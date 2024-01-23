<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2009-03-07
 */

namespace Galette\Core;

use Analog\Analog;
use ArrayObject;
use Galette\Entity\Adherent;
use Galette\IO\File;
use Laminas\Db\ResultSet\ResultSet;

/**
 * Mailing features
 *
 * @category  Core
 * @name      Mailing
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2009-03-07
 *
 * @property string $subject
 * @property string $message
 * @property boolean $html
 * @property integer $current_step
 * @property-read  integer $step
 * @property integer|string $id
 * @property-read string $alt_message
 * @property-read string $wrapped_message
 * @property-read PHPMailer\PHPMailer\PHPMailer $mail
 * @property-read PHPMailer\PHPMailer\PHPMailer $_mail
 * @property-read array $errors
 * @property-read array $recipients
 * @property-read string|false $tmp_path
 * @property array $attachments
 * @property-read string $sender_name
 * @property-read string $sender_address
 * @property integer $history_id
 */
class Mailing extends GaletteMail
{
    public const STEP_START = 0;
    public const STEP_PREVIEW = 1;
    public const STEP_SEND = 2;
    public const STEP_SENT = 3;

    public const MIME_HTML = 'text/html';
    public const MIME_TEXT = 'text/plain';
    public const MIME_DEFAULT = self::MIME_TEXT;

    private string|int $id;

    /** @var array<int, Adherent> */
    private array $unreachables = array();
    /** @var array<int, Adherent> */
    private array $mrecipients = array();
    private int $current_step;

    private string $mime_type;

    private ?string $tmp_path;
    private int $history_id;

    /**
     * Default constructor
     *
     * @param Preferences          $preferences Preferences instance
     * @param array<int, Adherent> $members     An array of members
     * @param ?integer             $id          Identifier, defaults to null
     */
    public function __construct(Preferences $preferences, array $members = [], int $id = null)
    {
        parent::__construct($preferences);
        $this->id = $id ?? $this->generateNewId();

        $this->current_step = self::STEP_START;
        $this->mime_type = self::MIME_DEFAULT;
        /** TODO: add a preference that propose default mime-type to use,
            then init it here */
        if (count($members)) {
            //Check which members have a valid email address and which have not
            $this->setRecipients($members);
        }
        $this->loadAttachments();
    }

    /**
     * Generate new mailing id and temporary path
     *
     * @return string
     */
    private function generateNewId(): string
    {
        $id = '';
        $chars = 'abcdefghjkmnpqrstuvwxyz0123456789';
        $i = 0;
        $size = 30;
        while ($i <= $size - 1) {
            $num = mt_rand(0, strlen($chars) - 1) % strlen($chars);
            $id .= substr($chars, $num, 1);
            $i++;
        }

        $this->id = $id;
        $this->generateTmpPath($this->id);
        return $this->id;
    }

    /**
     * Generate temporary path
     *
     * @param ?string $id Random id, defaults to null
     *
     * @return void
     */
    private function generateTmpPath(string $id = null): void
    {
        if ($id === null) {
            $id = $this->generateNewId();
        }
        $this->tmp_path = GALETTE_ATTACHMENTS_PATH . '/' . $id;
    }

    /**
     * Load mailing attachments
     *
     * @return void
     */
    private function loadAttachments(): void
    {
        $dir = '';
        if (
            isset($this->tmp_path)
            && trim($this->tmp_path) !== ''
        ) {
            $dir = $this->tmp_path;
        } else {
            $dir = GALETTE_ATTACHMENTS_PATH . $this->id . '/';
        }

        $files = glob($dir . '*.*');
        foreach ($files as $file) {
            $f = new File($dir);
            $f->setFileName(str_replace($dir, '', $file));
            $this->attachments[] = $f;
        }
    }

    /**
     * Loads a mailing from history
     *
     * @param ArrayObject<string, mixed> $rs  Mailing entry
     * @param boolean                    $new True if we create a 'new' mailing,
     *                                        false otherwise (from preview for example)
     *
     * @return boolean
     */
    public function loadFromHistory(ArrayObject $rs, bool $new = true): bool
    {
        global $zdb;

        try {
            $orig_recipients = unserialize($rs->mailing_recipients);
        } catch (\Throwable $e) {
            Analog::log(
                'Unable to unserialize recipients for mailing ' . $rs->mailing_id,
                Analog::ERROR
            );
            $orig_recipients = [];
        }

        $_recipients = array();
        $mdeps = ['parent' => true];
        foreach ($orig_recipients as $k => $v) {
            $m = new Adherent($zdb, $k, $mdeps);
            $_recipients[] = $m;
        }
        $this->setRecipients($_recipients);
        $this->subject = $rs->mailing_subject;
        $this->message = $rs->mailing_body;
        if ($rs->mailing_sender_name !== null || $rs->mailing_sender_address !== null) {
            $this->setSender(
                $rs->mailing_sender_name,
                $rs->mailing_sender_address
            );
        }
        //if mailing has already been sent, generate a new id and copy attachments
        if ($rs->mailing_sent && $new) {
            $this->generateNewId();
            $this->copyAttachments($rs->mailing_id);
        } else {
            $this->tmp_path = null;
            $this->id = $rs->mailing_id;
            if (!$this->attachments) {
                $this->loadAttachments();
            }
            $this->history_id = $rs->mailing_id;
        }
        return true;
    }

    /**
     * Copy attachments from another mailing
     *
     * @param int $id Original mailing id
     *
     * @return void
     */
    private function copyAttachments(int $id): void
    {
        $source_dir = GALETTE_ATTACHMENTS_PATH . $id . '/';
        $dest_dir = GALETTE_ATTACHMENTS_PATH . $this->id . '/';

        if (file_exists($source_dir)) {
            if (file_exists($dest_dir)) {
                throw new \RuntimeException(
                    str_replace(
                        '%s',
                        $this->id,
                        'Attachments directory already exists for mailing %s!'
                    )
                );
            } else {
                //create directory
                mkdir($dest_dir);
                //copy attachments from source mailing and populate attachments
                $this->attachments = array();
                $files = glob($source_dir . '*.*');
                foreach ($files as $file) {
                    $f = new File($source_dir);
                    $f->setFileName(str_replace($source_dir, '', $file));
                    $f->copyTo($dest_dir);
                    $this->attachments[] = $f;
                }
            }
        } else {
            Analog::log(
                'No attachments in source directory',
                Analog::DEBUG
            );
        }
    }

    /**
     * Apply final header to email and send it :-)
     *
     * @return int
     */
    public function send(): int
    {
        $m = array();
        foreach ($this->mrecipients as $member) {
            $email = $member->getEmail();
            $m[$email] = $member->sname;
        }
        parent::setRecipients($m);
        return parent::send();
    }

    /**
     * Set mailing recipients
     *
     * @phpstan-ignore-next-line
     * @param array<int, Adherent> $members Array of Adherent objects
     *
     * @return bool
     */
    public function setRecipients(array $members): bool
    {
        $m = array();
        $this->mrecipients = array();
        $this->unreachables = array();

        foreach ($members as $member) {
            $email = $member->getEmail();

            if (trim($email) != '' && self::isValidEmail($email)) {
                if (!in_array($member, $this->mrecipients)) {
                    $this->mrecipients[] = $member;
                }
                $m[$email] = $member->sname;
            } else {
                if (!in_array($member, $this->unreachables)) {
                    $this->unreachables[] = $member;
                }
            }
        }
        return parent::setRecipients($m);
    }

    /**
     * Store maling attachments
     *
     * @param array<string, string|int> $files Array of uploaded files to store
     *
     * @return true|int error code
     */
    public function store(array $files): bool|int
    {
        if ($this->tmp_path === null) {
            $this->generateTmpPath();
        }

        if (!file_exists($this->tmp_path)) {
            //directory does not exist, create it
            mkdir($this->tmp_path);
        }

        if (!is_dir($this->tmp_path)) {
            throw new \RuntimeException(
                $this->tmp_path . ' should be a directory!'
            );
        }

        //store files
        $attachment = new File($this->tmp_path);
        $res = $attachment->store($files);
        if ($res < 0) {
            return $res;
        } else {
            $this->attachments[] = $attachment;
        }

        return true;
    }

    /**
     * Move attachments with final id once mailing has been stored
     *
     * @param int $id Mailing history id
     *
     * @return void
     */
    public function moveAttachments(int $id): void
    {
        if (
            isset($this->tmp_path)
            && trim($this->tmp_path) !== ''
            && count($this->attachments) > 0
        ) {
            foreach ($this->attachments as &$attachment) {
                $old_path = $attachment->getDestDir() . $attachment->getFileName();
                $new_path = GALETTE_ATTACHMENTS_PATH . $id . '/' .
                    $attachment->getFileName();
                if (!file_exists(GALETTE_ATTACHMENTS_PATH . $id)) {
                    mkdir(GALETTE_ATTACHMENTS_PATH . $id);
                }
                $moved = rename($old_path, $new_path);
                if ($moved) {
                    $attachment->setDestDir(GALETTE_ATTACHMENTS_PATH);
                }
            }
            rmdir($this->tmp_path);
            $this->tmp_path = null;
        }
    }

    /**
     * Remove specified attachment
     *
     * @param string $name Filename
     *
     * @return void
     */
    public function removeAttachment(string $name): void
    {
        $to_remove = null;
        if (
            isset($this->tmp_path)
            && trim($this->tmp_path) !== ''
            && file_exists($this->tmp_path)
        ) {
            $to_remove = $this->tmp_path;
        } elseif (file_exists(GALETTE_ATTACHMENTS_PATH . $this->id)) {
            $to_remove = GALETTE_ATTACHMENTS_PATH . $this->id;
        }

        if ($to_remove !== null) {
            $to_remove .= '/' . $name;

            if (!$this->attachments) {
                $this->loadAttachments();
            }

            if (file_exists($to_remove)) {
                $i = 0;
                foreach ($this->attachments as $att) {
                    if ($att->getFileName() == $name) {
                        unset($this->attachments[$i]);
                        unlink($to_remove);
                        break;
                    }
                    $i++;
                }
            } else {
                Analog::log(
                    str_replace(
                        '%file',
                        $name,
                        'File %file does not exists and cannot be removed!'
                    ),
                    Analog::WARNING
                );
            }
        } else {
            throw new \RuntimeException(
                'Unable to get attachments path!'
            );
        }
    }

    /**
     * Remove mailing attachments
     *
     * @param boolean $temp Remove only temporary attachments,
     *                      to avoid history breaking
     *
     * @return boolean
     */
    public function removeAttachments(bool $temp = false): bool
    {
        $to_remove = null;
        if (
            isset($this->tmp_path)
            && trim($this->tmp_path) !== ''
            && file_exists($this->tmp_path)
        ) {
            $to_remove = $this->tmp_path;
        } elseif (file_exists(GALETTE_ATTACHMENTS_PATH . $this->id)) {
            if ($temp === true) {
                return false;
            }
            $to_remove = GALETTE_ATTACHMENTS_PATH . $this->id;
        }

        if ($to_remove !== null) {
            $rdi = new \RecursiveDirectoryIterator(
                $to_remove,
                \FilesystemIterator::SKIP_DOTS
            );
            $contents = new \RecursiveIteratorIterator(
                $rdi,
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($contents as $path) {
                if ($path->isFile()) {
                    unlink($path->getPathname());
                } else {
                    rmdir($path->getPathname());
                }
            }
            rmdir($to_remove);
        }
        return true;
    }

    /**
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    public function getAttachmentErrorMessage(int $code): string
    {
        $f = new File($this->tmp_path);
        return $f->getErrorMessage($code);
    }

    /**
     * Does mailing already exists in history?
     *
     * @return boolean
     */
    public function existsInHistory(): bool
    {
        return isset($this->history_id);
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name)
    {
        $forbidden = array('ordered');
        if (!in_array($name, $forbidden)) {
            switch ($name) {
                case 'alt_message':
                    return $this->cleanedHtml();
                case 'step':
                    return $this->current_step;
                case 'subject':
                    return $this->getSubject();
                case 'message':
                    return $this->getMessage();
                case 'wrapped_message':
                    return $this->getWrappedMessage();
                case 'html':
                    return $this->isHTML();
                case 'mail':
                case '_mail':
                    return $this->getPhpMailer();
                case 'errors':
                    return $this->getErrors();
                case 'recipients':
                    return $this->mrecipients;
                case 'tmp_path':
                    if (isset($this->tmp_path) && trim($this->tmp_path) !== '') {
                        return $this->tmp_path;
                    } else {
                        //no attachments
                        return false;
                    }
                case 'attachments':
                    return $this->attachments;
                case 'sender_name':
                    return $this->getSenderName();
                case 'sender_address':
                    return $this->getSenderAddress();
                case 'history_id':
                    return $this->$name;
                default:
                    Analog::log(
                        '[' . get_class($this) . 'Trying to get ' . $name,
                        Analog::DEBUG
                    );
                    return $this->$name;
            }
        } else {
            Analog::log(
                '[' . get_class($this) . 'Unable to get ' . $name,
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $forbidden = array('ordered');
        if (!in_array($name, $forbidden)) {
            switch ($name) {
                case 'alt_message':
                case 'step':
                case 'subject':
                case 'message':
                case 'wrapped_message':
                case 'html':
                case 'mail':
                case '_mail':
                case 'errors':
                case 'recipients':
                case 'tmp_path':
                case 'attachments':
                case 'sender_name':
                case 'sender_address':
                    return true;
            }
            return isset($this->$name);
        }

        return false;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'subject':
                $this->setSubject($value);
                break;
            case 'message':
                $this->setMessage($value);
                break;
            case 'html':
                if (is_bool($value)) {
                    $this->isHTML($value);
                } else {
                    Analog::log(
                        '[' . get_class($this) . '] Value for field `' . $name .
                        '` should be boolean - (' . gettype($value) . ')' .
                        $value . ' given',
                        Analog::WARNING
                    );
                }
                break;
            case 'current_step':
                if (
                    is_int($value)
                    && ($value == self::STEP_START
                    || $value == self::STEP_PREVIEW
                    || $value == self::STEP_SEND
                    || $value == self::STEP_SENT)
                ) {
                    $this->current_step = (int)$value;
                } else {
                    Analog::log(
                        '[' . get_class($this) . '] Value for field `' . $name .
                        '` should be integer and know - (' . gettype($value) . ')' .
                        $value . ' given',
                        Analog::WARNING
                    );
                }
                break;
            case 'id':
                $this->id = $value;
                break;
            default:
                Analog::log(
                    '[' . get_class($this) . '] Unable to set property `' . $name . '`',
                    Analog::WARNING
                );
        }
    }
}
