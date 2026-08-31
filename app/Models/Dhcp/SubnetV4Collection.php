<?php

namespace App\Models\Dhcp;

use Illuminate\Support\Collection;
use IPv4\Subnet as SubnetCalculator;

class SubnetV4Collection extends Collection
{
    /**
     * Find the subnet that contains the given IP address.
     * Returns the first (most specific) matching SubnetV4, or null if none found.
     *
     * @param  string  $ip  An IPv4 address (e.g. "10.1.2.50")
     * @return \App\Models\Dhcp\SubnetV4|null
     */
    public function findByIp(string $ip): ?SubnetV4
    {
        if (!$ip) {
            return null;
        }

        $longIp = ip2long($ip);

        return $this->filter(function ($subnet) use ($longIp) {
            $calc = SubnetCalculator::fromCidr($subnet->subnet);
            $longStart = ip2long($calc->networkAddress()->asQuads());
            $longEnd   = ip2long($calc->broadcastAddress()->asQuads());
            return $longIp >= $longStart && $longIp <= $longEnd;
        })->sortByDesc(function ($subnet) {
            // Sort by prefix length descending so the most specific match comes first
            return (int) explode('/', $subnet->subnet)[1];
        })->first();
    }

    /**
     * Find a subnet by its exact network address and prefix length.
     * Returns the matching SubnetV4, or null if not found.
     *
     * @param  string  $subnet  The network address (e.g. "10.1.2.0")
     * @param  int     $mask    The prefix length (e.g. 24)
     * @return \App\Models\Dhcp\SubnetV4|null
     */
    public function findBySubnet(string $subnet, int $mask): ?SubnetV4
    {
        if (!$subnet || !$mask) {
            return null;
        }

        $cidr = $subnet . '/' . $mask;

        return $this->first(function ($item) use ($cidr) {
            return $item->subnet === $cidr;
        });
    }

    /**
     * Find the parent (supernet) of the given network address.
     * Returns the smallest supernet that contains the given IP/network, or null.
     *
     * @param  string  $ip  An IPv4 address or network address (e.g. "10.1.2.0")
     * @return \App\Models\Dhcp\SubnetV4|null
     */
    public function findParent(string $ip): ?SubnetV4
    {
        return $this->findByIp($ip);
    }

    /**
     * Find all subnets (children) whose network address falls within the given supernet.
     *
     * @param  string  $network  The supernet network address (e.g. "10.0.0.0")
     * @param  int     $bitmask  The supernet prefix length (e.g. 8)
     * @return static
     */
    public function findChildren(string $network, int $bitmask): static
    {
        if (!$network || !$bitmask) {
            return new static([]);
        }

        $calc      = new SubnetCalculator($network, $bitmask);
        $longStart = ip2long($calc->networkAddress()->asQuads());
        $longEnd   = ip2long($calc->broadcastAddress()->asQuads());

        return $this->filter(function ($subnet) use ($longStart, $longEnd) {
            $longNetwork = ip2long($subnet->network());
            return $longNetwork >= $longStart && $longNetwork <= $longEnd;
        })->values();
    }
}
