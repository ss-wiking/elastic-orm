<?php

namespace SsWiking\ElasticOrm\Tests;

use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use SsWiking\ElasticOrm\Config;

class ConfigTest extends TestCase
{
    private Config $config;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $mock = $this->createMock(Repository::class);
        $mock->method('get')->with('elastic-orm')->willReturn([
            'hosts' => ['localhost:9200'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $this->config = new Config($mock);
    }

    public function testPassword(): void
    {
        $this->assertEquals('pass', $this->config->password());
    }

    public function testHosts(): void
    {
        $this->assertEquals(['localhost:9200'], $this->config->hosts());
    }

    public function testUsername(): void
    {
        $this->assertEquals('user', $this->config->username());
    }
}
