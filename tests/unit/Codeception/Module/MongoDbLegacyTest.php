<?php

declare(strict_types=1);

use Codeception\Lib\ModuleContainer;
use Codeception\Module\MongoDb;
use Codeception\PHPUnit\TestCase;
use Codeception\Util\Stub;
use PHPUnit\Framework\ExpectationFailedException;

final class MongoDbLegacyTest extends TestCase
{
    /**
     * @var array
     */
    private $mongoConfig = [
        'dsn' => 'mongodb://localhost:27017/test'
    ];

    /**
     * @var MongoDb
     */
    protected $module;

    /**
     * @var \MongoDb
     */
    protected $db;

    /**
     * @var MongoCollection
     */
    private $userCollection;

    protected function _setUp()
    {
        if (!class_exists('Mongo')) {
            $this->markTestSkipped('Mongo is not installed');
        }
        if (!class_exists('MongoDB\Client')) {
            $this->markTestSkipped('MongoDb\Client is not installed');
        }

        $mongoClient = new MongoClient();

        $container = Stub::make(ModuleContainer::class);
        $this->module = new MongoDb($container);
        $this->module->_setConfig($this->mongoConfig);
        $this->module->_initialize();

        $this->db = $mongoClient->selectDB('test');
        $this->userCollection = $this->db->createCollection('users');
        $this->userCollection->insert(['id' => 1, 'email' => 'miles@davis.com']);
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
        $this->assertEquals('miles@davis.com', $user['email']);
    }

    public function testSeeNumElementsInCollection()
    {
        $this->module->seeNumElementsInCollection('users', 1);
        $this->module->seeNumElementsInCollection('users', 1, ['email' => 'miles@davis.com']);
        $this->module->seeNumElementsInCollection('users', 0, ['name' => 'Doe']);
    }

    public function testGrabCollectionCount()
    {
        $this->userCollection->insert(['id' => 2, 'email' => 'louis@armstrong.com']);
        $this->userCollection->insert(['id' => 3, 'email' => 'dizzy@gillespie.com']);

        $this->assertEquals(1, $this->module->grabCollectionCount('users', ['id' => 3]));
        $this->assertEquals(3, $this->module->grabCollectionCount('users'));
    }

    public function testSeeElementIsArray()
    {
        $this->userCollection->insert(['id' => 4, 'trumpets' => ['piccolo', 'bass', 'slide']]);

        $this->module->seeElementIsArray('users', ['id' => 4], 'trumpets');
    }


    public function testSeeElementIsArrayThrowsError()
    {
        $this->expectException(ExpectationFailedException::class);

        $this->userCollection->insert(['id' => 5, 'trumpets' => ['piccolo', 'bass', 'slide']]);
        $this->userCollection->insert(['id' => 6, 'trumpets' => ['piccolo', 'bass', 'slide']]);
        
        $this->module->seeElementIsArray('users', [], 'trumpets');
    }

    public function testSeeElementIsObject()
    {
        $trumpet = new StdClass;

        $trumpet->name = 'Trumpet 1';
        $trumpet->pitch = 'B♭';
        $trumpet->price = ['min' => 458, 'max' => 891];

        $this->userCollection->insert(['id' => 6, 'trumpet' => $trumpet]);

        $this->module->seeElementIsObject('users', ['id' => 6], 'trumpet');
    }

    public function testSeeElementIsObjectThrowsError()
    {
        $trumpet = new StdClass;

        $trumpet->name = 'Trumpet 1';
        $trumpet->pitch = 'B♭';
        $trumpet->price = ['min' => 458, 'max' => 89];

        $this->expectException(ExpectationFailedException::class);

        $this->userCollection->insert(['id' => 5, 'trumpet' => $trumpet]);
        $this->userCollection->insert(['id' => 6, 'trumpet' => $trumpet]);

        $this->module->seeElementIsObject('users', [], 'trumpet');
    }

    public function testUseDatabase()
    {
        $this->module->useDatabase('example');
        $this->module->haveInCollection('stuff', ['name' => 'Ashley', 'email' => 'me@ashleyclarke.me']);
        $this->module->seeInCollection('stuff', ['name' => 'Ashley', 'email' => 'me@ashleyclarke.me']);
        $this->module->dontSeeInCollection('users', ['email' => 'miles@davis.com']);
    }
}
