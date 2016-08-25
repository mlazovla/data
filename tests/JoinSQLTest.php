<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class JoinSQLTest extends SQLTestCase
{
    public function testDirection()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');

        $j = $m->join('contact');
        $this->assertEquals(false, $this->getProtected($j, 'reverse'));
        $this->assertEquals('contact_id', $this->getProtected($j, 'master_field'));
        $this->assertEquals('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact2.test_id');
        $this->assertEquals(true, $this->getProtected($j, 'reverse'));
        $this->assertEquals('id', $this->getProtected($j, 'master_field'));
        $this->assertEquals('test_id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact3', 'test_id');
        $this->assertEquals(false, $this->getProtected($j, 'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j, 'master_field'));
        $this->assertEquals('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact3', ['test_id']);
        $this->assertEquals(false, $this->getProtected($j, 'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j, 'master_field'));
        $this->assertEquals('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact4.foo_id', ['test_id', 'reverse' => true]);
        $this->assertEquals(true, $this->getProtected($j, 'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j, 'master_field'));
        $this->assertEquals('foo_id', $this->getProtected($j, 'foreign_field'));
    }

    /**
     * @expectedException Exception
     */
    public function testDirection2()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $j = $m->join('contact4.foo_id', 'test_id');
        $this->assertEquals(true, $this->getProtected($j, 'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j, 'master_field'));
        $this->assertEquals('foo_id', $this->getProtected($j, 'foreign_field'));
    }

    public function testJoinSaving1()
    {
        $a = [
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123'],
            ], ];

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDB($a);

        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u['name'] = 'John';
        $m_u['contact_phone'] = '+123';

        $m_u->save();

        $m_u->unload();

        $this->assertEquals([
            'user'    => [1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1]],
            'contact' => [1 => ['id' => 1, 'contact_phone' => '+123']],
        ], $this->getDB('user,contact'));

        $m_u['name'] = 'Joe';
        $m_u['contact_phone'] = '+321';
        $m_u->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDB('user,contact'));
    }

    public function testJoinSaving2()
    {
        $a = [
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ], ];

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDB($a);
        $m_u->addField('name');
        $j = $m_u->join('contact.test_id');
        $j->addField('contact_phone');

        $m_u['name'] = 'John';
        $m_u['contact_phone'] = '+123';

        $m_u->save();

        $this->assertEquals([
            'user'    => [1 => ['id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getDB('user,contact'));

        $m_u->unload();
        $m_u['name'] = 'Peter';
        $m_u->save();
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
            ], 'contact' => [
                1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'test_id' => 2, 'contact_phone' => null],
            ],
        ], $this->getDB('user,contact'));

        $this->db->connection->dsql()->table('contact')->where('id', 2)->delete();
        $m_u->unload();
        $m_u['name'] = 'Sue';
        $m_u['contact_phone'] = '+444';
        $m_u->save();
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Sue'],
            ], 'contact' => [
                1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123'],
                3 => ['id' => 3, 'test_id' => 3, 'contact_phone' => '+444'],
            ],
        ], $this->getDB('user,contact'));
    }

    public function testJoinSaving3()
    {
        $a = [
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'test_id' => 0],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123'],
            ], ];

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDB($a);

        $m_u->addField('name');
        $j = $m_u->join('contact', 'test_id');
        $j->addField('contact_phone');

        $m_u['name'] = 'John';
        $m_u['contact_phone'] = '+123';

        $m_u->save();

        $this->assertEquals([
            'user'    => [1 => ['id' => 1, 'test_id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'contact_phone' => '+123']],
        ], $this->getDB('user,contact'));
    }

    public function testJoinLoading()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ], ];

        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_id', ['actual' => 'id']);
        $j->addField('contact_phone');

        $m_u->load(1);

        $this->assertEquals([
            'name' => 'John', 'contact_id' => 1, 'contact_phone' => '+123', 'id' => 1,
        ], $m_u->get());

        $m_u->load(3);
        $this->assertEquals([
            'name' => 'Joe', 'contact_id' => 2, 'contact_phone' => '+321', 'id' => 3,
        ], $m_u->get());

        $m_u->tryLoad(4);
        $this->assertEquals([
            'name' => null, 'contact_id' => null, 'contact_phone' => null, 'id' => null,
        ], $m_u->get());
    }

    public function testJoinUpdate()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ], ];

        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u->load(1);
        $m_u['name'] = 'John 2';
        $m_u['contact_phone'] = '+555';
        $m_u->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ], ], $this->getDB()
        );

        $m_u->load(1);
        $m_u['name'] = 'XX';
        $m_u['contact_phone'] = '+999';
        $m_u->load(3);
        $m_u['name'] = 'XX';
        $m_u->save();


        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ], ], $this->getDB()
        );

        $m_u['contact_phone'] = '+999';
        $m_u->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
            ], ], $this->getDB()
        );

        $m_u->tryLoad(4);
        $m_u['name'] = 'YYY';
        $m_u['contact_phone'] = '+777';
        $m_u->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ], ], $this->getDB()
        );
    }

    public function testJoinDelete()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u->load(1);
        $m_u->delete();

        $this->assertEquals([
            'user' => [
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ], 'contact' => [
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ], ], $this->getDB()
        );
    }

    public function testDoubleSaveHook()
    {
        $a = [
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ], ];

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDB($a);
        $m_u->addField('name');
        $j = $m_u->join('contact.test_id');
        $j->addField('contact_phone');

        $m_u->addHook('afterSave', function ($m) {
            if ($m['contact_phone'] != '+123') {
                $m['contact_phone'] = '+123';
                $m->save();
            }
        });

        $m_u['name'] = 'John';
        $m_u->save();

        $this->assertEquals([
            'user'    => [1 => ['id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getDB('user,contact'));
    }
}