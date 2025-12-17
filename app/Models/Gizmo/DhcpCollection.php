<?php

namespace App\Models\Gizmo;

use Illuminate\Database\Eloquent\Collection;
use IPv4\SubnetCalculator;

class DhcpCollection extends Collection 
{
    public function removeScope($scopeid)
    {
        return $this->where('scopeID','!=', $scopeid);
    }

    public function findOverlap($network, $bitmask)
    {
        $overlaps = [];
        $ipcalc = new SubnetCalculator($network, $bitmask);
        $range = $ipcalc->getIPAddressRange();

        $longstart = ip2long($range[0]);
        $longend = ip2long($range[1]);

        foreach($this as $scope){
            if(ip2long($scope->scopeID) >= $longstart && ip2long($scope->scopeID) <= $longend){
                $overlaps[] = $scope;
            }
        }
        return Collection::make($overlaps);
    }
}