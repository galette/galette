<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF contributions model
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

namespace Galette\Entity;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Entity\Contribution;
use NumberFormatter;

/**
 * PDF contribution model
 *
 * @category  Entity
 * @name      PdfContribution
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

abstract class PdfContribution extends PdfModel
{
    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param int         $type        Model type
     * @param mixed       $args        Arguments
     */
    public function __construct(Db $zdb, Preferences $preferences, int $type, $args = null)
    {
        parent::__construct($zdb, $preferences, $type, $args);

        $this->setPatterns(
            $this->getMemberPatterns() + $this->getContributionPatterns()
        );
    }

    /**
     * Get patterns for a contribution
     *
     * @param boolean $legacy Whether to load legacy patterns
     *
     * @return array
     */
    protected function getContributionPatterns($legacy = true): array
    {
        $dynamic_patterns = $this->getDynamicPatterns('contrib');

        $c_patterns = [
            'contrib_label'     => [
                'title'     => _T('Contribution label'),
                'pattern'   => '/{CONTRIB_LABEL}/',
            ],
            'contrib_amount'    => [
                'title'     => _T('Amount'),
                'pattern'   => '/{CONTRIB_AMOUNT}/',
            ],
            'contrib_amount_letters' => [
                'title'     => _T('Amount (in letters)'),
                'pattern'   => '/{CONTRIB_AMOUNT_LETTERS}/',
            ],
            'contrib_date'      => [
                'title'     => _T('Full date'),
                'pattern'   => '/{CONTRIB_DATE}/',
            ],
            'contrib_year'      => [
                'title'     => _T('Contribution year'),
                'pattern'   => '/{CONTRIB_YEAR}/',
            ],
            'contrib_comment'   => [
                'title'     => _T('Comment'),
                'pattern'   => '/{CONTRIB_COMMENT}/',
            ],
            'contrib_bdate'     => [
                'title'     => _T('Begin date'),
                'pattern'   => '/{CONTRIB_BEGIN_DATE}/',
            ],
            'contrib_edate'     => [
                'title'     => _T('End date'),
                'pattern'   => '/{CONTRIB_END_DATE}/',
            ],
            'contrib_id'        => [
                'title'     => _T('Contribution id'),
                'pattern'   => '/{CONTRIB_ID}/',
            ],
            'contrib_payment'   => [
                'title'     => _T('Payment type'),
                'pattern'   => '/{CONTRIB_PAYMENT_TYPE}/'
            ]
        ];

        if ($legacy === true) {
            foreach ($c_patterns as $key => $pattern) {
                $nkey = '_' . $key;
                $pattern['pattern'] = str_replace(
                    'CONTRIB_',
                    'CONTRIBUTION_',
                    $pattern['pattern']
                );
                $c_patterns[$nkey] = $pattern;
            }
        }

        return $c_patterns + $dynamic_patterns;
    }

    /**
     * Set contribution and proceed related replacements
     *
     * @param Contribution $contrib Contribution
     *
     * @return PdfModel
     */
    public function setContribution(Contribution $contrib): PdfContribution
    {
        global $login, $i18n;

        $formatter = new NumberFormatter($i18n->getID(), NumberFormatter::SPELLOUT);

        $c_replacements = [
            'contrib_label'     => $contrib->type->libelle,
            'contrib_amount'    => $contrib->amount,
            'contrib_amount_letters' => $formatter->format($contrib->amount),
            'contrib_date'      => $contrib->date,
            'contrib_year'      => $contrib->raw_date->format('Y'),
            'contrib_comment'   => $contrib->info,
            'contrib_bdate'     => $contrib->begin_date,
            'contrib_edate'     => $contrib->end_date,
            'contrib_id'        => $contrib->id,
            'contrib_payment'   => $contrib->spayment_type,
        ];

        foreach ($c_replacements as $key => $replacement) {
            $nkey = '_' . $key;
            $c_replacements[$nkey] = $replacement;
        }
        $this->setReplacements($c_replacements);

        /** the list of all dynamic fields */
        $fields = new \Galette\Repository\DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList('contrib');
        $this->setDynamicFields('contrib', $dynamic_fields, $contrib);

        return $this;
    }

    /**
     * Build legend array
     *
     * @return array
     */
    public function getLegend(): array
    {
        $legend = parent::getLegend();

        $patterns = $this->getContributionPatterns(false);

        $legend['contribution'] = [
            'title'     => _T('Contribution information'),
            'patterns'  => $patterns
        ];

        return $legend;
    }
}
