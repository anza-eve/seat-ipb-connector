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

namespace Anza\Seat\Connector\Drivers\Ipb\Http\Controllers;

use Seat\Web\Http\Controllers\Controller;

/**
 * Class RegistrationController.
 *
 * @package Warlof\Seat\Connector\Ipb\Http\Controllers
 */
class RegistrationController extends Controller
{

    /**
     * @return mixed
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function redirectToProvider()
    {

        try {
            $settings = setting('seat-connector.drivers.forums', true);
        } catch (SettingException $e) {
            return response('Driver not configured.', 400);
        }

        if (is_null($settings) || ! is_object($settings))
            return response('Driver not configured.', 400);

        if (! property_exists($settings, 'community_url') || is_null($settings->community_url) || $settings->community_url == '')
            return response('Driver not configured.', 400);

        return redirect()->away($settings->community_url);
    }
}