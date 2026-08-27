<?php

namespace App\Models\Dhcp;

use App\Models\Dhcp\BaseModel;

#[\AllowDynamicProperties]
class LeaseV4 extends BaseModel
{
    protected static $model = "leasev4";

    /**
     * Find a single DHCPv4 lease by IP address.
     * GET leasev4/ip/{ipaddress}
     *
     * TODO: Verify response shape from kea API and adjust unwrapping if needed.
     */
    public static function findByIp(string $ip)
    {
        if (!$ip) {
            return null;
        }
        $path = static::getPath() . '/' . $ip;
        $response = static::getQuery()->get($path);
        if(isset($response[0]->arguments))
        {
            return static::hydrateOne($response[0]->arguments);
        } else {
            return null;
        }

    }

    /**
     * Delete a DHCPv4 lease by IP address.
     * DELETE leasev4/ip/{ipaddress}
     */
    public static function deleteByIp(string $ip)
    {
        if (!$ip) {
            return null;
        }
        $path = static::getPath() . '/' . $ip;
        return static::getQuery()->delete($path);
    }

    /**
     * Find a single DHCPv4 lease by MAC address.
     * GET leasev4/mac/{macaddress}
     *
     * TODO: Verify response shape from kea API and adjust unwrapping if needed.
     */
    public static function findByMac(string $mac)
    {
        if (!$mac) {
            return null;
        }
        $path = static::getPath() . '/mac/' . $mac;
        $response = static::getQuery()->get($path);
        if(isset($response[0]->arguments->leases[0]))
        {
            return static::hydrateOne($response[0]->arguments->leases[0]);
        } else {
            return null;
        }

    }

    /**
     * Get all DHCPv4 leases for a given subnet.
     * GET leasev4/subnet/{subnet}
     *
     * TODO: Verify response shape from kea API and adjust unwrapping if needed.
     */
    public static function allBySubnet(string $subnet)
    {
        if (!$subnet) {
            return collect([]);
        }
        $path = static::getPath() . '/subnet/' . $subnet;
        $response = static::getQuery()->get($path);
        return static::hydrateMany($response[0]->arguments->leases);
    }

    /**
     * Create a new DHCPv4 lease.
     * POST /api/dhcp/leasev4
     *
     * @param  string  $ipaddress    The IP address of the lease.
     * @param  string  $hwaddress    The MAC/hardware address of the client.
     * @param  string  $description  A human-readable description for the lease.
     */
    public static function create(string $ipaddress, string $hwaddress, string $description)
    {
        $body = [
            'ipaddress'   => $ipaddress,
            'hwaddress'   => $hwaddress,
            'hostname'    => $description,
        ];
        return static::getQuery()->post(static::getPath(), $body);
    }

    /**
     * Update an existing DHCPv4 lease.
     * PATCH /api/dhcp/leasev4
     *
     * @param  string  $ipaddress    The IP address of the lease to update.
     * @param  string  $hwaddress    The new MAC/hardware address of the client.
     * @param  string  $description  The new description for the lease.
     */
    public static function update(string $ipaddress, string $hwaddress, string $description)
    {
        $body = [
            'ipaddress'   => $ipaddress,
            'hwaddress'   => $hwaddress,
            'hostname'    => $description,
        ];
        return static::getQuery()->patch(static::getPath(), $body);
    }

    public function delete()
    {
        if(isset($this->ipAddress) && $this->ipAddress)
        {
            $response = static::deleteByIp($this->ipAddress);
            if(isset($response[0]->result) && $response[0]->result == 0)
            {
                return true;
            } else {
                return false;
            }
        }
    }
}
