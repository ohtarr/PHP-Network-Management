<?php

namespace App\Models\Dhcp;

use Illuminate\Support\Collection;

class ReservationV4Collection extends Collection
{
    /**
     * Filter reservations by client ID (case-insensitive).
     *
     * @param  string  $clientId
     * @return static
     */
    public function findByClientId(string $clientId): static
    {
        return $this->filter(function ($reservation) use ($clientId) {
            return isset($reservation->clientId) && strcasecmp($reservation->clientId, $clientId) === 0;
        })->values();
    }

    /**
     * Filter reservations by hardware (MAC) address (case-insensitive).
     *
     * @param  string  $hwAddress
     * @return static
     */
    public function findByHwAddress(string $hwAddress): static
    {
        return $this->filter(function ($reservation) use ($hwAddress) {
            return isset($reservation->hwAddress) && strcasecmp($reservation->hwAddress, $hwAddress) === 0;
        })->values();
    }

    /**
     * Filter reservations by IP address (case-sensitive; not needed for IPs, but kept consistent).
     *
     * @param  string  $ipAddress
     * @return static
     */
    public function findByIpAddress(string $ipAddress): static
    {
        return $this->filter(function ($reservation) use ($ipAddress) {
            return isset($reservation->ipAddress) && $reservation->ipAddress === $ipAddress;
        })->values();
    }
}
