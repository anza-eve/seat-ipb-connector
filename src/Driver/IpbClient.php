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

namespace Anza\Seat\Connector\Drivers\Ipb\Driver;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class IpbClient.
 *
 * @package Anza\Seat\Connector\Drivers\Ipb\Driver
 */
class IpbClient implements IClient
{

    /**
     * @var \Anza\Seat\Connector\Drivers\Ipb\Driver\IpbClient
     */
    private static $instance;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @var string
     */
    private $community_url;

    /**
     * @var string
     */
    private $apikey;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $members;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $groups;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $default_groups;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $special_groups;

    /**
     * IpbClient constructor.
     *
     * @param array $parameters
     */
    private function __construct(array $parameters)
    {
        $this->community_url = $parameters['community_url'];
        $this->apikey        = $parameters['apikey'];

        $this->members = collect();
        $this->groups  = collect();

        $this->default_groups = collect(config('ipb-connector.config.default_groups', []));
        $this->special_groups = collect(config('ipb-connector.config.special_groups', []));

        $fetcher = config('ipb-connector.config.fetcher');
        $base_uri = rtrim($this->community_url, '/') . '/api/';
        $this->client = new $fetcher($base_uri, $this->apikey);
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\Ipb\Driver\IpbClient
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public static function getInstance(): IClient
    {
        if (! isset(self::$instance)) {
            try {
                $settings = setting('seat-connector.drivers.forums', true);
            } catch (SettingException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }

            if (is_null($settings) || ! is_object($settings))
                throw new DriverSettingsException('The Driver has not been configured yet.');

            if (! property_exists($settings, 'community_url') || is_null($settings->community_url) || $settings->community_url == '')
                throw new DriverSettingsException('Parameter community_url is missing.');

            if (! property_exists($settings, 'apikey') || is_null($settings->apikey) || $settings->apikey == '')
                throw new DriverSettingsException('Parameter apikey is missing.');

            self::$instance = new IpbClient([
                'community_url' => $settings->community_url,
                'apikey'        => $settings->apikey,
            ]);
        }

        return self::$instance;
    }

    /**
     * Reset the instance
     */
    public static function tearDown()
    {
        self::$instance = null;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUsers(): array
    {
        if ($this->members->isEmpty()) {
            try {
                $this->seedMembers();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->members->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\IUser|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUser(string $id): ?IUser
    {
        $member = $this->members->get($id);

        if (! is_null($member))
            return $member;

        try {
            $member = $this->sendCall('GET', '/core/members/{id}', [
                'id' => $id,
            ]);
        } catch (ClientException $e) {
            logger()->error($e->getMessage(), $e->getTrace());

            $error = json_decode($e->getResponse()->getBody());

            if (! is_null($error) && property_exists($error, 'errorCode')) {
                switch ($error->code) {
                    // The member ID does not exist
                    // ref: https://invisioncommunity.com/developers/rest-api?endpoint=core/members/GETitem
                    case '1C292\/2':
                        throw new InvalidDriverIdentityException(sprintf('User ID %s is not found.', $id));                       
                }
            }

            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        }

        $member = new IpbMember((array) $member);
        $this->members->put($member->getClientId(), $member);

        return $member;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSets(): array
    {
        if ($this->groups->isEmpty()) {
            try {
                $this->seedGroups();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->groups->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\ISet|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSet(string $id): ?ISet
    {
        if ($this->groups->isEmpty()) {
            try {
                $this->seedGroups();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->groups->get($id);
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $arguments
     * @return object
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendCall(string $method, string $endpoint, array $arguments = [])
    {
        $uri = ltrim($endpoint, '/');

        foreach ($arguments as $uri_parameter => $value) {
            if (strpos($uri, sprintf('{%s}', $uri_parameter)) === false)
                continue;

            $uri = str_replace(sprintf('{%s}', $uri_parameter), $value, $uri);
            Arr::pull($arguments, $uri_parameter);
        }

        if ($method == 'GET') {
            $response = $this->client->request($method, $uri, [
                'query' => $arguments,
            ]);
        } else {
            $response = $this->client->request($method, $uri, [
                'form_params' => $arguments,
            ]);
        }

        logger()->debug(
            sprintf('[seat-connector][forums] [http %d, %s] %s -> /%s',
                $response->getStatusCode(), $response->getReasonPhrase(), $method, $uri)
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function seedMembers()
    {

        $current_page = 1;
        $total_pages  = null;

        do {
            // set some options such as the page number we want to request from IPB.
            $options= [
                'page'    => $current_page,
                'perPage' => 50,
            ];

            // send the API request
            $members = $this->sendCall('GET', '/core/members', $options);

            // retrieve the total number of paginated results we need to query.
            $total_pages = $members['totalPages'];

            // if we have received no result, break here.
            if (empty($members))
                break;

            // iterate over our results to build our members collection.
            foreach ($members['results'] as $member_attributes) {

                $member = new IpbMember($member_attributes);
                $this->members->put($member->getClientId(), $member);
            }

            // are we at our last page? if so, we break here.
            if ($current_page == $total_pages)
                break;

            // increment our current page counter
            $current_page++;
        } while (true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    private function seedGroups()
    {

        $current_page = 1;
        $total_pages  = null;

        do {
            // set some options such as the page number we want to request from IPB.
            $options= [
                'page'    => $current_page,
                'perPage' => 50,
            ];

            // send the API request
            $groups = IpbClient::getInstance()->sendCall('GET', '/core/groups', $options);

            // retrieve the total number of paginated results we need to query.
            $total_pages = $groups['totalPages'];

            // if we have received no result, break here.
            if (empty($groups))
                break;

            // iterate over our results to build our members collection.
            foreach ($groups['results'] as $group_attributes) {
                $group = new IpbGroup($group_attributes);
                $this->groups->put($group->getId(), $group);
            }

            // are we at our last page? if so, we break here.
            if ($current_page == $total_pages)
                break;

            // increment our current page counter
            $current_page++;
        } while (true);
    }
}