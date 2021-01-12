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
use Anza\Seat\Connector\Drivers\Ipb\Driver\IpbMember;
use Anza\Seat\Connector\Drivers\Ipb\Driver\IpbGroup;
use Anza\Seat\Connector\Drivers\Ipb\Tests\Fetchers\TestFetcher;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;

/**
 * Class IpbClientTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Ipb\Tests
 */
class IpbClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');
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

    protected function tearDown(): void
    {
        IpbClient::tearDown();

        parent::tearDown();
    }

    public function testGetAllUsers()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
            ],
        ]);

        $driver = IpbClient::getInstance();

        $artifact = [
            '10' => new IpbMember([
                'nick' => 'Member 1',
                'groups' => [],
                'user' => [
                    'id' => '10',
                    'username' => 'Nelly',
                ],
            ]),
            '11' => new IpbMember([
                'nick' => 'Member 2',
                'groups' => [],
                'user' => [
                    'id' => '11',
                    'username' => 'Mike',
                ],
            ]),
            '12' => new IpbMember([
                'nick' => 'Member 3',
                'groups' => [],
                'user' => [
                    'id' => '12',
                    'username' => 'Clarke',
                ],
            ]),
        ];

        $this->assertEquals($artifact, $driver->getUsers());
    }

    public function testGetExistingSingleUser()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/member.json')),
                new Response(200, [], '[]'),
            ],
        ]);

        $user_id = '11';
        $artifact = new IpbMember([
            'nick' => 'Member 2',
            'groups' => [],
            'user' => [
                'id' => $user_id,
                'username' => 'Mike',
            ],
        ]);

        $driver = IpbClient::getInstance();
        $driver->getUsers();

        $this->assertEquals($artifact, $driver->getUser($user_id));
    }

    public function testGetMissingSingleUser()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/member.json')),
            ],
        ]);

        $user_id = '24';
        $artifact = new IpbMember([
            'nick' => 'Member 4',
            'groups' => [],
            'user' => [
                'id' => $user_id,
                'username' => 'Jocelyn',
            ],
        ]);

        $driver = IpbClient::getInstance();

        $this->assertEquals($artifact, $driver->getUser($user_id));
    }

    public function testGetAllSets()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/groups.json')),
            ],
        ]);

        $artifact = [
            10 => new IpbGroup([
                'id'   => '10',
                'name' => 'TEST GROUP',
            ]),
            11 => new IpbGroup([
                'id'   => '11',
                'name' => 'Another Test Group',
            ]),
            12 => new IpbGroup([
                'id'   => '12',
                'name' => 'Yet another test group',
            ]),
        ];

        $driver = IpbClient::getInstance();

        $this->assertEquals($artifact, $driver->getSets());
    }

    public function testGetSingleSet()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/groups.json')),
            ],
        ]);

        $set_id = '10';
        $artifact = new IpbGroup([
            'id'   => $set_id,
            'name' => 'TEST GROUP',
        ]);

        $driver = IpbClient::getInstance();

        $this->assertEquals($artifact, $driver->getSet($set_id));
    }
}
