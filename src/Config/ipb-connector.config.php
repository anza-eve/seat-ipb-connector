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

return [
    'fetcher'        => \Anza\Seat\Connector\Drivers\Ipb\Fetchers\GuzzleFetcher::class,
    'version'        => '1.0.0',
    'default_group'  => 3, // Members
    'primary_groups' => [
        11,
    ],
    'special_groups' => [
        2 => 'Guests', 
        4 => 'Administrators', 
        7 => 'Users Awaiting Email Confirmation', 
        9 => 'Banned Users',
    ],
];
