<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transaction tests
 *
 * PHP version 5
 *
 * Copyright © 2021 The Galette Team
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
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-10-16
 */

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Transaction tests class
 *
 * @category  Entity
 * @name      Transaction
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-10-16
 */
class Transaction extends GaletteTestCase
{
    protected $seed = 95842354;
    /** @var \Galette\Entity\Transaction */
    private $transaction;

    /**
     * Cleanup after each test method
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();

        //first, remove contributions
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        //then, remove transactions
        $delete = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $delete->where(['trans_desc' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        //remove members with parents
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        //remove all others members
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        parent::beforeTestMethod($testMethod);
        $this->initContributionsTypes();

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $this->transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Create test transaction in database
     *
     * @return void
     */
    private function createTransaction()
    {
        $date = new \DateTime(); // 2020-11-07
        $data = [
            'id_adh' => $this->adh->id,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'trans_desc' => 'FAKER' . $this->seed
        ];

        $this->transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $check = $this->transaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $this->transaction->store($this->history);
        $this->boolean($store)->isTrue();

        return $this->transaction;
    }

    /**
     * Test empty transaction
     *
     * @return void
     */
    public function testEmpty()
    {
        $this->variable($this->transaction->id)->isNull();
        $this->variable($this->transaction->date)->isEqualTo(date('Y-m-d'));
        $this->variable($this->transaction->amount)->isNull();
        $this->variable($this->transaction->description)->isNull();

        $this->float($this->transaction->getDispatchedAmount())->isIdenticalTo((double)0);
        $this->float($this->transaction->getMissingAmount())->isIdenticalTo((double)0);
        $this->string($this->transaction->getRowClass())->isIdenticalTo('transaction-normal');
        $this->array($this->transaction->fields)
            ->hasSize(5)
            ->hasKeys([
                \Galette\Entity\Transaction::PK,
                \Galette\Entity\Adherent::PK,
                'trans_date',
                'trans_amount',
                'trans_desc'
            ]);

        $this->variable($this->transaction->unknown_property)->isEqualTo(false);
    }

    /**
     * Test getter and setter special cases
     *
     * @return void
     */
    public function testGetterSetter()
    {
        $transaction = $this->transaction;

        //set a bad date
        $data = ['trans_date' => 'mypassword'];
        $expected = ['- Wrong date format (Y-m-d) for Date!'];
        $check = $transaction->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        //set a correct date
        $data = ['trans_date' => '1999-01-01'];
        $check = $transaction->check($data, [], []);
        $this->boolean($check)->isTrue();
        $this->string($transaction->date)->isIdenticalTo('1999-01-01');

        //set a bad amount
        $data = ['trans_amount' => 'mypassword'];
        $expected = ['- The amount must be an integer!'];
        $check = $transaction->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        //set a correct amount
        $data = ['trans_amount' => 1256];
        $check = $transaction->check($data, ['trans_amount' => 1], []);
        $this->boolean($check)->isTrue();
        $this->string($transaction->amount)->isIdenticalTo('1256');

        //set a bad description
        $data = ['trans_desc' => 'this is a very long description that should give an error; because the length of transaction description is limited to 150 characters long, even if this is quite hard to find something to write.'];
        $expected = ['- Transaction description must be 150 characters long maximum.'];
        $check = $transaction->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);
    }

    /**
     * Test transaction creation
     *
     * @return void
     */
    public function testCreation()
    {
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();
    }

    /**
     * Test fields labels
     *
     * @return void
     */
    public function testGetFieldLabel()
    {
        $this->string($this->transaction->getFieldLabel('trans_amount'))
            ->isIdenticalTo('Amount');

        $this->string($this->transaction->getFieldLabel('trans_date'))
            ->isIdenticalTo('Date');

        $this->string($this->transaction->getFieldLabel('trans_desc'))
            ->isIdenticalTo('Description');

        $this->string($this->transaction->getFieldLabel(\Galette\Entity\Adherent::PK))
            ->isIdenticalTo('Originator');
    }

    /**
     * Test transaction loading
     *
     * @return void
     */
    public function testLoad()
    {
        $this->login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($this->login)->isLogged = true;
        $this->calling($this->login)->isStaff = true;
        $this->calling($this->login)->isAdmin = true;

        $this->getMemberOne();

        //create transaction for member
        $this->createTransaction();

        $id = $this->transaction->id;
        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);

        $this->boolean($transaction->load((int)$id))->isTrue();
        $this->boolean($transaction->load(1355522012))->isFalse();
    }

    /**
     * Test transaction removal
     *
     * @return void
     */
    public function testRemove()
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();

        $this->getMemberOne();
        $this->createTransaction();

        $tid = $this->transaction->id;
        $this->boolean($this->transaction->load($tid))->isTrue();
        $this->boolean($this->transaction->remove($this->history))->isTrue();
        $this->boolean($this->transaction->load($tid))->isFalse();

        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->boolean($transaction->remove($this->history))->isFalse();
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan()
    {
        $this->getMemberOne();
        //create contribution for member
        $this->createTransaction();
        $transaction = $this->transaction;

        $this->boolean($transaction->canShow($this->login))->isFalse();

        //Superadmin can fully change contributions
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();

        $this->boolean($transaction->canShow($this->login))->isTrue();

        //logout
        $this->login->logOut();
        $this->boolean($this->login->isLogged())->isFalse();

        //Member can fully change its own contributions
        $mdata = $this->dataAdherentOne();
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();

        $this->boolean($transaction->canShow($this->login))->isTrue();

        //logout
        $this->login->logOut();
        $this->boolean($this->login->isLogged())->isFalse();

        //Another member has no access
        $this->getMemberTwo();
        $mdata = $this->dataAdherentTwo();
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();

        $this->boolean($transaction->canShow($this->login))->isFalse();

        //parents can chow change children contributions
        $this->getMemberOne();
        $member = $this->adh;
        $mdata = $this->dataAdherentOne();
        global $login;
        $login = $this->login;
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();

        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $member->id,
            'attach'        => true,
            'login_adh'     => 'child.johny.doe',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $child = $this->createMember($child_data);
        $cid = $child->id;

        //contribution for child
        $date = new \DateTime(); // 2020-11-07

        $data = [
            'id_adh' => $cid,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'trans_desc' => 'FAKER' . $this->seed
        ];

        $ctransaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $check = $ctransaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $ctransaction->store($this->history);
        $this->boolean($store)->isTrue();

        $this->login->logOut();

        //load child from db
        $child = new \Galette\Entity\Adherent($this->zdb);
        $child->enableDep('parent');
        $this->boolean($child->load($cid))->isTrue();

        $this->string($child->name)->isIdenticalTo($child_data['nom_adh']);
        $this->object($child->parent)->isInstanceOf('\Galette\Entity\Adherent');
        $this->integer($child->parent->id)->isIdenticalTo($member->id);
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();

        $mdata = $this->dataAdherentOne();
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();

        $this->boolean($ctransaction->canShow($this->login))->isTrue();

        //logout
        $this->login->logOut();
        $this->boolean($this->login->isLogged())->isFalse();
    }
}
