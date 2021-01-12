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
use Illuminate\Support\Str;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class IpbMember.
 *
 * @package Warlof\Seat\Connector\Drivers\Ipb\Driver
 */
class IpbMember implements IUser
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $group_ids;

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
     * IpbMember constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->group_ids = [];
        $this->groups    = collect();
        $this->hydrate($attributes);

        $this->default_groups = collect(config('ipb-connector.config.default_groups', []));
        $this->special_groups = collect(config('ipb-connector.config.special_groups', []));
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return bool
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function setName(string $name): bool
    {

        try {
            IpbClient::getInstance()->sendCall('POST', '/core/members/{user.id}', [
                'user.id' => $this->id,
                'name' => $name,
            ]);
        } catch (GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][forums] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to change user name from %s to %s.', $this->getName(), $name),
                0,
                $e);
        }

        $this->name = $name;

        return true;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSets(): array
    {
        if ($this->groups->isEmpty()) {
            foreach ($this->group_ids as $group_id) {
                $set = IpbClient::getInstance()->getSet($group_id);

                if (is_null($set)) continue;

                $this->groups->put($group_id, $set);
            }
        }

        return $this->groups->toArray();
    }

    /**
     * @param array $attributes
     * @return \Warlof\Seat\Connector\Drivers\Ipb\Driver\IpbMember
     */
    public function hydrate(array $attributes = []): IpbMember
    {
        $this->id    = $attributes['id'];
        $this->email = $attributes['email'];
        $this->name  = $attributes['name'];
        
        $group_ids = collect([$attributes['primaryGroup']]);
        $group_ids = $group_ids->merge($attributes['secondaryGroups']);
        $this->group_ids = $group_ids->pluck('id')->unique()->flatten()->all();

        return $this;
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function addSet(ISet $group)
    {
        try {
            if (in_array($group->getId(), $this->group_ids))
                return;

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

            $this->group_ids[] = $group->getId();
            $this->groups->put($group->getId(), $group);
        } catch (SettingException | GuzzleException $e) {
            throw new DriverException(
                sprintf('Unable to add set %s to the user %s.', $group->getName(), $this->getName()),
                0,
                $e);
        }
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function removeSet(ISet $group)
    {
        try {
            if (! in_array($group->getId(), $this->group_ids) || $this->special_groups->has($group->getId()))
                return;

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

            $this->groups->pull($group->getId());

            $key = array_search($group->getId(), $this->group_ids);

            if ($key !== false) {
                unset($this->group_ids[$key]);
            }
        } catch (SettingException | GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][forums] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to remove set %s from the user %s.', $group->getName(), $this->getName()),
                0,
                $e);
        }
    }
}