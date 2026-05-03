<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Analog\Analog;
use Galette\Core\Preferences;

use function Safe\curl_exec;
use function Safe\curl_init;
use function Safe\curl_setopt;
use function Safe\fclose;
use function Safe\fwrite;
use function Safe\json_encode;
use function Safe\proc_close;
use function Safe\proc_open;
use function Safe\stream_get_contents;

/**
 * External script call
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ExternalScript
{
    private string $protocol;
    private string $method;
    private string $uri;
    private bool $as_json = true;
    private string $output;

    /**
     * Main constructor
     *
     * @param Preferences $pref Galette preferences
     */
    public function __construct(Preferences $pref)
    {
        $uri = $pref->pref_new_contrib_script;
        [$protocol, ] = explode('://', $uri);

        if ($protocol == $uri) {
            Analog::log(
                'An URI must be specified',
                Analog::ERROR
            );
        }

        switch ($protocol) {
            case 'galette':
                $this->method = 'galette';
                $uri = $pref->getURL() . str_replace($protocol . '://', '/', $uri);
                $this->protocol = explode('://', $uri)[0];
                break;
            case 'file':
                $this->protocol = $protocol;
                $this->method = $protocol;
                break;
            case 'get':
            case 'post':
                $this->method = $protocol;
                $this->protocol = 'http';

                break;
            case 'gets':
            case 'posts':
                $this->method = trim($protocol, 's');
                $this->protocol = 'https';
                break;
            default:
                throw new \RuntimeException('Unknown protocol.');
        }

        Analog::log(
            static::class . ' instanced with method ' . $this->method
            . ' and protocol ' . $this->protocol,
            Analog::INFO
        );

        if ($protocol !== 'file') {
            $this->uri = str_replace(
                $protocol . '://',
                $this->protocol . '://',
                $uri
            );
        } elseif (file_exists($uri)) {
            $this->uri = str_replace(
                $protocol . '://',
                '',
                $uri
            );
        } else {
            throw new \RuntimeException(
                __METHOD__ . 'File ' . $uri . ' does not exists!'
            );
        }

        Analog::log(
            static::class . ' URI set to ' . $this->uri,
            Analog::INFO
        );
    }

    /**
     * Send data
     *
     * @param array<string,mixed> $params Array of params to send
     */
    public function send(array $params): bool
    {
        if (count($params) == 0) {
            throw new \RuntimeException(__METHOD__ . ': parameters are mandatory.');
        }

        $uri = $this->uri;

        switch ($this->method) {
            case 'get':
                $ch = curl_init();
                if ($this->as_json === true) {
                    $uri .= '?params=' . json_encode($params);
                } else {
                    $url_params = http_build_query($params, 'galette_');
                    $uri .= '?' . $url_params;
                }
                curl_setopt($ch, CURLOPT_URL, $uri);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $this->output = curl_exec($ch);
                $result = (bool)$this->output;
                break;
            case 'galette':
            case 'post':
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->uri);
                curl_setopt($ch, CURLOPT_POST, (bool)count($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                if ($this->as_json === true) {
                    curl_setopt(
                        $ch,
                        CURLOPT_POSTFIELDS,
                        [
                            'params' => json_encode($params)
                        ]
                    );
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                }
                $this->output = curl_exec($ch);
                $result = (bool)$this->output;
                break;
            case 'file':
                $this->output = '';
                if ($this->as_json === true) {
                    $params = json_encode($params);
                } else {
                    $imploded = '';
                    foreach ($params as $k => $v) {
                        $imploded .= ' ' . $k . '=' . $v;
                    }
                    $params = $imploded;
                }

                $descriptors = [
                    0   => ['pipe', 'r'],
                    1   => ['pipe', 'w'],
                    2   => ['pipe', 'w']
                ];

                $process = proc_open(
                    $uri,
                    $descriptors,
                    $pipes
                );
                fwrite($pipes[0], $params);
                fclose($pipes[0]);

                //get stdout, if any
                $output = stream_get_contents($pipes[1]);
                if (trim($output) !== '') {
                    $this->output .= "\n\nStdOut:\n" . $output;
                }

                //get stderr, if any
                $errors = stream_get_contents($pipes[2]);
                if (trim($errors) !== '') {
                    $this->output .= "\n\nStdErr:\n" . $errors;
                }

                //closes pipes and process
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                $result = trim($this->output) === '';
                break;
            default:
                throw new \RuntimeException(
                    __METHOD__ . ': unknown method ' . $this->method
                );
        }

        Analog::log(
            __METHOD__ . ' result: ' . $result . "output:\n" . $this->output,
            Analog::DEBUG
        );

        return $result;
    }

    /**
     * Get full output (only relevant is method is file)
     */
    public function getOutput(): string
    {
        return $this->output;
    }
}
