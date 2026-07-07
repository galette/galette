<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Galette\IO\File;
use Galette\Util\Text;
use Throwable;
use Analog\Analog;
use PHPMailer\PHPMailer\PHPMailer;

use function Safe\preg_match;

/**
 * Generic email for Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteMail
{
    public const int MAIL_ERROR = 0;
    public const int MAIL_SENT = 1;

    public const int METHOD_DISABLED = 0;
    public const int METHOD_PHPMAIL = 1;
    public const int METHOD_SMTP = 2;
    //value 3 (former METHOD_QMAIL) is no longer used, do not reuse it
    public const int METHOD_GMAIL = 4;
    public const int METHOD_SENDMAIL = 5;

    public const int SENDER_PREFS = 0;
    public const int SENDER_CURRENT = 1;
    public const int SENDER_OTHER = 2;

    private string $sender_name;
    private string $sender_address;
    private string $subject;
    private string $message;
    private bool $html = false;
    private int $word_wrap = 70;
    private int $timeout = 300;

    /** @var array<int, string> */
    private array $errors = [];
    /** @var array<string, string> */
    private array $recipients = [];

    private PHPMailer $mail;
    /** @var array<int,File> */
    protected array $attachments = [];

    /**
     * Constructor
     *
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(private readonly Preferences $preferences)
    {
        $this->setSender(
            $preferences->pref_email_nom,
            $preferences->pref_email
        );
        if (!$this->preferences->pref_bool_wrap_mails) {
            $this->word_wrap = 0;
        }
    }

    /**
     * Create the underlying PHPMailer instance.
     * Extracted as a seam so tests can inject a double that does not
     * actually send anything.
     */
    protected function createMailer(): PHPMailer
    {
        return new PHPMailer();
    }

    /**
     * Initialize PHPMailer
     */
    private function initMailer(): void
    {
        global $i18n;

        $this->mail = $this->createMailer();
        $this->mail->Timeout = $this->timeout;

        switch ($this->preferences->pref_mail_method) {
            case self::METHOD_SMTP:
            case self::METHOD_GMAIL:
                //if we want to send emails using a smtp server
                $this->mail->IsSMTP();
                // enables SMTP debug information
                if (Galette::isDebugEnabled()) {
                    $this->mail->SMTPDebug = 4;
                    //cannot use a callable here; this prevents class to be serialized
                    //see https://bugs.galette.eu/issues/1468
                    $this->mail->Debugoutput = 'error_log';
                }

                if ($this->preferences->pref_mail_method == self::METHOD_GMAIL) {
                    // sets GMAIL as the SMTP server
                    $this->mail->Host = "smtp.gmail.com";
                    // enable SMTP authentication
                    $this->mail->SMTPAuth   = true;
                    // sets the prefix to the servier
                    $this->mail->SMTPSecure = "tls";
                    // set the SMTP port for the GMAIL server
                    $this->mail->Port = 587;
                } else {
                    $this->mail->Host = $this->preferences->pref_mail_smtp_host;
                    $this->mail->SMTPAuth = $this->preferences->pref_mail_smtp_auth;

                    if (!$this->preferences->pref_mail_smtp_secure || $this->preferences->pref_mail_allow_unsecure) {
                        //Allow "unsecure" SMTP connections if user has asked fot it or
                        //if user did not request TLS explicitly
                        $this->mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            ]
                        ];
                    }

                    if ($this->preferences->pref_mail_smtp_port) {
                        // set the SMTP port for the SMTP server
                        $this->mail->Port = $this->preferences->pref_mail_smtp_port;
                    } else {
                        $this->mail->Port = $this->preferences->pref_mail_smtp_secure ? 587 : 25;
                        Analog::log(
                            sprintf(
                                '[%1$s]No SMTP port provided. Switch to default (%2$s).',
                                static::class,
                                $this->mail->Port
                            ),
                            Analog::INFO
                        );
                    }

                    if ($this->preferences->pref_mail_smtp_secure && $this->mail->Port == 465) {
                        $this->mail->SMTPSecure = "ssl";
                    } elseif ($this->preferences->pref_mail_smtp_secure && $this->mail->Port == 587) {
                        $this->mail->SMTPSecure = "tls";
                    }
                }

                // SMTP account username
                $this->mail->Username   = $this->preferences->pref_mail_smtp_user;
                // SMTP account password
                $this->mail->Password   = $this->preferences->pref_mail_smtp_password;
                //keep the SMTP connection open across messages (mailing batches)
                $this->mail->SMTPKeepAlive = (bool)$this->preferences->pref_mail_smtp_keepalive;
                break;
            case self::METHOD_SENDMAIL:
                // telling the class to use Sendmail transport
                $this->mail->IsSendmail();
                break;
        }

        $this->mail->CharSet = 'UTF-8';
        $this->mail->SetLanguage($i18n->getAbbrev());

        if ($this->word_wrap > 0) {
            $this->mail->WordWrap = $this->word_wrap;
        }
    }

    /**
     * Sets the recipients
     * For mailing convenience; all recipients will be added as BCC,
     * regular recipient will be the sender.
     *
     * @param array<string, string> $recipients Array (mail=>name) of all recipients
     */
    public function setRecipients(array $recipients): bool
    {
        $res = true;

        if (!isset($this->mail)) {
            $this->initMailer();
        }

        $this->mail->ClearBCCs();
        if ($recipients !== []) {
            $this->recipients = [];
            foreach ($recipients as $mail => $name) {
                if (self::isValidEmail($mail)) {
                    $this->recipients[$mail] = $name;
                    $this->mail->AddBCC($mail, $name);
                } else {
                    //one of addresses is not valid:
                    //- set $res to false
                    //- clear BCCs
                    //- log an INFO
                    $res = false;
                    Analog::log(
                        '[' . static::class
                        . '] One of recipients address is not valid.',
                        Analog::INFO
                    );
                    $this->mail->ClearBCCs();
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * Apply final header to email and send it :-)
     *
     * @return int Either GaletteMail::MAIL_ERROR|GaletteMail::MAIL_SENT
     */
    public function send(): int
    {
        //when a batch size is set and there are more recipients than
        //this size, split the mailing into several messages
        $batch_size = (int)$this->preferences->pref_mail_batch_size;
        if ($batch_size > 0 && count($this->recipients) > $batch_size) {
            return $this->sendBatched(
                $batch_size,
                (int)$this->preferences->pref_mail_batch_delay
            );
        }

        $this->prepareMessage();

        //set at least on real recipient (not bcc)
        if (count($this->recipients) === 1) {
            //there is only one recipient, clean bcc and readd as simple recipient
            $this->mail->ClearBCCs();
            $this->mail->AddAddress(
                key($this->recipients),
                current($this->recipients)
            );
        } else {
            //we're sending a mailing. Set main recipient to sender
            $this->mail->AddAddress(
                $this->getSenderAddress(),
                $this->getSenderName()
            );
        }

        try {
            //reinit errors array
            $this->errors = [];
            //let's send the email
            if (!$this->mail->Send()) {
                $this->errors[] = $this->mail->ErrorInfo;
                Analog::log(
                    'An error occurred sending email to: '
                    . implode(', ', array_keys($this->recipients))
                    . "\n" . $this->mail->ErrorInfo,
                    Analog::INFO
                );
                unset($this->mail);
                return self::MAIL_ERROR;
            } else {
                $txt = '';
                foreach ($this->recipients as $k => $v) {
                    $txt .= $v . ' (' . $k . '), ';
                }
                Analog::log(
                    'An email has been sent to: ' . $txt,
                    Analog::INFO
                );
                unset($this->mail);
                return self::MAIL_SENT;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Error sending message: ' . $e->getMessage(),
                Analog::ERROR
            );
            $this->errors[] = $e->getMessage();
            unset($this->mail);
            return self::MAIL_ERROR;
        }
    }

    /**
     * Prepare the message (sender, reply-to, body, signature and attachments).
     * Recipients are *not* set here; they are handled by the caller so the
     * message can be reused across several batches.
     */
    private function prepareMessage(): void
    {
        if (!isset($this->mail)) {
            $this->initMailer();
        }

        //set sender
        $this->mail->SetFrom(
            $this->getSenderAddress(),
            $this->getSenderName()
        );
        // Add a Reply-To field in the email headers.
        // Fix bug #6654.
        if ($this->preferences->pref_email_reply_to) {
            $this->mail->AddReplyTo($this->preferences->pref_email_reply_to);
        } else {
            $this->mail->AddReplyTo($this->getSenderAddress());
        }

        if ($this->html) {
            //the email is html :(
            $this->mail->AltBody = $this->getTextMessage();
            $this->mail->IsHTML(true);
        } else {
            //the email is plaintext :)
            $this->mail->AltBody = '';
            $this->mail->IsHTML(false);
        }

        $this->mail->Subject = $this->subject;
        $this->mail->Body = $this->message;

        $signature = $this->preferences->getMailSignature($this->mail);
        if ($signature != '') {
            if ($this->html) {
                //we are sending HTML message
                //apply email sign to text version
                $this->mail->AltBody .= $this->preferences->getMailSignature($this->mail, true);
                //then apply email sign to HTML version
                $sign_style = 'color:grey;border-top:1px solid #ccc;margin-top:2em';
                $hsign = '<div style="' . $sign_style . '">'
                    . nl2br($signature) . '</div>';
                $this->mail->Body .= $hsign;
            } else {
                $this->mail->Body .= $this->preferences->getMailSignature($this->mail, true);
            }
        }

        //join attachments
        if (count($this->attachments) > 0) {
            foreach ($this->attachments as $attachment) {
                $this->mail->AddAttachment(
                    $attachment->getDestDir() . $attachment->getFileName()
                );
            }
        }
    }

    /**
     * Send the message to recipients in batches, reusing the SMTP connection.
     *
     * All recipients are added as BCC and split into chunks of at most
     * $batch_size addresses per message, with an optional delay between two
     * messages. This helps comply with mail servers that restrict the number
     * of recipients per message or the sending rate.
     *
     * @param int $batch_size Maximum number of recipients (BCC) per message
     * @param int $delay      Delay, in seconds, between two messages
     *
     * @return int GaletteMail::MAIL_SENT if every batch has been sent,
     *             GaletteMail::MAIL_ERROR if at least one batch failed
     */
    private function sendBatched(int $batch_size, int $delay): int
    {
        $this->prepareMessage();

        //mailing: the main recipient is always the sender, members stay in BCC
        $this->mail->ClearAddresses();
        $this->mail->AddAddress(
            $this->getSenderAddress(),
            $this->getSenderName()
        );

        //reinit errors array
        $this->errors = [];
        $has_error = false;
        $chunks = array_chunk($this->recipients, $batch_size, true);
        $nb_chunks = count($chunks);

        foreach ($chunks as $i => $chunk) {
            $this->mail->ClearBCCs();
            foreach ($chunk as $address => $name) {
                $this->mail->AddBCC($address, $name);
            }

            try {
                if (!$this->mail->Send()) {
                    $has_error = true;
                    $this->errors[] = $this->mail->ErrorInfo;
                    Analog::log(
                        'An error occurred sending mailing batch to: '
                        . implode(', ', array_keys($chunk))
                        . "\n" . $this->mail->ErrorInfo,
                        Analog::INFO
                    );
                } else {
                    Analog::log(
                        'A mailing batch has been sent to '
                        . count($chunk) . ' recipient(s).',
                        Analog::INFO
                    );
                }
            } catch (Throwable $e) {
                $has_error = true;
                $this->errors[] = $e->getMessage();
                Analog::log(
                    'Error sending mailing batch: ' . $e->getMessage(),
                    Analog::ERROR
                );
            }

            //pause between two messages, but not after the last one
            if ($delay > 0 && $i < $nb_chunks - 1) {
                sleep($delay);
            }
        }

        //close the (possibly kept-alive) SMTP connection
        $this->mail->smtpClose();
        unset($this->mail);

        return $has_error ? self::MAIL_ERROR : self::MAIL_SENT;
    }

    /**
     * Check if an email address is valid
     *
     * @param string $address the email address to check
     */
    public static function isValidEmail(string $address): bool
    {
        $valid = PHPMailer::ValidateAddress($address);
        if (!$valid) {
            Analog::log(
                '[GaletteMail] Address `' . $address . '` is not valid ',
                Analog::DEBUG
            );
        }
        return $valid;
    }

    /**
     * Check if a string is a URL
     *
     * @param string $url the URL to check
     */
    public static function isUrl(string $url): bool
    {
        $valid = preg_match(
            '|^http(s)?://\[?[a-z0-9-]+(.[a-z0-9-]+)*\]?(:\d+)?(/.*)?$|i',
            $url
        );
        if (!$valid) {
            Analog::log(
                '[GaletteMail] `' . $url . '` is not an url',
                Analog::DEBUG
            );
            return false;
        }
        return true;
    }

    /**
     * Get AltText for HTML emails
     *
     * @return string current message in plaintext format
     */
    protected function getTextMessage(): string
    {
        return Text::convertHtmlToText($this->message);
    }

    /**
     * Retrieve PHPMailer main object
     *
     * @return PHPMailer object
     */
    protected function getPhpMailer(): PHPMailer
    {
        if (!isset($this->mail)) {
            $this->initMailer();
        }

        return $this->mail;
    }

    /**
     * Is the email HTML formatted?
     *
     * @param ?bool $set The value to set
     */
    public function isHTML(?bool $set = null): bool
    {
        if (is_bool($set)) {
            $this->html = $set;
        }
        return $this->html;
    }

    /**
     * Get sender name
     */
    public function getSenderName(): string
    {
        return $this->sender_name;
    }

    /**
     * Get sender address
     */
    public function getSenderAddress(): string
    {
        return $this->sender_address;
    }

    /**
     * Get the subject
     *
     * @return string The subject
     */
    public function getSubject(): string
    {
        return $this->subject ?? '';
    }

    /**
     * Retrieve errors
     *
     * @return array<int,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the message
     *
     * @return string The message
     */
    public function getMessage(): string
    {
        return $this->message ?? '';
    }

    /**
     * Get the message, wrapped
     *
     * @return string Wrapped message
     */
    public function getWrappedMessage(): string
    {
        if ($this->word_wrap > 0) {
            if (!isset($this->mail)) {
                $this->initMailer();
            }

            return $this->mail->wrapText(
                $this->message,
                $this->word_wrap
            );
        } else {
            return $this->message;
        }
    }

    /**
     * Sets the subject
     *
     * @param string $subject The subject
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Sets the message
     *
     * @param string $message The message
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Sets the sender
     *
     * @param string $name    Sender name
     * @param string $address Sender address
     */
    public function setSender(string $name, string $address): self
    {
        $this->sender_name = $name;
        $this->sender_address = $address;
        return $this;
    }

    /**
     * Set timeout on SMTP connexion
     *
     * @param int $timeout SMTP timeout
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }
}
