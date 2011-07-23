<?php
// +----------------------------------------------------------------------+
// | PHP versions 4 and 5                                                 |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2008 Manuel Lemos, Paul Cooper, Lorenzo Alberton  |
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// | MDB2 is a merge of PEAR DB and Metabases that provides a unified DB  |
// | API as well as database abstraction for PHP applications.            |
// | This LICENSE is in the BSD license style.                            |
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
// | Lukas Smith nor the names of his contributors may be used to endorse |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Authors: Paul Cooper <pgc@ucecom.com>                                |
// |          Lorenzo Alberton <l dot alberton at quipo dot it>           |
// +----------------------------------------------------------------------+
//
// $Id: MDB2_manager_testcase.php 292655 2009-12-26 15:53:03Z quipo $

require_once 'MDB2_testcase.php';

class MDB2_Manager_TestCase extends MDB2_TestCase {
    //test table name (it is dynamically created/dropped)
    var $table = 'newtable';

    function setUp() {
        parent::setUp();
        $this->db->loadModule('Manager', null, true);
        $this->fields = array(
            'id' => array(
                'type'     => 'integer',
                'unsigned' => true,
                'notnull'  => true,
                'default'  => 0,
            ),
            'somename' => array(
                'type'     => 'text',
                'length'   => 12,
            ),
            'somedescription'  => array(
                'type'     => 'text',
                'length'   => 12,
            ),
            'sex' => array(
                'type'     => 'text',
                'length'   => 1,
                'default'  => 'M',
            ),
        );
        $options = array();
        if ('mysql' == substr($this->db->phptype, 0, 5)) {
            $options['type'] = 'innodb';
        }
        if (!$this->tableExists($this->table)) {
            $result = $this->db->manager->createTable($this->table, $this->fields, $options);
            $this->assertFalse(PEAR::isError($result), 'Error creating table');
            $this->assertEquals(MDB2_OK, $result, 'Invalid return value for createTable()');
        }
    }

    function tearDown() {
        if ($this->tableExists($this->table)) {
            $result = $this->db->manager->dropTable($this->table);
            $this->assertFalse(PEAR::isError($result), 'Error dropping table');
        }
        $this->db->popExpect();
        unset($this->dsn);
        if (!PEAR::isError($this->db->manager)) {
            $this->db->disconnect();
        }
        unset($this->db);
    }

    /**
     * Create a sample table, test the new fields, and drop it.
     */
    function testCreateTable() {
        if (!$this->methodExists($this->db->manager, 'createTable')) {
            return;
        }
        if ($this->tableExists($this->table)) {
            $this->db->manager->dropTable($this->table);
        }

        $result = $this->db->manager->createTable($this->table, $this->fields);
        $this->assertFalse(PEAR::isError($result), 'Error creating table');
    }

    /**
     * Create a sample table, test the new fields, and drop it.
     */
    function testCreateAutoIncrementTable() {
        if (!$this->methodExists($this->db->manager, 'createTable')) {
            return;
        }
        if ($this->tableExists($this->table)) {
            $this->db->manager->dropTable($this->table);
        }
        $seq_name = $this->table;
        if ('ibase' == $this->db->phptype) {
            $seq_name .= '_id';
        }
        //remove existing PK sequence
        $sequences = $this->db->manager->listSequences();
        if (in_array($seq_name, $sequences)) {
            $this->db->manager->dropSequence($seq_name);
        }

        $fields = $this->fields;
        $fields['id']['autoincrement'] = true;
        $result = $this->db->manager->createTable($this->table, $fields);
        $this->assertFalse(PEAR::isError($result), 'Error creating table');
        $this->assertEquals(MDB2_OK, $result, 'Error creating table: unexpected return value');
        $query = 'INSERT INTO '.$this->db->quoteIdentifier($this->table, true);
        $query.= ' (somename, somedescription)';
        $query.= ' VALUES (:somename, :somedescription)';
        $stmt =& $this->db->prepare($query, array('text', 'text'), MDB2_PREPARE_MANIP);
        if (PEAR::isError($stmt)) {
            $this->fail('Preparing insert');
            return;
        }
        $values = array(
            'somename' => 'foo',
            'somedescription' => 'bar',
        );
        $rows = 5;
        for ($i =0; $i < $rows; ++$i) {
            $result = $stmt->execute($values);
            if (PEAR::isError($result)) {
                $this->fail('Error executing autoincrementing insert number: '.$i);
                return;
            }
        }
        $stmt->free();
        $query = 'SELECT id FROM '.$this->table;
        $data = $this->db->queryCol($query, 'integer');
        if (PEAR::isError($data)) {
            $this->fail('Error executing select: ' . $data->getMessage());
            return;
        }
        for ($i=0; $i<$rows; ++$i) {
            if (!isset($data[$i])) {
                $this->fail('Error in data returned by select');
                return;
            }
            if ($data[$i] !== ($i+1)) {
                $this->fail('Error executing autoincrementing insert');
                return;
            }
        }
    }

    /**
     *
     */
    function testListTableFields() {
        if (!$this->methodExists($this->db->manager, 'listTableFields')) {
            return;
        }
        $this->assertEquals(
            array_keys($this->fields),
            $this->db->manager->listTableFields($this->table),
            'Error creating table: incorrect fields'
        );
    }

    /**
     *
     */
    function testCreateIndex() {
        if (!$this->methodExists($this->db->manager, 'createIndex')) {
            return;
        }
        $index = array(
            'fields' => array(
                'somename' => array(
                    'sorting' => 'ascending',
                ),
            ),
        );
        $name = 'simpleindex';
        $result = $this->db->manager->createIndex($this->table, $name, $index);
        $this->assertFalse(PEAR::isError($result), 'Error creating index');
    }

    /**
     *
     */
    function testDropIndex() {
        if (!$this->methodExists($this->db->manager, 'dropIndex')) {
            return;
        }
        $index = array(
            'fields' => array(
                'somename' => array(
                    'sorting' => 'ascending',
                ),
            ),
        );
        $name = 'simpleindex';
        $result = $this->db->manager->createIndex($this->table, $name, $index);
        if (PEAR::isError($result)) {
            $this->fail('Error creating index');
        } else {
            $result = $this->db->manager->dropIndex($this->table, $name);
            $this->assertFalse(PEAR::isError($result), 'Error dropping index');
            $indices = $this->db->manager->listTableIndexes($this->table);
            $this->assertFalse(PEAR::isError($indices), 'Error listing indices');
            $this->assertFalse(in_array($name, $indices), 'Error dropping index');
        }
    }

    /**
     *
     */
    function testListIndexes() {
        if (!$this->methodExists($this->db->manager, 'listTableIndexes')) {
            return;
        }
        $index = array(
            'fields' => array(
                'somename' => array(
                    'sorting' => 'ascending',
                ),
            ),
        );
        $name = 'simpleindex';
        $result = $this->db->manager->createIndex($this->table, $name, $index);
        if (PEAR::isError($result)) {
            $this->fail('Error creating index');
        } else {
            $indices = $this->db->manager->listTableIndexes($this->table);
            $this->assertFalse(PEAR::isError($indices), 'Error listing indices');
            $this->assertTrue(in_array($name, $indices), 'Error listing indices');
        }
    }

    /**
     *
     */
    function testCreatePrimaryKey() {
        if (!$this->methodExists($this->db->manager, 'createConstraint')) {
            return;
        }
        $constraint = array(
            'fields' => array(
                'id' => array(
                    'sorting' => 'ascending',
                ),
            ),
            'primary' => true,
        );
        $name = 'pkindex';
        $result = $this->db->manager->createConstraint($this->table, $name, $constraint);
        $this->assertFalse(PEAR::isError($result), 'Error creating primary key constraint');
    }

    /**
     *
     */
    function testCreateUniqueConstraint() {
        if (!$this->methodExists($this->db->manager, 'createConstraint')) {
            return;
        }
        $constraint = array(
            'fields' => array(
                'somename' => array(
                    'sorting' => 'ascending',
                ),
            ),
            'unique' => true,
        );
        $name = 'uniqueindex';
        $result = $this->db->manager->createConstraint($this->table, $name, $constraint);
        $this->assertFalse(PEAR::isError($result), 'Error creating unique constraint');
    }

    /**
     *
     */
    function testCreateForeignKeyConstraint() {
        if (!$this->methodExists($this->db->manager, 'createConstraint')) {
            return;
        }
        $constraint = array(
            'fields' => array(
                'id' => array(
                    'sorting' => 'ascending',
                ),
            ),
            'foreign' => true,
            'references' => array(
                'table' => 'users',
                'fields' => array(
                    'user_id' => array(
                        'position' => 1,
                    ),
                ),
            ),
            'initiallydeferred' => false,
            'deferrable' => false,
            'match' => 'SIMPLE',
            'onupdate' => 'CASCADE',
            'ondelete' => 'CASCADE',
        );
        $constraint_name = 'fkconstraint';
        $result = $this->db->manager->createConstraint($this->table, $constraint_name, $constraint);
        $this->assertFalse(PEAR::isError($result), 'Error creating FOREIGN KEY constraint');

        //see if it was created successfully
        $constraints = $this->db->manager->listTableConstraints($this->table);
        $this->assertTrue(!PEAR::isError($constraints), 'Error listing table constraints');
        $constraint_name_idx = $this->db->getIndexName($constraint_name);
        $this->assertTrue(in_array($constraint_name_idx, $constraints) || in_array($constraint_name, $constraints), 'Error, FK constraint not found');
        
        //now check that it is enforced...

        //insert a row in the primary table
        $result = $this->db->exec('INSERT INTO users (user_id) VALUES (1)');
        $this->assertTrue(!PEAR::isError($result), 'Insert failed');

        //insert a row in the FK table with an id that references
        //the newly inserted row on the primary table: should not fail
        $query = 'INSERT INTO '.$this->db->quoteIdentifier($this->table, true)
                .' ('.$this->db->quoteIdentifier('id', true).') VALUES (1)';
        $result = $this->db->exec($query);
        $this->assertTrue(!PEAR::isError($result), 'Insert failed');

        //try to insert a row into the FK table with an id that does not
        //exist in the primary table: should fail
        $query = 'INSERT INTO '.$this->db->quoteIdentifier($this->table, true)
                .' ('.$this->db->quoteIdentifier('id', true).') VALUES (123456)';
        $this->db->pushErrorHandling(PEAR_ERROR_RETURN);
        $this->db->expectError('*');
        $result = $this->db->exec($query);
        $this->db->popExpect();
        $this->db->popErrorHandling();
        $this->assertTrue(PEAR::isError($result), 'Foreign Key constraint is not enforced for INSERT query');

        //try to update the first row of the FK table with an id that does not
        //exist in the primary table: should fail
        $query = 'UPDATE '.$this->db->quoteIdentifier($this->table, true)
                .' SET '.$this->db->quoteIdentifier('id', true).' = 123456 '
                .' WHERE '.$this->db->quoteIdentifier('id', true).' = 1';
        $this->db->expectError('*');
        $result = $this->db->exec($query);
        $this->db->popExpect();
        $this->assertTrue(PEAR::isError($result), 'Foreign Key constraint is not enforced for UPDATE query');

        $numrows_query = 'SELECT COUNT(*) FROM '. $this->db->quoteIdentifier($this->table, true);
        $numrows = $this->db->queryOne($numrows_query, 'integer');
        $this->assertEquals(1, $numrows, 'Invalid number of rows in the FK table');

        //update the PK value of the primary table: the new value should be
        //propagated to the FK table (ON UPDATE CASCADE)
        $result = $this->db->exec('UPDATE users SET user_id = 2');
        $this->assertTrue(!PEAR::isError($result), 'Update failed');

        $numrows = $this->db->queryOne($numrows_query, 'integer');
        $this->assertEquals(1, $numrows, 'Invalid number of rows in the FK table');

        $query = 'SELECT id FROM '.$this->db->quoteIdentifier($this->table, true);
        $newvalue = $this->db->queryOne($query, 'integer');
        $this->assertEquals(2, $newvalue, 'The value of the FK field was not updated (CASCADE failed)');

        //delete the row of the primary table: the row in the FK table should be
        //deleted automatically (ON DELETE CASCADE)
        $result = $this->db->exec('DELETE FROM users');
        $this->assertTrue(!PEAR::isError($result), 'Delete failed');

        $numrows = $this->db->queryOne($numrows_query, 'integer');
        $this->assertEquals(0, $numrows, 'Invalid number of rows in the FK table (CASCADE failed)');

        //cleanup
        $result = $this->db->manager->dropConstraint($this->table, $constraint_name);
        $this->assertTrue(!PEAR::isError($result), 'Error dropping the constraint');
    }

    /**
     *
     */
    function testDropPrimaryKey() {
        if (!$this->methodExists($this->db->manager, 'dropConstraint')) {
            return;
        }
        $index = array(
            'fields' => array(
                'id' => array(
                    'sorting' => 'ascending',
                ),
            ),
            'primary' => true,
        );
        $name = 'pkindex';
        $result = $this->db->manager->createConstraint($this->table, $name, $index);
        if (PEAR::isError($result)) {
            $this->fail('Error creating primary index');
        } else {
            $result = $this->db->manager->dropConstraint($this->table, $name, true);
            $this->assertFalse(PEAR::isError($result), 'Error dropping primary key index');
        }
    }

    /**
     *
     */
    function testListDatabases() {
        if (!$this->methodExists($this->db->manager, 'listDatabases')) {
            return;
        }
        $result = $this->db->manager->listDatabases();
        if (PEAR::isError($result)) {
            if ($result->getCode() == MDB2_ERROR_UNSUPPORTED) {
                $this->markTestSkipped('listDatabases() not supported');
            }
            $this->fail('Error listing databases ('.$result->getMessage().')');
        } else {
            $this->assertTrue(in_array(strtolower($this->database), $result), 'Error listing databases');
        }
    }

    /**
     *
     */
    function testListConstraints() {
        if (!$this->methodExists($this->db->manager, 'listTableConstraints')) {
            return;
        }
        $index = array(
            'fields' => array(
                'id' => array(
                    'sorting' => 'ascending',
                ),
            ),
            'unique' => true,
        );
        $name = 'uniqueindex';
        $result = $this->db->manager->createConstraint($this->table, $name, $index);
        if (PEAR::isError($result)) {
            $this->fail('Error creating unique constraint');
        } else {
            $constraints = $this->db->manager->listTableConstraints($this->table);
            $this->assertFalse(PEAR::isError($constraints), 'Error listing constraints');
            $this->assertTrue(in_array($name, $constraints), 'Error listing unique key index');
        }
    }

    /**
     *
     */
    function testListTables() {
        if (!$this->methodExists($this->db->manager, 'listTables')) {
            return;
        }
        $this->assertTrue($this->tableExists($this->table), 'Error listing tables');
    }

    /**
     *
     */
    function testAlterTable() {
        if (!$this->methodExists($this->db->manager, 'alterTable')) {
            return;
        }
        $newer = 'newertable';
        if ($this->tableExists($newer)) {
            $this->db->manager->dropTable($newer);
        }
        $changes = array(
            'add' => array(
                'quota' => array(
                    'type' => 'integer',
                    'unsigned' => 1,
                ),
                'note' => array(
                    'type' => 'text',
                    'length' => '20',
                ),
            ),
            'rename' => array(
                'sex' => array(
                    'name' => 'gender',
                    'definition' => array(
                        'type' => 'text',
                        'length' => 1,
                        'default' => 'M',
                    ),
                ),
            ),
            'change' => array(
                'id' => array(
                    'unsigned' => false,
                    'definition' => array(
                        'type'     => 'integer',
                        'notnull'  => false,
                        'default'  => 0,
                    ),
                ),
                'somename' => array(
                    'length' => '20',
                    'definition' => array(
                        'type' => 'text',
                        'length' => 20,
                    ),
                )
            ),
            'remove' => array(
                'somedescription' => array(),
            ),
            'name' => $newer,
        );

        $this->db->expectError(MDB2_ERROR_CANNOT_ALTER);
        $result = $this->db->manager->alterTable($this->table, $changes, true);
        $this->db->popExpect();
        if (PEAR::isError($result)) {
            $this->fail('Cannot alter table: '.$result->getMessage().' :: '.$result->getUserInfo());
        } else {
            $result = $this->db->manager->alterTable($this->table, $changes, false);
            if (PEAR::isError($result)) {
                $this->fail('Error altering table');
            } else {
                $this->db->manager->dropTable($newer);
            }
        }
    }

    /**
     *
     */
    function testAlterTable2() {
        if (!$this->methodExists($this->db->manager, 'alterTable')) {
            return;
        }
        $newer = 'newertable2';
        if ($this->tableExists($newer)) {
            $this->db->manager->dropTable($newer);
        }
        $changes_all = array(
            'add' => array(
                'quota' => array(
                    'type' => 'integer',
                    'unsigned' => 1,
                ),
            ),
            'rename' => array(
                'sex' => array(
                    'name' => 'gender',
                    'definition' => array(
                        'type' => 'text',
                        'length' => 1,
                        'default' => 'M',
                    ),
                ),
            ),
            'change' => array(
                'somename' => array(
                    'length' => '20',
                    'definition' => array(
                        'type' => 'text',
                        'length' => 20,
                    ),
                )
            ),
            'remove' => array(
                'somedescription' => array(),
            ),
            'name' => $newer,
        );

        foreach ($changes_all as $type => $change) {
            $changes = array($type => $change);
            $this->db->expectError(MDB2_ERROR_CANNOT_ALTER);
            $result = $this->db->manager->alterTable($this->table, $changes, true);
            $this->db->popExpect();
            if (PEAR::isError($result)) {
                $this->fail('Cannot alter table: '.$type);
                return;
            }
            $result = $this->db->manager->alterTable($this->table, $changes, false);
            if (PEAR::isError($result)) {
                $this->fail('Error altering table: '.$type);
            } else {
                switch ($type) {
                case 'add':
                    $altered_table_fields = $this->db->manager->listTableFields($this->table);
                    foreach ($change as $newfield => $dummy) {
                        $this->assertTrue(in_array($newfield, $altered_table_fields), 'Error: new field "'.$newfield.'" not added');
                    }
                    break;
                case 'rename':
                    $altered_table_fields = $this->db->manager->listTableFields($this->table);
                    foreach ($change as $oldfield => $newfield) {
                        $this->assertFalse(in_array($oldfield, $altered_table_fields), 'Error: field "'.$oldfield.'" not renamed');
                        $this->assertTrue(in_array($newfield['name'], $altered_table_fields), 'Error: field "'.$oldfield.'" not renamed correctly');
                    }
                    break;
                case 'change':
                    break;
                case 'remove':
                    $altered_table_fields = $this->db->manager->listTableFields($this->table);
                    foreach ($change as $newfield => $dummy) {
                        $this->assertFalse(in_array($newfield, $altered_table_fields), 'Error: field "'.$newfield.'" not removed');
                    }
                    break;
                case 'name':
                    if ($this->tableExists($newer)) {
                        $this->db->manager->dropTable($newer);
                    } else {
                        $this->fail('Error: table "'.$this->table.'" not renamed');
                    }
                    break;
                }
            }
        }
    }

    /**
     *
     */
    function testTruncateTable() {
        if (!$this->methodExists($this->db->manager, 'truncateTable')) {
            return;
        }

        $query = 'INSERT INTO '.$this->table;
        $query.= ' (id, somename, somedescription)';
        $query.= ' VALUES (:id, :somename, :somedescription)';
        $stmt =& $this->db->prepare($query, array('integer', 'text', 'text'), MDB2_PREPARE_MANIP);
        if (PEAR::isError($stmt)) {
            $this->fail('Error preparing INSERT');
            return;
        }
        $rows = 5;
        for ($i=1; $i<=$rows; ++$i) {
            $values = array(
                'id' => $i,
                'somename' => 'foo'.$i,
                'somedescription' => 'bar'.$i,
            );
            $result = $stmt->execute($values);
            if (PEAR::isError($result)) {
                $this->fail('Error executing insert number: '.$i);
                return;
            }
        }
        $stmt->free();
        $count = $this->db->queryOne('SELECT COUNT(*) FROM '.$this->table, 'integer');
        if (PEAR::isError($count)) {
            $this->fail('Error executing SELECT');
            return;
        }
        $this->assertEquals($rows, $count, 'Error: invalid number of rows returned');

        $result = $this->db->manager->truncateTable($this->table);
        if (PEAR::isError($result)) {
            $this->fail('Error truncating table');
        }

        $count = $this->db->queryOne('SELECT COUNT(*) FROM '.$this->table, 'integer');
        if (PEAR::isError($count)) {
            $this->fail('Error executing SELECT');
            return;
        }
        $this->assertEquals(0, $count, 'Error: invalid number of rows returned');
    }

    /**
     *
     */
    function testDropTable() {
        if (!$this->methodExists($this->db->manager, 'dropTable')) {
            return;
        }
        $result = $this->db->manager->dropTable($this->table);
        $this->assertFalse(PEAR::isError($result), 'Error dropping table');
    }

    /**
     *
     */
    function testListTablesNoTable() {
        if (!$this->methodExists($this->db->manager, 'listTables')) {
            return;
        }
        $result = $this->db->manager->dropTable($this->table);
        $this->assertFalse($this->tableExists($this->table), 'Error listing tables');
    }

    /**
     *
     */
    function testSequences() {
        if (!$this->methodExists($this->db->manager, 'createSequence')) {
            return;
        }
        $seq_name = 'testsequence';
        $result = $this->db->manager->createSequence($seq_name);
        $this->assertFalse(PEAR::isError($result), 'Error creating a sequence');
        $this->assertTrue(in_array($seq_name, $this->db->manager->listSequences()), 'Error listing sequences');
        $result = $this->db->manager->dropSequence($seq_name);
        $this->assertFalse(PEAR::isError($result), 'Error dropping a sequence');
        $this->assertFalse(in_array($seq_name, $this->db->manager->listSequences()), 'Error listing sequences');
    }

    /**
     * Test listTableTriggers($table)
     */
    function testListTableTriggers() {
        //setup
        $trigger_name = 'test_newtrigger';

        include_once 'MDB2_nonstandard.php';
        $nonstd =& MDB2_nonstandard::factory($this->db, $this);
        if (PEAR::isError($nonstd)) {
            $this->fail('Cannot instanciate MDB2_nonstandard object: '.$nonstd->getMessage());
            return;
        }

        $result = $nonstd->createTrigger($trigger_name, $this->table);
        if (PEAR::isError($result)) {
            $this->fail('Cannot create trigger: '.$result->getMessage());
            return;
        }

        //test
        $triggers = $this->db->manager->listTableTriggers($this->table);
        if (PEAR::isError($triggers)) {
            $this->fail('Error listing the table triggers: '.$triggers->getMessage());
        } else {
            $this->assertTrue(in_array($trigger_name, $triggers), 'Error: trigger not found');
            //check that only the triggers referencing the given table are returned
            $triggers = $this->db->manager->listTableTriggers('fake_table');
            $this->assertFalse(in_array($trigger_name, $triggers), 'Error: trigger found');
        }


        //cleanup
        $result = $nonstd->dropTrigger($trigger_name, $this->table);
        if (PEAR::isError($result)) {
            $this->fail('Error dropping the trigger: '.$result->getMessage());
        }
    }

    /**
     * Test listTableViews($table)
     */
    function testListTableViews() {
        //setup
        $view_name = 'test_newview';

        include_once 'MDB2_nonstandard.php';
        $nonstd =& MDB2_nonstandard::factory($this->db, $this);
        if (PEAR::isError($nonstd)) {
            $this->fail('Cannot instanciate MDB2_nonstandard object: '.$nonstd->getMessage());
            return;
        }

        $result = $nonstd->createView($view_name, $this->table);
        if (PEAR::isError($result)) {
            $this->fail('Cannot create view: '.$result->getMessage());
            return;
        }

        //test
        $views = $this->db->manager->listTableViews($this->table);
        if (PEAR::isError($views)) {
            if ($views->getCode() == MDB2_ERROR_UNSUPPORTED) {
                $this->markTestSkipped('listDatabases() not supported');
            }
            $this->fail('Error listing the table views: '.$views->getMessage());
        } else {
            $this->assertTrue(in_array($view_name, $views), 'Error: view not found');
            //check that only the views referencing the given table are returned
            $views = $this->db->manager->listTableViews('fake_table');
            $this->assertFalse(in_array($view_name, $views), 'Error: view found');
        }


        //cleanup
        $result = $nonstd->dropView($view_name);
        if (PEAR::isError($result)) {
            $this->fail('Error dropping the view: '.$result->getMessage());
        }
    }

    /**
     * Test listViews()
     */
    function testListViews() {
        //setup
        $view_name = 'test_brandnewview';

        include_once 'MDB2_nonstandard.php';
        $nonstd =& MDB2_nonstandard::factory($this->db, $this);
        if (PEAR::isError($nonstd)) {
            $this->fail('Cannot instanciate MDB2_nonstandard object: '.$nonstd->getMessage());
            return;
        }

        $result = $nonstd->createView($view_name, $this->table);
        if (PEAR::isError($result)) {
            $this->fail('Cannot create view: '.$result->getMessage());
            return;
        }

        //test
        $views = $this->db->manager->listViews();
        if (PEAR::isError($views)) {
            $this->fail('Error listing the views: '.$views->getMessage());
        } else {
            $this->assertTrue(in_array($view_name, $views), 'Error: view not found');
        }

        //cleanup
        $result = $nonstd->dropView($view_name);
        if (PEAR::isError($result)) {
            $this->fail('Error dropping the view: '.$result->getMessage());
        }
    }

    /**
     * Test listUsers()
     */
    function testListUsers() {
        $users = $this->db->manager->listUsers();
        if (PEAR::isError($users)) {
            if ($users->getCode() == MDB2_ERROR_UNSUPPORTED) {
                $this->markTestSkipped('listUsers() not supported');
            }
            $this->fail('Error listing the users: '.$users->getMessage().' :: '.$users->getUserInfo());
        } else {
            $users = array_map('strtolower', $users);
            $this->assertTrue(in_array(strtolower($this->db->dsn['username']), $users), 'Error: user not found');
        }
    }

    /**
     * Test listFunctions()
     */
    function testListFunctions() {
        //setup
        $function_name = 'test_add';

        include_once 'MDB2_nonstandard.php';
        $nonstd =& MDB2_nonstandard::factory($this->db, $this);
        if (PEAR::isError($nonstd)) {
            $this->fail('Cannot instanciate MDB2_nonstandard object: '.$nonstd->getMessage());
            return;
        }

        $this->db->pushErrorHandling(PEAR_ERROR_RETURN);
        $this->db->expectError('*');
        $result = $nonstd->createFunction($function_name);
        $this->db->popExpect();
        $this->db->popErrorHandling();
        if (PEAR::isError($result)) {
            if ($result->getCode() == MDB2_ERROR_NOT_CAPABLE) {
                $this->markTestSkipped('createFunction() not supported');
            }
            $this->fail('Cannot create function: '.$result->getMessage().' :: '.$result->getUserInfo());
            return;
        }

        //test
        $functions = $this->db->manager->listFunctions();
        if (PEAR::isError($functions)) {
            $this->fail('Error listing the functions: '.$functions->getMessage());
        } else {
            $this->assertTrue(in_array($function_name, $functions), 'Error: function not found');
        }

        //cleanup
        $result = $nonstd->dropFunction($function_name);
        if (PEAR::isError($result)) {
            $this->fail('Error dropping the function: '.$result->getMessage());
        }
    }
    
    /**
     * Test createDatabase(), alterDatabase(), dropDatabase()
     */
    function testCrudDatabase() {
        $name = 'newdb';
        $options = array(
            'charset' => 'UTF8',
            'collation' => 'utf8_bin',
        );
        $changes = array(
            'name' => 'newdbname',
            'charset' => 'UTF8',
        );
        if ('pgsql' == substr($this->db->phptype, 0, 5)) {
            $options['charset'] = 'WIN1252';
        }
        if ('mssql' == substr($this->db->phptype, 0, 5)) {
            $options['collation'] = 'WIN1252';
            $options['collation'] = 'Latin1_General_BIN';
        }
        $result = $this->db->manager->createDatabase($name, $options);
        if (PEAR::isError($result)) {
            //echo '<pre>'; print_r($result); echo '</pre>';
            $this->fail('Error: cannot create database: ' . $result->getUserInfo());
            return;
        }
        $result = $this->db->manager->alterDatabase($name, $changes);
        if (PEAR::isError($result)) {
            echo '<pre>'; print_r($result); echo '</pre>';
            $this->fail('Error: cannot alter database');
            return;
        }
        $dbs = $this->db->manager->listDatabases();
        //echo '<pre>'; print_r($dbs); echo '</pre>';
        if (in_array($changes['name'], $dbs)) {
            $result = $this->db->manager->dropDatabase($changes['name']);
        } else {
            $this->fail('Error: database not renamed');
            $result = $this->db->manager->dropDatabase($name);
        }
        if (PEAR::isError($result)) {
            $this->fail('Error dropping database: '.$result->getMessage());
        }
        //echo '<pre>'; print_r($result); echo '</pre>';
    }
    
    /**
     * Test vacuum
     */
    function testVacuum() {
        //vacuum table
        $result = $this->db->manager->vacuum($this->table);
        if (PEAR::isError($result)) {
            $this->fail('Error: cannot vacuum table: ' . $result->getMessage());
        }

        //vacuum and analyze table
        $options = array(
            'analyze' => true,
            'full'    => true,
            'freeze'  => true,
        );
        $result = $this->db->manager->vacuum($this->table, $options);
        if (PEAR::isError($result)) {
            $this->fail('Error: cannot vacuum table: ' . $result->getMessage());
        }

        //vacuum all tables
        $result = $this->db->manager->vacuum();
        if (PEAR::isError($result)) {
            $this->fail('Error: cannot vacuum table: ' . $result->getMessage());
        }
    }
}
?>