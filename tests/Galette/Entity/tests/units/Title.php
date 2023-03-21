<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Titles tests
 *
 * PHP version 5
 *
 * Copyright © 2019-2023 The Galette Team
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
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-14
 */

namespace Galette\Entity\test\units;

use atoum;
use Laminas\Db\Adapter\Adapter;

/**
 * Status tests
 *
 * @category  Entity
 * @name      Title
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-14
 */
class Title extends atoum
{
    private \Galette\Core\Db $zdb;
    private array $remove = [];

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
        }
        $this->deleteTitle();
    }

    /**
     * Delete status
     *
     * @return void
     */
    private function deleteTitle()
    {
        if (is_array($this->remove) && count($this->remove) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\Title::TABLE);
            $delete->where->in(\Galette\Entity\Title::PK, $this->remove);
            $this->zdb->execute($delete);
        }

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test title
     *
     * @return void
     */
    public function testTitle()
    {
        global $zdb;
        $zdb = $this->zdb;

        $titles = new \Galette\Repository\Titles($this->zdb);
        if (count($titles->getList()) === 0) {
            $res = $titles->installInit();
            $this->boolean($res)->isTrue();
        }

        $title = new \Galette\Entity\Title();

        $title->short = 'Te.';
        $title->long = 'Test';
        $this->boolean($title->store($this->zdb))->isTrue();

        $id = $title->id;
        $this->remove[] = $id;
        $title = new \Galette\Entity\Title($id); //reload

        $title->long = 'Test title';
        $this->boolean($title->store($this->zdb))->isTrue();
        $title = new \Galette\Entity\Title($id); //reload

        $this->string($title->long)->isIdenticalTo('Test title');

        $title = new \Galette\Entity\Title(\Galette\Entity\Title::MR);
        $this->exception(
            function () use ($title) {
                $title->remove($this->zdb);
            }
        )
            ->hasMessage('You cannot delete Mr. or Mrs. titles!')
            ->isInstanceOf('\RuntimeException');

        $title = new \Galette\Entity\Title($id); //reload
        $this->boolean(
            $title->remove($this->zdb)
        )->isTrue();
    }
}
