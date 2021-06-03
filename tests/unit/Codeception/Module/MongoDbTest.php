<?php

declare(strict_types=1);

use Codeception\Lib\ModuleContainer;
use Codeception\Module\MongoDb;
use Codeception\Exception\ModuleException;
use Codeception\Test\Unit;
use Codeception\Stub;
use PHPUnit\Framework\ExpectationFailedException;

final class MongoDbTest extends Unit
{
    /**
     * @var array
     */
    private $mongoConfig = [
        'dsn' => 'mongodb://localhost:27017/test?connectTimeoutMS=300',
        'dump' => 'tests/data/dumps/mongo.js',
        'populate' => true
    ];

    /**
     * @var MongoDb
     */
    protected $module;

    /**
     * @var \MongoDB\Database
     */
    protected $db;

    /**
     * @var \MongoDB\Collection
     */
    private $userCollection;

    protected function _setUp()
    {
        if (!class_exists('\MongoDB\Client')) {
            $this->markTestSkipped('MongoDB is not installed');
        }

        $cleanupDirty = in_array('cleanup-dirty', $this->getGroups());
        $config = $this->mongoConfig + ['cleanup' => $cleanupDirty ? 'dirty' : true];

        $client = new \MongoDB\Client();

        $container = Stub::make(ModuleContainer::class);
        $this->module = new MongoDb($container);
        $this->module->_setConfig($config);
        try {
            $this->module->_initialize();
        } catch (ModuleException $moduleException) {
            $this->markTestSkipped($moduleException->getMessage());
        }

        $this->db = $client->selectDatabase('test');
        $this->userCollection = $this->db->users;

        if (!$cleanupDirty) {
            $this->userCollection->insertOne(['id' => 1, 'email' => 'miles@davis.com']);
        }
    }

    protected function _tearDown()
    {
        if (!is_null($this->userCollection)) {
            $this->userCollection->drop();
        }
    }

    public function testSeeInCollection()
    {
        $this->module->seeInCollection('users', ['email' => 'miles@davis.com']);
    }

    public function testDontSeeInCollection()
    {
        $this->module->dontSeeInCollection('users', ['email' => 'davert@davert.com']);
    }

    public function testHaveAndSeeInCollection()
    {
        $this->module->haveInCollection('users', ['name' => 'John', 'email' => 'john@coltrane.com']);
        $this->module->seeInCollection('users', ['name' => 'John', 'email' => 'john@coltrane.com']);
    }

    public function testGrabFromCollection()
    {
        $user = $this->module->grabFromCollection('users', ['id' => 1]);
        $this->assertArrayHasKey('email', $user);
        $this->assertSame('miles@davis.com', $user['email']);
    }

    public function testSeeNumElementsInCollection()
    {
        $this->module->seeNumElementsInCollection('users', 1);
        $this->module->seeNumElementsInCollection('users', 1, ['email' => 'miles@davis.com']);
        $this->module->seeNumElementsInCollection('users', 0, ['name' => 'Doe']);
    }

    public function testGrabCollectionCount()
    {
        $this->userCollection->insertOne(['id' => 2, 'email' => 'louis@armstrong.com']);
        $this->userCollection->insertOne(['id' => 3, 'email' => 'dizzy@gillespie.com']);

        $this->assertSame(1, $this->module->grabCollectionCount('users', ['id' => 3]));
        $this->assertSame(3, $this->module->grabCollectionCount('users'));
    }

    public function testSeeElementIsArray()
    {
        $this->userCollection->insertOne(['id' => 4, 'trumpets' => ['piccolo', 'bass', 'slide']]);

        $this->module->seeElementIsArray('users', ['id' => 4], 'trumpets');
    }


    public function testSeeElementIsArrayThrowsError()
    {
        $this->expectException(ExpectationFailedException::class);

        $this->userCollection->insertOne(['id' => 5, 'trumpets' => ['piccolo', 'bass', 'slide']]);
        $this->userCollection->insertOne(['id' => 6, 'trumpets' => ['piccolo', 'bass', 'slide']]);
        
        $this->module->seeElementIsArray('users', [], 'trumpets');
    }

    public function testSeeElementIsObject()
    {
        $trumpet = new StdClass;

        $trumpet->name = 'Trumpet 1';
        $trumpet->pitch = 'B♭';
        $trumpet->price = ['min' => 458, 'max' => 891];

        $this->userCollection->insertOne(['id' => 6, 'trumpet' => $trumpet]);

        $this->module->seeElementIsObject('users', ['id' => 6], 'trumpet');
    }

    public function testSeeElementIsObjectThrowsError()
    {
        $trumpet = new StdClass;

        $trumpet->name = 'Trumpet 1';
        $trumpet->pitch = 'B♭';
        $trumpet->price = ['min' => 458, 'max' => 891];

        $this->expectException(ExpectationFailedException::class);

        $this->userCollection->insertOne(['id' => 5, 'trumpet' => $trumpet]);
        $this->userCollection->insertOne(['id' => 6, 'trumpet' => $trumpet]);

        $this->module->seeElementIsObject('users', [], 'trumpet');
    }

    public function testUseDatabase()
    {
        $this->module->useDatabase('example');
        $this->module->haveInCollection('stuff', ['name' => 'Ashley', 'email' => 'me@ashleyclarke.me']);
        $this->module->seeInCollection('stuff', ['name' => 'Ashley', 'email' => 'me@ashleyclarke.me']);
        $this->module->dontSeeInCollection('users', ['email' => 'miles@davis.com']);
    }

    public function testLoadDump()
    {
        $testRecords = [
            ['name' => 'Michael Jordan', 'position' => 'sg'],
            ['name' => 'Ron Harper','position' => 'pg'],
            ['name' => 'Steve Kerr','position' => 'pg'],
            ['name' => 'Toni Kukoc','position' => 'sf'],
            ['name' => 'Luc Longley','position' => 'c'],
            ['name' => 'Scottie Pippen','position' => 'sf'],
            ['name' => 'Dennis Rodman','position' => 'pf']
        ];

        foreach ($testRecords as $testRecord) {
            $this->module->haveInCollection('96_bulls', $testRecord);
        }
    }

    /**
     * @group cleanup-dirty
     */
    public function testCleanupDirty()
    {
        $test = $this->createMock(\Codeception\TestInterface::class);
        $collection = $this->db->selectCollection('96_bulls');

        $hash1 = $this->module->driver->getDbHash();
        $this->module->seeNumElementsInCollection('96_bulls', 7);
        $this->assertSame($hash1, $this->module->driver->getDbHash());

        $this->module->_after($test);

        $this->module->_before($test); // No cleanup expected

        $this->assertSame($hash1, $this->module->driver->getDbHash());
        $collection->insertOne(['name' => 'Coby White','position' => 'pg']);

        $hashDirty = $this->module->driver->getDbHash();
        $this->assertNotSame($hash1, $hashDirty);

        $this->module->_after($test);

        $this->module->_before($test); // Cleanup expected
        $this->module->seeNumElementsInCollection('96_bulls', 7);

        $hash2 = $this->module->driver->getDbHash();

        $this->assertNotSame($hash1, $hash2);
        $this->assertNotSame($hashDirty, $hash2);

        $this->module->_after($test);

        $this->module->_before($test); // No cleanup expected

        $this->assertSame($hash2, $this->module->driver->getDbHash());
    }
}
