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
 * Class IpbGroupTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Ipb\Tests
 */
class IpbGroupTest extends TestCase
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

    public function testGetId()
    {
        $artifact = '10';

        $set = new IpbGroup([
            'id'   => $artifact,
            'name' => 'TEST GROUP',
        ]);

        $this->assertEquals($artifact, $set->getId());
    }

    public function testGetName()
    {
        $artifact = 'TEST GROUP';

        $set = new IpbGroup([
            'id'   => '10',
            'name' => $artifact,
        ]);

        $this->assertEquals($artifact, $set->getName());
    }

    public function testAddMember()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
            ],
        ]);

        $group = new IpbGroup([
            'id'   => '11',
            'name' => 'Another Test Group',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => 'test@example.com',
            ],
        ]);

        $group->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());

        $group->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());
    }

    public function testAddMemberGuzzleException()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(400),
            ],
        ]);

        $group = new IpbGroup([
            'id'   => '11',
            'name' => 'Another Test Group',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to add user Member 2 as a member of set TEST ROLE.');

        $role->addMember($user);
    }

    public function testRemoveMember()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
                new Response(204),
            ],
        ]);

        $group = new IpbGroup([
            'id'   => '11',
            'name' => 'Another Test Group',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => 'test@example.com',
            ],
        ]);

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());

        $role->removeMember($user);
        $this->assertEmpty($role->getMembers());

        $role->removeMember($user);
        $this->assertEmpty($role->getMembers());
    }

    public function testRemoveMemberGuzzleException()
    {
        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
                new Response(400),
            ],
        ]);

        $group = new IpbGroup([
            'id'   => '41771983423143937',
            'name' => 'TEST ROLE',
        ]);

        $user = new IpbMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => 'test@example.com',
            ],
        ]);

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to remove user Member 2 from set TEST ROLE.');

        $role->removeMember($user);
    }
}
