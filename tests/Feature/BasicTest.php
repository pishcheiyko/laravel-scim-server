<?php

namespace UniqKey\Laravel\SCIMServer\Tests\Feature;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
    }
}

/*
use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicTest extends TestCase {
    
    protected $baseUrl = 'http://localhost';
    
    use RefreshDatabase;
    
    
    
    public function setUp()
    {
        parent::setUp();
        
        $this->loadLaravelMigrations('testbench');
        
        $this->withFactories(realpath(dirname(__DIR__).'/database/factories'));
        
        \UniqKey\Laravel\SCIMServer\Providers\RouteProvider::routes();;
        
        factory(\UniqKey\Laravel\SCIMServer\Tests\Model\User::class, 100)->create();
        
    }

    protected function getEnvironmentSetUp($app) {

        
        $app ['config']->set ( 'app.url','http://localhost');;
                
        // Setup default database to use sqlite :memory:
        
        $app['config']->set('scimserver.Users.class', \UniqKey\Laravel\SCIMServer\Tests\Model\User::class);
        
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

    }
    
    public function testGet() {
        
        $response = $this->get('/scim/v2/Users');
       
        $response->assertStatus(200);
        
    }

*/
