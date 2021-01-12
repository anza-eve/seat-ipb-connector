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

Route::group([
    'namespace'  => 'Anza\Seat\Connector\Drivers\Ipb\Http\Controllers',
    'prefix'     => 'seat-connector',
    'middleware' => ['web', 'auth', 'locale'],
], function () {

    Route::group([
        'prefix' => 'registration',
    ], function () {

        Route::get('/forums', [
            'as'   => 'seat-connector.drivers.forums.registration',
            'uses' => 'RegistrationController@handleRegistration',
        ]);

    });

    Route::group([
        'prefix' => 'settings',
        'middleware' => 'can:global.superuser',
    ], function () {

        Route::post('/forums', [
            'as' => 'seat-connector.drivers.forums.settings',
            'uses' => 'SettingsController@store',
        ]);

    });

});