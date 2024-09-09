<?php

/**
 * Copyright © 2003-2024 The Galette Team
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
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Exception;
use Galette\Core\I18n;
use Galette\Features\Replacements;
use Slim\Routing\RouteParser;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Password;
use Galette\Core\Preferences;

/**
 * Texts class for galette
 *
 * @author John Perr <johnperr@abul.org>
 * @author Johan Cwiklinski <joahn@x-tnd.be>
 */
class Texts
{
    use Replacements {
        getLegend as protected trait_getLegend;
    }

    /** @var ArrayObject<string, int|string> */
    private ArrayObject $all_texts;
    public const TABLE = "texts";
    public const PK = 'tid';
    public const DEFAULT_REF = 'sub';

    /** @var array<int, mixed> */
    private array $defaults;
    private ?string $current;

    /**
     * Main constructor
     *
     * @param Preferences      $preferences Galette's preferences
     * @param RouteParser|null $routeparser RouteParser instance
     */
    public function __construct(Preferences $preferences, ?RouteParser $routeparser = null)
    {
        global $zdb, $login, $container;
        $this->preferences = $preferences;
        if ($routeparser === null) {
            $routeparser = $container->get(RouteParser::class);
        }
        if ($login === null) {
            $login = $container->get('login');
        }
        $this->routeparser = $routeparser;
        $this
            ->setDb($zdb)
            ->setLogin($login);

        $this->setPatterns(
            $this->getMainPatterns()
            + $this->getMailPatterns()
            + $this->getMemberPatterns()
            + $this->getContributionPatterns()
        );

        if (!defined('GALETTE_INSTALLER') || GALETTE_INSTALLER !== true) {
            $this
                ->setMain()
                ->setMail();
        }
    }

    /**
     * Get patterns for mails
     *
     * @param boolean $legacy Whether to load legacy patterns
     *
     * @return array<string, array<string, string>>
     */
    protected function getMailPatterns(bool $legacy = true): array
    {
        $m_patterns = [
            'breakline'     => [
                'title'     => _T('Insert a carriage return'),
                'pattern'   => '/{BR}/',
            ],
            'newline'    => [
                'title'     => _T('Insert a new blank line'),
                'pattern'   => '/{NEWLINE}/',
            ],
            'link_validity'     => [
                'title'     => _T('Link validity'),
                'pattern'   => '/{LINK_VALIDITY}/',
                'onlyfor'   => ['sub', 'pwd']
            ],
            'link_membercard'   => [
                'title'     => _T('Direct link for member card download'),
                'pattern'   => '/{LINK_MEMBERCARD}/',
                'onlyfor'   => ['contrib', 'donation']
            ],
            'link_contribpdf'   => [
                'title'     => _T('Direct link for invoice/receipt download'),
                'pattern'   => '/{LINK_CONTRIBPDF}/',
                'onlyfor'   => ['contrib', 'donation']
            ],
            'change_pass_uri'       => [
                'title'     => _T("Galette's change password URI"),
                'pattern'   => '/{CHG_PWD_URI}/',
                'onlyfor'   => ['sub', 'pwd']
            ],
        ];

        //clean based on current ref and onlyfor
        if (!empty($this->current)) {
            foreach ($m_patterns as $key => $m_pattern) {
                if (
                    isset($m_pattern['onlyfor'])
                    && !in_array($this->current, $m_pattern['onlyfor'])
                ) {
                    unset($m_patterns[$key]);
                }
            }
        }

        return $m_patterns;
    }

    /**
     * Set emails replacements
     *
     * @return self
     */
    public function setMail(): self
    {
        $this->setReplacements([
            'link_validity'     => null,
            'breakline'         => "\r\n",
            'newline'           => "\r\n\r\n",
            'link_membercard'   => null,
            'link_contribpdf'   => null,
            'change_pass_uri'   => null
        ]);
        return $this;
    }

    /**
     * Set change password URL
     *
     * @param Password $password Password instance
     *
     * @return self
     */
    public function setChangePasswordURI(Password $password): self
    {
        $this->setReplacements([
            'change_pass_uri'   => $this->preferences->getURL() .
                $this->routeparser->urlFor(
                    'password-recovery',
                    ['hash' => base64_encode($password->getHash())]
                )
        ]);
        return $this;
    }

    /**
     * Set validity link
     *
     * @return self
     */
    public function setLinkValidity(): self
    {
        $link_validity = new \DateTime();
        $link_validity->add(new \DateInterval('PT24H'));
        $this->setReplacements(['link_validity' => $link_validity->format(_T("Y-m-d H:i:s"))]);
        return $this;
    }

    /**
     * Set member card PDF link
     *
     * @param string $link Link
     *
     * @return self
     */
    public function setMemberCardLink(string $link): self
    {
        $this->setReplacements(['link_membercard' => $link]);
        return $this;
    }

    /**
     * Set contribution PDF link
     *
     * @param string $link Link
     *
     * @return self
     */
    public function setContribLink(string $link): self
    {
        $this->setReplacements(['link_contribpdf' => $link]);
        return $this;
    }

    /**
     * Get specific text
     *
     * @param string $ref  Reference of text to get
     * @param string $lang Language texts to get
     *
     * @return ArrayObject<string, int|string> of all text fields for one language.
     */
    public function getTexts(string $ref, string $lang): ArrayObject
    {
        global $i18n;

        //check if language is set and exists
        $langs = $i18n->getList();
        $is_lang_ok = false;
        foreach ($langs as $l) {
            if ($lang === $l->getID()) {
                $is_lang_ok = true;
                break;
            }
        }

        if ($is_lang_ok !== true) {
            Analog::log(
                'Language ' . $lang .
                ' does not exists. Falling back to default Galette lang.',
                Analog::ERROR
            );
            $lang = $i18n->getID();
        }

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where(
                array(
                    'tref' => $ref,
                    'tlang' => $lang
                )
            );
            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                $this->all_texts = $result;
            } else {
                //hum... no result... That means text do not exist in the
                //database, let's add it
                $default = null;
                $this->defaults = $this->getAllDefaults(); //load defaults
                foreach ($this->defaults as $d) {
                    if ($d['tref'] == $ref && $d['tlang'] == $lang) {
                        $default = $d;
                        break;
                    }
                }
                if ($default !== null) {
                    $values = array(
                        'tref'      => $default['tref'],
                        'tsubject'  => $default['tsubject'],
                        'tbody'     => $default['tbody'],
                        'tlang'     => $default['tlang'],
                        'tcomment'  => $default['tcomment']
                    );

                    try {
                        $this->insert([$values]);
                        return $this->getTexts($ref, $lang);
                    } catch (Throwable $e) {
                        Analog::log(
                            'Unable to add missing requested text "' . $ref .
                            ' (' . $lang . ') | ' . $e->getMessage(),
                            Analog::WARNING
                        );
                    }
                } else {
                    Analog::log(
                        'Unable to find missing requested text "' . $ref .
                        ' (' . $lang . ')',
                        Analog::WARNING
                    );
                }
            }

            $this->all_texts->tbody = str_replace(
                [
                    '{BR}',
                    '{NEWLINE}'
                ],
                [
                    "\r\n",
                    "\r\n\r\n"
                ],
                $this->all_texts->tbody
            );
            return $this->all_texts;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot get text `' . $ref . '` for lang `' . $lang . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Set text
     *
     * @param string $ref     Texte ref to locate
     * @param string $lang    Texte language to locate
     * @param string $subject Subject to set
     * @param string $body    Body text to set
     *
     * @return bool
     */
    public function setTexts(string $ref, string $lang, string $subject, string $body): bool
    {
        try {
            $values = array(
                'tsubject' => $subject,
                'tbody'    => $body,
            );

            $update = $this->zdb->update(self::TABLE);
            $update->set($values)->where(
                array(
                    'tref'  => $ref,
                    'tlang' => $lang
                )
            );
            $this->zdb->execute($update);

            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error has occurred while storing email text. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Ref List
     *
     * @param string $lang Requested language
     *
     * @return array<int,mixed> list of references used for texts
     */
    public function getRefs(string $lang = I18n::DEFAULT_LANG): array
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                array('tref', 'tcomment')
            )->where(array('tlang' => $lang));

            $refs = [];
            $results = $this->zdb->execute($select);
            foreach ($results as $result) {
                $refs[] = $result;
            }
            return $refs;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot get refs for lang `' . $lang . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Initialize texts at install time
     *
     * @param boolean $check_first Check first if it seems initialized
     *
     * @return boolean false if no need to initialize, true if data has been initialized, Exception if error
     * @throws Throwable
     */
    public function installInit(bool $check_first = true): bool
    {
        try {
            //first of all, let's check if data seem to have already
            //been initialized
            $this->defaults = $this->getAllDefaults(); //load defaults
            $proceed = false;
            if ($check_first === true) {
                $select = $this->zdb->select(self::TABLE);
                $select->columns(
                    array(
                        'counter' => new Expression('COUNT(' . self::PK . ')')
                    )
                );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $count = $result->counter;
                if ($count == 0) {
                    //if we got no values in texts table, let's proceed
                    $proceed = true;
                } else {
                    if ($count < count($this->defaults)) {
                        return $this->checkUpdate();
                    }
                    return false;
                }
            } else {
                $proceed = true;
            }

            if ($proceed === true) {
                //first, we drop all values
                $delete = $this->zdb->delete(self::TABLE);
                $this->zdb->execute($delete);

                $this->zdb->handleSequence(
                    self::TABLE,
                    count($this->defaults)
                );

                $this->insert($this->defaults);

                Analog::log(
                    'Default texts were successfully stored into database.',
                    Analog::INFO
                );
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default texts.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Checks for missing texts in the database
     *
     * @return boolean
     */
    private function checkUpdate(): bool
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $dblist = $this->zdb->execute($select);

            $list = [];
            foreach ($dblist as $dbentry) {
                $list[] = $dbentry;
            }

            $missing = array();
            foreach ($this->defaults as $default) {
                $exists = false;
                foreach ($list as $text) {
                    if (
                        $text->tref == $default['tref']
                        && $text->tlang == $default['tlang']
                    ) {
                        $exists = true;
                        continue;
                    }
                }

                if ($exists === false) {
                    //text does not exists in database, insert it.
                    $missing[] = $default;
                }
            }

            if (count($missing) > 0) {
                $this->insert($missing);

                Analog::log(
                    'Missing texts were successfully stored into database.',
                    Analog::INFO
                );
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred checking missing texts.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
        return false;
    }

    /**
     * Get the subject, with all replacements done
     *
     * @return string
     */
    public function getSubject(): string
    {
        return $this->proceedReplacements($this->all_texts->tsubject);
    }

    /**
     * Get the body, with all replacements done
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->proceedReplacements($this->all_texts->tbody);
    }

    /**
     * Insert values in database
     *
     * @param array<int, mixed> $values Values to insert
     *
     * @return void
     */
    private function insert(array $values): void
    {
        $insert = $this->zdb->insert(self::TABLE);
        $insert->values(
            array(
                'tref'      => ':tref',
                'tsubject'  => ':tsubject',
                'tbody'     => ':tbody',
                'tlang'     => ':tlang',
                'tcomment'  => ':tcomment'
            )
        );
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

        foreach ($values as $value) {
            $stmt->execute($value);
        }
    }

    /**
     * Get default mail texts for all languages
     *
     * @return array<int,mixed>
     */
    public function getAllDefaults(): array
    {
        global $i18n;

        $all = [];
        foreach (array_keys($i18n->getArrayList()) as $lang) {
            $all = array_merge($all, $this->getDefaultTexts($lang));
        }

        return $all;
    }

    /**
     * Get default texts for specified language
     *
     * @param string $lang Requested lang. Defaults to en_US
     *
     * @return array<int,mixed>
     */
    public function getDefaultTexts(string $lang = 'en_US'): array
    {
        global $i18n;

        $current_lang = $i18n->getID();

        $i18n->changeLanguage($lang);

        //do the magic!
        include GALETTE_ROOT . 'includes/fields_defs/texts_fields.php';
        $texts = [];

        //@phpstan-ignore-next-line
        foreach ($texts_fields as $text_field) {
            unset($text_field['tid']);
            $text_field['tlang'] = $lang;
            $texts[] = $text_field;
        }

        //reset to current lang
        $i18n->changeLanguage($current_lang);
        return $texts;
    }

    /**
     * Build legend array
     *
     * @return array<string, mixed>
     */
    public function getLegend(): array
    {
        $legend = $this->trait_getLegend();

        $contribs = ['contrib', 'newcont', 'donation', 'newdonation'];
        if ($this->current !== null && in_array($this->current, $contribs)) {
            $patterns = $this->getContributionPatterns(false);
            $legend['contribution'] = [
                'title' => _T('Contribution information'),
                'patterns' => $patterns
            ];
        }

        $patterns = $this->getMailPatterns(false);
        $legend['mail'] = [
            'title'     => _T('Mail specific'),
            'patterns'  => $patterns
        ];

        return $legend;
    }

    /**
     * Set current text reference
     *
     * @param string $ref Reference
     *
     * @return self
     */
    public function setCurrent(string $ref): self
    {
        $this->current = $ref;
        return $this;
    }
}
