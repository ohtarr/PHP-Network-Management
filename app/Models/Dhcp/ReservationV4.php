<?php

namespace App\Models\Dhcp;

use App\Models\Dhcp\BaseModel;

#[\AllowDynamicProperties]
class ReservationV4 extends BaseModel
{
    protected static $model = "reservationv4";

    /**
     * Find a single DHCPv4 reservation by IP address.
     * GET reservationv4/ip/{ipaddress}
     *
     * TODO: Verify response shape from kea API and adjust unwrapping if needed.
     */
    public static function findByIp(string $ip)
    {
        if (!$ip) {
            return null;
        }
        $path = static::getPath() . '/ip/' . $ip;
        $response = static::getQuery()->get($path);
        if(isset($response[0]->arguments->hosts[0]))
        {
            return static::hydrateOne($response[0]->arguments->hosts[0]);
        } else {
            return null;
        }

    }

    /**
     * Delete a DHCPv4 reservation by IP address.
     * DELETE reservationv4/ip/{ipaddress}
     */
    public static function deleteByIp(string $ip)
    {
        if (!$ip) {
            return null;
        }
        $path = static::getPath() . '/ip/' . $ip;
        return static::getQuery()->delete($path);
    }

    /**
     * Find a single DHCPv4 reservation by MAC address.
     * GET reservationv4/mac/{macaddress}
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
        if(isset($response[0]->arguments->hosts[0]))
        {
            return static::hydrateOne($response[0]->arguments->hosts[0]);
        } else {
            return null;
        }

    }

    /**
     * Delete a DHCPv4 reservation by MAC address.
     * DELETE reservationv4/mac/{macaddress}
     */
    public static function deleteByMac(string $mac)
    {
        if (!$mac) {
            return null;
        }
        $path = static::getPath() . '/mac/' . $mac;
        return static::getQuery()->delete($path);
    }

    /**
     * Get all DHCPv4 reservations for a given subnet.
     * GET reservationv4/subnet/{subnet}
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
        return static::hydrateMany($response[0]->arguments->hosts);
    }

    /**
     * Create a new DHCPv4 reservation.
     * POST /api/dhcp/reservationv4
     *
     * @param  string  $ipaddress    The IP address to reserve.
     * @param  string  $hwaddress    The MAC/hardware address of the client.
     * @param  string  $description  A human-readable description for the reservation.
     */
    public static function create(string $ipaddress, string $hwaddress, string $description)
    {
        $body = [
            'ipaddress'   => $ipaddress,
            'hwaddress'   => $hwaddress,
            'usercontext' => [
                'description' => $description,
            ],
        ];
        return static::getQuery()->post(static::getPath(), $body);
    }

    /**
     * Update an existing DHCPv4 reservation.
     * PATCH /api/dhcp/reservationv4
     *
     * @param  string  $ipaddress    The IP address of the reservation to update.
     * @param  string  $hwaddress    The new MAC/hardware address of the client.
     * @param  string  $description  The new description for the reservation.
     */
    public static function update(string $ipaddress, string $hwaddress, string $description)
    {
        $body = [
            'ipaddress'   => $ipaddress,
            'hwaddress'   => $hwaddress,
            'usercontext' => [
                'description' => $description,
            ],
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
