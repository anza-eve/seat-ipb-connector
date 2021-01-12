<?php
/**
 * This file is part of SeAT IPB Connector.
 *
 * Copyright (C) 2021  Ben Thompson <ben.thompson002@gmail.com>
 *
 * SeAT IPB Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT IPB Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Anza\Seat\Connector\Drivers\Ipb\Tests;

use Anza\Seat\Connector\Drivers\Ipb\Driver\IpbClient;
use Anza\Seat\Connector\Drivers\Ipb\Driver\IpbGroup;
use Anza\Seat\Connector\Drivers\Ipb\Driver\IpbMember;
use Anza\Seat\Connector\Drivers\Ipb\Tests\Fetchers\TestFetcher;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class IpbMemberTest.
 *
 * @package Anza\Seat\Connector\Drivers\Ipb\Tests
 */
class IpbMemberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);
    }

    protected function tearDown(): void
    {
        IpbClient::tearDown();

        parent::tearDown();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('ipb-connector.config.fetcher', TestFetcher::class);
        $app['config']->set('ipb-connector.config.version', 'dev');
    }

    public function testGetClientId()
    {
        $artifact = '80351110224678913';

        $user = new IpbMember([
            'nick' => 'Member 2',
            'groups' => [],
            'user' => [
                'id' => $artifact,
                'username' => 'Mike',
            ],
        ]);

        $this->assertEquals($artifact, $user->getClientId());
    }

    public function testGetUniqueId()
    {
        $artifact = '80351110224678913';

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => $artifact
            ],
        ]);

        $this->assertEquals($artifact, $user->getUniqueId());
    }

    public function testGetName()
    {
        $artifact = 'Member 2';

        $user = new IpbMember([
            'nick' => $artifact,
            'groups' => [],
            'user' => [
                'id' => '80351110224678913',
                'username' => 'Mike',
            ],
        ]);

        $this->assertEquals($artifact, $user->getName());
    }

    public function testGetSets()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/roles.json')),
            ],
        ]);

        $artifact = new IpbGroup([
            'id' => '41771983423143939',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new IpbMember([
            'nick' => 'Jeremy',
            'roles' => [
                '41771983423143939',
            ],
            'user' => [
                'id' => '9687651657897975421',
                'username' => 'Jeremy',
            ],
        ]);

        $this->assertEquals([$artifact->getId() => $artifact], $user->getSets());
    }

    public function testSetName()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(204),
            ],
        ]);

        $artifact = 'Mike';

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => $artifact,
                'email'    => 'test@example.com',
            ],
        ]);

        $user->setName('Georges');

        $this->assertNotEquals($artifact, $user->getName());
    }

    public function testSetNameGuzzleException()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to change user name from Member 2 to Georges.');

        $user->setName('Georges');
    }

    public function testAddSet()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(204),
            ],
        ]);

        $group = new IpbGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 5',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());
    }

    public function testAddSetGuzzleException()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $group = new IpbGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 5',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to add set ANOTHER ROLE to the user Member 5.');

        $user->addSet($role);
    }

    public function testRemoveSet()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(204),
                new Response(204),
            ],
        ]);

        $role = new IpbGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 5',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());

        $user->removeSet($group);
        $this->assertEmpty($user->getSets());

        $user->removeSet($group);
        $this->assertEmpty($user->getSets());
    }

    public function testRemoveSetGuzzleException()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(204),
                new Response(400),
            ],
        ]);

        $role = new IpbGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new IpbMMember([
            'nick'  => 'Member 5',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to remove set ANOTHER ROLE from the user Member 5.');

        $user->removeSet($role);
    }
}
