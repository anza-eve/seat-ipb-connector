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

use GuzzleHttp\Exception\GuzzleException;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class IpbGroup.
 *
 * @package Warlof\Seat\Connector\Drivers\Ipb\Driver
 */
class IpbGroup implements ISet
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $members;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $default_groups;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $special_groups;

    /**
     * IpbGroup constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $this->members = collect();
        $this->hydrate($attributes);

        $this->default_groups = collect(config('ipb-connector.config.default_groups', []));
        $this->special_groups = collect(config('ipb-connector.config.special_groups', []));
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            $users = IpbClient::getInstance()->getUsers();

            $this->members = collect(array_filter($users, function (IUser $user) {
                return in_array($this, $user->getSets());
            }));
        }

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        try {
            // get current users group memberships
            $response = IpbClient::getInstance()->sendCall('GET', '/core/members/{user.id}', [
                'user.id'         => $this->id,
            ]);

            // collect our current groups
            $current_groups = collect([$response['primaryGroup']]);
            $current_groups = $current_groups->merge($response['secondaryGroups']);

            // is the new group we are adding a primary?
            $new_group = $response['primaryGroup']['id'];
            if (in_array($group->getId(), config('ipb-connector.config.primary_groups', []))) {
                $new_group = $group->getId();
            } else {
                $current_groups->push([
                    'id' => $group->getId(),
                    'name' => $group->getName(),
                ]);
            }

            // update user with new group memberships
            IpbClient::getInstance()->sendCall('POST', '/core/members/{user.id}', [
                'user.id'         => $this->id,
                'group'           => $new_group,
                'secondaryGroups' => $current_groups->pluck('id')->unique()->flatten()->all(),
            ]);
        } catch (GuzzleException $e) {
            throw new DriverException(
                sprintf('Unable to add user %s as a member of set %s.', $user->getName(), $this->getName()),
                0,
                $e);
        }

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function removeMember(IUser $user)
    {
        if (! in_array($user, $this->getMembers()) || $this->special_groups->has($this->id))
            return;

        try {
            // get current users group memberships
            $response = IpbClient::getInstance()->sendCall('GET', '/core/members/{user.id}', [
                'user.id'         => $this->id,
            ]);

            // collect our current groups
            $current_groups = collect([$response['primaryGroup']]);
            $current_groups = $current_groups->merge($response['secondaryGroups']);

            // is the group we are removing a primary?
            $new_group = $response['primaryGroup']['id'];
            if (in_array($group->getId(), config('ipb-connector.config.primary_groups', []))) {
                $new_group = config('ipb-connector.config.default_group', 3);
            } else {
                $current_groups = $current_groups->reject(function ($value, $key) use ($group) {
                    return $value['id'] == $group->getId();
                });
            }

            // update user with new group memberships
            IpbClient::getInstance()->sendCall('POST', '/core/members/{user.id}', [
                'user.id'         => $this->id,
                'group'           => $new_group,
                'secondaryGroups' => $current_groups->pluck('id')->unique()->flatten()->all(),
            ]);
        } catch (GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][forums] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to remove user %s from set %s.', $user->getName(), $this->getName()),
                0,
                $e);
        }

        $this->members->pull($user->getClientId());
    }

    /**
     * @param array $attributes
     * @return \Warlof\Seat\Connector\Drivers\Discord\Driver\IpbGroup
     */
    public function hydrate(array $attributes = []): IpbGroup
    {
        $this->id   = $attributes['id'];
        $this->name = $attributes['name'];

        return $this;
    }
}