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

namespace Anza\Seat\Connector\Drivers\Ipb\Fetchers;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GuzzleFetcher.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Fetchers
 */
class GuzzleFetcher implements IFetcher
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * GuzzleFetcher constructor.
     *
     * @param string $base_uri
     * @param string $token
     */
    public function __construct(string $base_uri, string $apikey)
    {
        $stack = HandlerStack::create();

        $this->client = new Client([
            'base_uri' => $base_uri,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => sprintf('anza-eve@seat-ipb-connector/%s GitHub SeAT', config('ipb-connector.config.version')),
            ],
            'auth' => [$apikey, ''],
            'handler' => $stack,
        ]);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request(string $method, string $uri = '', array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}