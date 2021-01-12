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

use Anza\Seat\Connector\Drivers\Ipb\Driver\IpbClient;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Events\EventLogger;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Models\User;

/**
 * Class RegistrationController.
 *
 * @package Anza\Seat\Connector\Ipb\Http\Controllers
 */
class RegistrationController extends Controller
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function handleRegistration(Request $request)
    {

        $settings = setting('seat-connector.drivers.forums', true);

        if (is_null($settings) || ! is_object($settings))
            throw new DriverSettingsException('The Driver has not been configured yet.');

        if (! property_exists($settings, 'community_url') || is_null($settings->community_url) || $settings->community_url == '')
            throw new DriverSettingsException('Parameter community_url is missing.');

        if (! property_exists($settings, 'apikey') || is_null($settings->apikey) || $settings->apikey == '')
            throw new DriverSettingsException('Parameter apikey is missing.');

        // retrieve driver instance
        $client = IpbClient::getInstance();

        // check for existing connector user
        $connector_user = User::where('connector_type', 'forums')->where('user_id', auth()->user()->id)->first();

        // if we got a connector user, we can't proceed. redirect with error.
        if (! is_null($connector_user))
            return redirect()->route('seat-connector.identities')->with('error', 'You already have an account and can not use this function again to make a new one.');

        // Check for any existing account in IPB
        try {
            $response = $client->sendCall('GET', 'core/members', [
                'name'  => auth()->user()->name,
                'email' => auth()->user()->email,
            ]);

            if($response['totalResults'] > 0)
                return redirect()->route('seat-connector.identities')->with('error', 'You already have an account and can not use this function again to make a new one.');
        } catch (RequestException $e) {

            return redirect()->route('seat-connector.identities')->with('error', 'We could not communicate with the Forums to check your registration. Please try again. ' . $e->getCode());
        }

        // Generate a new password
        $password = Str::random(16);

        // Is email set? Validation check!
        $emailaddress = auth()->user()->email;
        if(strlen($emailaddress) < 1)
            return redirect()->route('seat-connector.identities')->with('error', 'You cannot create a forum account without an email address. Make sure you have entered your email address on the profile tab.');

        // Create the account
        try {
            $response = $client->sendCall('POST', 'core/members', [
                'name'                  => auth()->user()->name,
                'email'                 => auth()->user()->email,
                'password'              => $password,
                'group'                 => 3,
                'registrationIpAddress' => $request->ip(),
                'validated'             => 0,
            ]);

            if($response['id'] > 0) {
                // spawn or update existing identity using returned information
                $driver_user = User::updateOrCreate([
                    'connector_type' => 'forums',
                    'user_id'        => auth()->user()->id,
                ], [
                    'connector_id'   => $response['id'],
                    'unique_id'      => auth()->user()->email,
                    'connector_name' => auth()->user()->name,
                ]);

                event(new EventLogger('forums', 'notice', 'registration',
                    sprintf('User %s (%d) has been registered with ID %s and UID %s',
                        $driver_user->connector_name, $driver_user->user_id, $driver_user->connector_id, $driver_user->unique_id)));

                return redirect()->route('seat-connector.identities')->with('info', 'A new Forum account has been generated for you. Check your email for a link you will need to click to complete your registration. Your temporary Forum account password is: ' . $password . ' - you should change this once you are logged in.');
            }
        } catch (RequestException $e) {

            return redirect()->route('seat-connector.identities')->with('error', 'We could not communicate with the Forums to complete your registration. Please try again. ' . $e->getCode());
        }

        return redirect()->route('seat-connector.identities')->with('error', 'An unexpected error occurred and we could not register your account. Please try again.');
    }
}