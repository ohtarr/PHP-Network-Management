<?php

namespace App\Models\Gizmo;

use Illuminate\Database\Eloquent\Collection;
use IPv4\Subnet as SubnetCalculator;

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
        $longstart = ip2long($ipcalc->networkAddress()->asQuads());
        $longend = ip2long($ipcalc->broadcastAddress()->asQuads());

        foreach($this as $scope){
            if(ip2long($scope->scopeID) >= $longstart && ip2long($scope->scopeID) <= $longend){
                $overlaps[] = $scope;
            }
        }
        return Collection::make($overlaps);
    }
}