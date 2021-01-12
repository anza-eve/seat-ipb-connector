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

use Illuminate\Http\Request;
use Seat\Web\Http\Controllers\Controller;

/**
 * Class SettingsController.
 *
 * @package Warlof\Seat\Connector\Drivers\Ipb\Http\Controllers
 */
class SettingsController extends Controller
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return mixed
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function store(Request $request)
    {
        $request->validate([
            'community_url' => 'required|url',
            'apikey'        => 'required|string',
        ]);

        $settings = (object) [
            'community_url' => $request->input('community_url'),
            'apikey'        => $request->input('apikey'),
        ];

        setting(['seat-connector.drivers.forums', $settings], true);

        return redirect()->route('seat-connector.settings')
            ->with('success', 'IPB settings has successfully been updated.');
    }
}