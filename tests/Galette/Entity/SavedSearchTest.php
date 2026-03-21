<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use Galette\Entity\SavedSearch;

/**
 * SavedSearch tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearchTest extends GaletteTestCase
{
    /**
     * Test SavedSearch instantiation
     */
    public function testInstantiation(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);
        $this->assertInstanceOf(SavedSearch::class, $search);
    }

    /**
     * Test check() with valid data
     */
    public function testCheckValid(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search',
            'form' => 'Adherent',
            'parameters' => [
                'name' => 'test',
                'surname' => 'user'
            ]
        ];

        $result = $search->check($data, [], []);
        $this->assertTrue($result);
        $this->assertCount(0, $search->getErrors());
    }

    /**
     * Test check() with missing mandatory field (form)
     */
    public function testCheckMissingForm(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search',
            'parameters' => [
                'name' => 'test'
            ]
        ];

        $result = $search->check($data, [], []);
        $this->assertIsArray($result);
        $errors = $search->getErrors();
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('mandatory', strtolower($errors[0]));
    }

    /**
     * Test check() with empty form value
     */
    public function testCheckEmptyForm(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search',
            'form' => '',
            'parameters' => []
        ];

        $result = $search->check($data, [], []);
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($search->getErrors()));
    }

    /**
     * Test check() with invalid form value
     */
    public function testCheckInvalidForm(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search',
            'form' => 'InvalidForm',
            'parameters' => []
        ];

        $result = $search->check($data, [], []);
        $this->assertIsArray($result);
        $errors = $search->getErrors();
        $this->assertGreaterThan(0, count($errors));
    }

    /**
     * Test check() sets author_id for non-super admin
     */
    public function testCheckSetsAuthorId(): void
    {
        // Login as regular user
        $this->login->login('test_adh', 'pass_adh');

        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search',
            'form' => 'Adherent',
            'parameters' => []
        ];

        $result = $search->check($data, [], []);
        $this->assertTrue($result);
        $this->assertEquals($this->login->id, $search->author_id);
    }

    /**
     * Test store() after successful check()
     */
    public function testStore(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search Store',
            'form' => 'Adherent',
            'parameters' => [
                'name' => 'test',
                'active' => true
            ]
        ];

        $check = $search->check($data, [], []);
        $this->assertTrue($check);

        $store = $search->store();
        $this->assertTrue($store);
        $this->assertGreaterThan(0, $search->id);

        // Clean up
        $search->remove();
    }

    /**
     * Test remove()
     */
    public function testRemove(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);

        $data = [
            'name' => 'Test Search Remove',
            'form' => 'Adherent',
            'parameters' => []
        ];

        $search->check($data, [], []);
        $search->store();
        $id = $search->id;

        $this->assertGreaterThan(0, $id);

        $remove = $search->remove();
        $this->assertTrue($remove);
    }

    /**
     * Test getErrors() returns array
     */
    public function testGetErrors(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);
        $errors = $search->getErrors();
        
        $this->assertIsArray($errors);
    }

    /**
     * Test __set with empty name
     */
    public function testSetEmptyName(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);
        $search->name = '';
        
        $errors = $search->getErrors();
        $this->assertGreaterThan(0, count($errors));
    }

    /**
     * Test __set with valid name
     */
    public function testSetValidName(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);
        $search->name = 'Valid Search Name';
        
        $this->assertEquals('Valid Search Name', $search->name);
    }

    /**
     * Test __set with parameters array
     */
    public function testSetParameters(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);
        $params = ['key' => 'value', 'another' => 'data'];
        $search->parameters = $params;
        
        $this->assertEquals($params, $search->parameters);
    }

    /**
     * Test getKnownForms()
     */
    public function testGetKnownForms(): void
    {
        $search = new SavedSearch($this->zdb, $this->login);
        $forms = $search->getKnownForms();
        
        $this->assertIsArray($forms);
        $this->assertContains('Adherent', $forms);
    }
}

