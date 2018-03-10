<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */

namespace Galette\Core;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\IO\File;

/**
 * Mailing features
 *
 * @category  Core
 * @name      Mailing
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */
class Mailing extends GaletteMail
{
    const STEP_START = 0;
    const STEP_PREVIEW = 1;
    const STEP_SEND = 2;
    const STEP_SENT = 3;

    const MIME_HTML = 'text/html';
    const MIME_TEXT = 'text/plain';
    const MIME_DEFAULT = self::MIME_TEXT;

    private $id;

    private $unreachables;
    private $mrecipients;
    private $current_step;

    private $mime_type;

    private $tmp_path;
    private $history_id;

    /**
     * Default constructor
     *
     * @param Preferences $preferences Preferences instance
     * @param array       $members     An array of members
     * @param int         $id          Identifier, defaults to null
     */
    public function __construct(Preferences $preferences, $members, $id = null)
    {
        parent::__construct($preferences);
        if ($id !== null) {
            $this->id = $id;
        } else {
            $this->generateNewId();
        }
        $this->current_step = self::STEP_START;
        $this->mime_type = self::MIME_DEFAULT;
        /** TODO: add a preference that propose default mime-type to use,
            then init it here */
        if ($members !== null) {
            //Check which members have a valid email address and which have not
            $this->setRecipients($members);
        }
        $this->loadAttachments();
    }

    /**
     * Generate new mailing id and temporary path
     *
     * @return void
     */
    private function generateNewId()
    {
        global $zdb;

        $pass = new Password($zdb);
        $this->id = $pass->makeRandomPassword(30);
        $this->generateTmpPath($this->id);
    }

    /**
     * Generate temporary path
     *
     * @param string $id Random id, defautls to null
     *
     * @return void
     */
    private function generateTmpPath($id = null)
    {
        if ($id === null) {
            global $zdb;

            $pass = new Password($zdb);
            $id = $pass->makeRandomPassword(30);
        }
        $this->tmp_path = GALETTE_ATTACHMENTS_PATH . '/' . $id;
    }

    /**
     * Load mailing attachments
     *
     * @return void
     */
    private function loadAttachments()
    {
        $dir = '';
        if (isset($this->tmp_path)
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
     * @param ResultSet $rs  Mailing entry
     * @param boolean   $new True if we create a 'new' mailing,
     *                       false otherwise (from preview for example)
     *
     * @return boolean
     */
    public function loadFromHistory($rs, $new = true)
    {
        global $zdb;

        $orig_recipients = unserialize($rs->mailing_recipients);

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
    }

    /**
     * Copy attachments from another mailing
     *
     * @param int $id Original mailing id
     *
     * @return void
     */
    private function copyAttachments($id)
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
     * Apply final header to mail and send it :-)
     *
     * @return GaletteMail::MAIL_ERROR|GaletteMail::MAIL_SENT
     */
    public function send()
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
     * @param array $members Array of Adherent objects
     *
     * @return void
     */
    public function setRecipients($members)
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
        parent::setRecipients($m);
    }

    /**
     * Store maling attachments
     *
     * @param array $files Array of uploaded files to store
     *
     * @return true|int error code
     */
    public function store($files)
    {
        if ($this->tmp_path === null) {
            $this->generateTmpPath();
        }

        if (!file_exists($this->tmp_path)) {
            //directory does not exists, create it
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
     * @return boolean
     */
    public function moveAttachments($id)
    {
        if (isset($this->tmp_path)
            && trim($this->tmp_path) !== ''
            && count($this->attachments) > 0
        ) {
            foreach ($this->attachments as &$attachment) {
                $old_path = $attachment->getDestDir() . $attachment->getFileName();
                $new_path = GALETTE_ATTACHMENTS_PATH . $this->id .'/' .
                    $attachment->getFileName();
                if (!file_exists(GALETTE_ATTACHMENTS_PATH . $this->id)) {
                    mkdir(GALETTE_ATTACHMENTS_PATH . $this->id);
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
    public function removeAttachment($name)
    {
        $to_remove = null;
        if (isset($this->tmp_path)
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
     * @param boolean $temp Remove only tmporary attachments,
     *                      to avoid history breaking
     *
     * @return void
     */
    public function removeAttachments($temp = false)
    {
        $to_remove = null;
        if (isset($this->tmp_path)
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
    }

    /**
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    public function getAttachmentErrorMessage($code)
    {
        $f = new File($this->tmp_path);
        return $f->getErrorMessage($code);
    }

    /**
     * Does mailing already exists in history?
     *
     * @return boolean
     */
    public function existsInHistory()
    {
        return isset($this->history_id);
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrive
     *
     * @return false|object the called property
     */
    public function __get($name)
    {
        $forbidden = array('ordered');
        if (!in_array($name, $forbidden)) {
            switch ($name) {
                case 'alt_message':
                    return $this->cleanedHtml();
                    break;
                case 'step':
                    return $this->current_step;
                    break;
                case 'subject':
                    return $this->getSubject();
                    break;
                case 'message':
                    return $this->getMessage();
                    break;
                case 'wrapped_message':
                    return $this->getWrappedMessage();
                    break;
                case 'html':
                    return $this->isHTML();
                    break;
                case 'mail':
                case '_mail':
                    return $this->getPhpMailer();
                    break;
                case 'errors':
                    return $this->getErrors();
                    break;
                case 'recipients':
                    return $this->mrecipients;
                    break;
                case 'tmp_path':
                    if (isset($this->tmp_path) && trim($this->tmp_path) !== '') {
                        return $this->tmp_path;
                    } else {
                        //no attachments
                        return false;
                    }
                    break;
                case 'attachments':
                    return $this->attachments;
                    break;
                case 'sender_name':
                    return $this->getSenderName();
                    break;
                case 'sender_address':
                    return $this->getSenderAddress();
                    break;
                default:
                    Analog::log(
                        '[' . get_class($this) . 'Trying to get ' . $name,
                        Analog::DEBUG
                    );
                    return $this->$name;
                    break;
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
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param object $value a relevant value for the property
     *
     * @return void
     */
    public function __set($name, $value)
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
                if (is_int($value)
                    && (   $value == self::STEP_START
                    || $value == self::STEP_PREVIEW
                    || $value == self::STEP_SEND
                    || $value == self::STEP_SENT )
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
                    '[' . get_class($this) . '] Unable to set proprety `' . $name . '`',
                    Analog::WARNING
                );
                return false;
                break;
        }
    }
}
