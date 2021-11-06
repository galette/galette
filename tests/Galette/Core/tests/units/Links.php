<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password tests
 *
 * PHP version 5
 *
 * Copyright © 2020-2021 The Galette Team
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
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-03-15
 */

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;

/**
 * Password tests class
 *
 * @category  Core
 * @name      Password
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-03-15
 */
class Links extends GaletteTestCase
{
    //private $pass = null;
    protected $seed = 95842355;
    private $links;
    private $ids = [];
    protected $excluded_after_methods = ['testDuplicateLinkTarget'];

    /**
     * Set up tests
     *
     * @param string $method Method name
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        parent::beforeTestMethod($method);
        $this->initStatus();
        $this->initContributionsTypes();

        $this->links = new \Galette\Core\Links($this->zdb, false);
        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Cleanup after testeach test method
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        parent::afterTestMethod($method);

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Core\Links::TABLE);
        $this->zdb->execute($delete);

        $this->cleanHistory();
    }

    /**
     * Test new Link generation
     *
     * @return void
     */
    public function testGenerateNewLink()
    {
        $links = $this->links;
        $this->getMemberTwo();
        $id = $this->adh->id;

        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        );

        $this->string($res)->isNotEmpty();

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(1);

        $this->array($links->isHashValid($res, 'phoarau@tele2.fr'))->isIdenticalTo([
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        ]);

        $this->boolean($links->isHashValid($res, 'any@mail.com'))->isFalse();
        $this->boolean($links->isHashValid(base64_encode('sthingthatisnotahash'), 'phoarau@tele2.fr'))->isFalse();

        $this->createContribution();
        $cid = $this->contrib->id;
        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_INVOICE,
            $cid
        );

        $this->string($res)->isNotEmpty();
        $this->array($links->isHashValid($res, 'phoarau@tele2.fr'))->isIdenticalTo([
            \Galette\Core\Links::TARGET_INVOICE,
            $cid
        ]);
    }

    /**
     * Test expired is invalid
     *
     * @return void
     */
    public function testExpiredValidate()
    {
        $links = $this->links;
        $this->getMemberTwo();
        $id = $this->adh->id;

        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        );

        $this->string($res)->isNotEmpty();

        $this->array($links->isHashValid($res, 'phoarau@tele2.fr'))->isIdenticalTo([
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        ]);

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(1);

        $update = $this->zdb->update(\Galette\Core\Links::TABLE);
        $old_date = new \DateTime();
        $old_date->sub(new \DateInterval('P2W'));
        $update
            ->set(['creation_date' => $old_date->format('Y-m-d')])
            ->where(['hash' => base64_decode($res)]);
        $this->zdb->execute($update);

        $this->boolean($links->isHashValid($res, 'phoarau@tele2.fr'))->isFalse();
    }

    /**
     * Test cleanExpired
     *
     * @return void
     */
    public function testCleanExpired()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Not expired link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );
        $this->zdb->execute($insert);

        $date->sub(new \DateInterval('P1W'));
        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Expired link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 2
            ]
        );
        $this->zdb->execute($insert);

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(2);

        $links = new \Galette\Core\Links($this->zdb, true);

        $results = $this->zdb->execute($select);
        $result = $results->current();
        $this->integer($results->count())->isIdenticalTo(1);
        $this->string($result['hash'])->isIdenticalTo('Not expired link');
    }

    /**
     * Test duplicate target
     *
     * @return void
     */
    public function testDuplicateLinkTarget()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Unique link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );
        $this->zdb->execute($insert);

        $date->sub(new \DateInterval('PT1H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Unique link (but we did not know before)',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );

        $this->exception(
            function () use ($insert) {
                $this->zdb->execute($insert);
            }
        )
            ->hasMessage('Duplicate entry');
    }

    /**
     * Create test contribution in database
     *
     * @return void
     */
    protected function createContribution()
    {
        $bdate = new \DateTime(); // 2020-11-07
        $bdate->sub(new \DateInterval('P1Y')); // 2019-11-07
        $bdate->sub(new \DateInterval('P6M')); // 2019-05-07
        $bdate->add(new \DateInterval('P13D')); // 2019-05-20

        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 3,
            'montant_cotis' => 111,
            'type_paiement_cotis' => 6,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
        ];
        $this->createContrib($data);
        $this->checkContribExpected();
    }

    /**
     * Check contributions expecteds
     *
     * @param Contribution $contrib       Contribution instance, if any
     * @param array        $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkContribExpected($contrib = null, $new_expecteds = [])
    {
        if ($contrib === null) {
            $contrib = $this->contrib;
        }

        $date_begin = $contrib->raw_begin_date;
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->object($contrib->raw_date)->isInstanceOf('DateTime');
        $this->object($contrib->raw_begin_date)->isInstanceOf('DateTime');
        $this->object($contrib->raw_end_date)->isInstanceOf('DateTime');

        $expecteds = [
            'id_adh' => "{$this->adh->id}",
            'id_type_cotis' => 3,
            'montant_cotis' => '111',
            'type_paiement_cotis' => '6',
            'info_cotis' => 'FAKER' . $this->seed,
            'date_fin_cotis' => $date_end->format('Y-m-d')
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        $this->string($contrib->raw_end_date->format('Y-m-d'))->isIdenticalTo($expecteds['date_fin_cotis']);

        foreach ($expecteds as $key => $value) {
            $property = $this->contrib->fields[$key]['propname'];
            switch ($key) {
                case \Galette\Entity\ContributionsTypes::PK:
                    $ct = $this->contrib->type;
                    if ($ct instanceof \Galette\Entity\ContributionsTypes) {
                        $this->integer((int)$ct->id)->isIdenticalTo($value);
                    } else {
                        $this->integer($ct)->isIdenticalTo($value);
                    }
                    break;
                default:
                    $this->variable($contrib->$property)->isEqualTo($value, $property);
                    break;
            }
        }

        //load member from db
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        //member is now up-to-date
        $this->string($this->adh->getRowClass())->isIdenticalTo('active cotis-late');
        $this->string($this->adh->due_date)->isIdenticalTo($this->contrib->end_date);
        $this->boolean($this->adh->isUp2Date())->isFalse();
    }
}
