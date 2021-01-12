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
use Anza\Seat\Connector\Drivers\Ipb\Tests\Fetchers\TestFetcher;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class Test.
 *
 * @package Anza\Seat\Connector\Drivers\Ipb\Tests
 */
class DriverExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

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

    public function testUnsetSettingsDriverException()
    {
        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('The Driver has not been configured yet.');

        IpbClient::getInstance();
    }

    public function testCommunityUrlDriverSettingException()
    {
        setting([
            'seat-connector.drivers.ipb', (object) [
                'community_url' => null,
            ],
        ], true);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Parameter community_url is missing.');

        IpbClient::getInstance();
    }

    public function testApiKeyDriverSettingException()
    {
        setting([
            'seat-connector.drivers.ipb', (object) [
                'api_key' => null,
            ],
        ], true);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Parameter api_key is missing.');

        IpbClient::getInstance();
    }

    public function testGetSetsException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(403),
            ],
        ]);

        $this->expectException(DriverException::class);

        IpbClient::getInstance()->getSets();
    }

    public function testGetSingleSetException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(404),
            ],
        ]);

        $this->expectException(DriverException::class);

        IpbClient::getInstance()->getSet('10');
    }

    public function testGetUsersGuzzleException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $this->expectException(DriverException::class);

        IpbClient::getInstance()->getUsers();
    }

    public function testGetSingleUserDriverSettingsException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(404),
            ],
        ]);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Configured community_url is invalid.');

        IpbClient::getInstance()->getUser('3');
    }

    public function testGetSingleUserInvalidDriverIdentityException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(404, [], '{"errorCode": "1C292\/2", "errorMessage": "INVALID_ID"}'),
            ],
        ]);

        $this->expectException(InvalidDriverIdentityException::class);
        $this->expectExceptionMessage('User ID 3 is not found.');

        IpbClient::getInstance()->getUser('3');
    }

    public function testGetSingleUserClientException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(403),
            ],
        ]);

        $this->expectException(DriverException::class);

        IpbClient::getInstance()->getUser('3');
    }

    public function testGetSingleUserGuzzleException()
    {
        $settings = (object) [
            'community_url' => 'https://example.com/community/',
            'apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.ipb', $settings], true);

        config([
            'ipb-connector.config.mocks' => [
                new Response(500),
            ],
        ]);

        $this->expectException(DriverException::class);

        IpbClient::getInstance()->getUser('3');
    }
}
