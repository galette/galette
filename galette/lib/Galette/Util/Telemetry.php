<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Handle Telemetry data
 *
 * PHP version 5
 *
 * Copyright © 2017 GLPI and Contributors
 * Copyright © 2017 The Galette Team
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
 * @category  Util
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 GLPI and Contributors
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9
 */

namespace Galette\Util;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\Plugins;

/**
 * Handle Telemetry data
 *
 * @category  Util
 * @name      Telemetry
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 GLPI and Contributors
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9
 */
class Telemetry
{
    private $zdb;
    private $prefs;
    private $plugins;

    /**
     * Constructor
     *
     * @param Db          $zdb     Database instance
     * @param Preferences $prefs   Preferences instance
     * @param Plugins     $plugins Plugins instance
     */
    public function __construct(Db $zdb, Preferences $prefs, Plugins $plugins)
    {
        $this->zdb = $zdb;
        $this->prefs = $prefs;
        $this->plugins = $plugins;
    }

   /**
    * Grab telemetry informations
    *
    * @return array
    */
    public function getTelemetryInfos()
    {
        $data = [
            'galette'  => $this->grabGaletteInfos(),
            'system'   => [
                'db'           => $this->grabDbInfos(),
                'web_server'   => $this->grabWebserverInfos(),
                'php'          => $this->grabPhpInfos(),
                'os'           => $this->grabOsInfos()
            ]
        ];
        return $data;
    }

   /**
    * Grab Galette part informations
    *
    * @return array
    */
    public function grabGaletteInfos()
    {
        $galette = [
            'uuid'               => $this->getInstanceUuid(),
            'version'            => GALETTE_VERSION,
            'plugins'            => [],
            'default_language'   => $this->prefs->pref_lang,
            'usage'              => [
                'avg_members'           => $this->getAverage(\Galette\Entity\Adherent::TABLE),
                'avg_contributions'     => $this->getAverage(\Galette\Entity\Contribution::TABLE),
                'avg_transactions'      => $this->getAverage(\Galette\Entity\Transaction::TABLE)
            ]
        ];

        $plugins = $this->plugins->getModules();
        foreach ($plugins as $plugin) {
            $galette['plugins'][] = [
                'key'       => $plugin['name'],
                'version'   => $plugin['version']
            ];
        }

        return $galette;
    }

    /**
     * Grab DB part informations
     *
     * @return array
     */
    public function grabDbInfos()
    {
        $dbinfos = $this->zdb->getInfos();
        return $dbinfos;
    }

    /**
     * Grab web server part informations
     *
     * @return array
     */
    public function grabWebserverInfos()
    {
        $headers = false;
        $engine  = '';
        $version = '';

        // check if host is present (do no throw php warning in contrary of get_headers)
        if (PHP_SAPI !== 'cli') {
            $headers = get_headers($this->prefs->getURL());
        }

        if (is_array($headers)) {
            //BEGIN EXTRACTING SERVER DETAILS
            $pattern = '#^Server:*#i';
            $matches = preg_grep($pattern, $headers);

            if (count($matches)) {
                $infos = current($matches);
                $pattern = '#Server: ([^ ]+)/([^ ]+)#i';
                preg_match($pattern, $infos, $srv_infos);
                if (count($srv_infos) == 3) {
                    $engine  = $srv_infos[1];
                    $version = $srv_infos[2];
                }
            }
        }

        $server = [
            'engine'    => $engine,
            'version'   => $version
        ];

        return $server;
    }

    /**
     * Grab PHP part informations
     *
     * @return array
     */
    public function grabPhpInfos()
    {
        $php = [
            'version'   => str_replace(PHP_EXTRA_VERSION, '', PHP_VERSION),
            'modules'   => get_loaded_extensions(),
            'setup'     => [
                'max_execution_time'    => ini_get('max_execution_time'),
                'memory_limit'          => ini_get('memory_limit'),
                'post_max_size'         => ini_get('post_max_size'),
                'safe_mode'             => ini_get('safe_mode'),
                'session'               => ini_get('session.save_handler'),
                'upload_max_filesize'   => ini_get('upload_max_filesize')
            ]
        ];

        return $php;
    }

    /**
     * Grab OS part informations
     *
     * @return array
     */
    public function grabOsInfos()
    {
        $os = [
            'family'       => php_uname('s'),
            'distribution' => '',
            'version'      => php_uname('r')
        ];

        return $os;
    }

    /**
     * Count
     *
     * @param string $table Table to query
     * @param array  $where Where clause, if any
     *
     * @return integer
     */
    public function getCount($table, $where = [])
    {
        $select = $this->zdb->select($table);
        $select->columns([
            'cnt' => new \Zend\Db\Sql\Expression(
                'COUNT(1)'
            )
        ]);
        $results = $this->zdb->execute($select);
        $result = $results->current();
        return (int)$result->cnt;
    }

    /**
     * Calculate average parts
     *
     * @param string $table Table to query
     * @param array  $where Where clause, if any
     *
     * @return string
     */
    private function getAverage($table, $where = [])
    {
        $count = $this->getCount($table, $where);

        if ($count <= 50) {
            return '0-50';
        } elseif ($count <= 250) {
            return '50-250';
        } elseif ($count <= 500) {
            return '250-500';
        } elseif ($count <= 1000) {
            return '500-1000';
        } elseif ($count <= 5000) {
            return '1000-5000';
        }
        return '5000+';
    }

   /**
     * Send telemetry informations
     *
     * @return boolean
     */
    public function send()
    {
        $data = $this->getTelemetryInfos();
        $infos = json_encode(['data' => $data]);

        $uri = GALETTE_TELEMETRY_URI . 'telemetry';
        $ch = curl_init($uri);
        $opts = [
            CURLOPT_URL             => $uri,
            CURLOPT_USERAGENT       => 'Galette/' . GALETTE_VERSION,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_POSTFIELDS      => $infos,
            CURLOPT_HTTPHEADER      => ['Content-Type:application/json']
        ];

        curl_setopt_array($ch, $opts);
        $content = json_decode(curl_exec($ch));
        $errstr = curl_error($ch);
        curl_close($ch);

        if ($content && property_exists($content, 'message')) {
            if (property_exists($content, 'errors')) {
                $errors = '';
                foreach ($content->errors as $error) {
                    $errors .= "\n" . $error->property . ': ' . $error->message;
                }
                throw new \RuntimeException($errors);
            }

            $this->prefs->pref_telemetry_date = date('Y-m-d H:i:s');
            $this->prefs->store();

            //all is OK!
            return true;
        } else {
            $message = 'Something went wrong sending telemetry informations';
            if ($errstr != '') {
                $message .= ": $errstr";
            }
            Analog::log(
                $message,
                Analog::ERROR
            );
            throw new \RuntimeException($message);
        }
    }

    /**
     * Get UUID
     *
     * @param string $type UUID type (either instance or registration)
     *
     * @return string
     */
    private function getUuid($type)
    {
        $param = 'pref_' . $type . '_uuid';
        $uuid = $this->prefs->$param;
        if (empty($uuid)) {
            $uuid = self::generateUuid($type);
        }
        return $uuid;
    }

    /**
     * Get instance UUID
     *
     * @return string
     */
    private function getInstanceUuid()
    {
        return $this->getUuid('instance');
    }

   /**
     * Get registration UUID
     *
     * @return string
     */
    final public function getRegistrationUuid()
    {
        return $this->getUuid('registration');
    }


    /**
     * Generates an unique identifier and store it
     *
     * @param string $type UUID type (either instance or registration)
     *
     * @return string
     */
    final public function generateUuid($type)
    {
        $uuid = $this->getRandomString(40);
        $param = 'pref_' . $type . '_uuid';
        $this->prefs->$param = $uuid;
        $this->prefs->store();
        return $uuid;
    }

    /**
     * Generates an unique identifier for current instance and store it
     *
     * @return string
     */
    final public function generateInstanceUuid()
    {
        return self::generateUuid('instance');
    }

    /**
     * Generates an unique identifier for current instance and store it
     *
     * @return string
     */
    final public function generateRegistrationUuid()
    {
        return self::generateUuid('registration');
    }

    /**
     * Get date telemetry has been sent
     *
     * @return string
     */
    public function getSentDate()
    {
        return $this->prefs->pref_telemetry_date;
    }

    /**
     * Get date of registration
     *
     * @return string
     */
    public function getRegistrationDate()
    {
        return $this->prefs->pref_registration_date;
    }

    /**
     * Does telemetry infos has been sent already?
     *
     * @return boolean
     */
    public function isSent()
    {
        return $this->getSentDate() != false;
    }

    /**
     * Is instance registered?
     *
     * @return boolean
     */
    public function isRegistered()
    {
        return $this->getRegistrationDate() != false;
    }

   /**
    * Get a random string
    *
    * @param integer $length of the random string
    *
    * @return random string
    *
    * @see https://stackoverflow.com/questions/4356289/php-random-string-generator/31107425#31107425
    */
    private function getRandomString($length)
    {
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }
}
