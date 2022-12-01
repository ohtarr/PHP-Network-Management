<?php

namespace App\Models\Location\Site;

use Illuminate\Database\Eloquent\Collection;

class SiteCollection extends Collection 
{
    public function withDhcpScopes()
    {
        $scopes = Dhcp::all()->withoutOptions()->withoutReservations()->withoutFailover();
        return $this->map(function ($item) use ($scopes) {
            $item->scopes = $scopes->findScopesByName($item->name);
            return $item;
        });
    }

    public function withDhcpScopesFull()
    {
        $scopes = Dhcp::all();
        return $this->map(function ($item) use ($scopes) {
            $item->scopes = $scopes->findScopesByName($item->name);
            return $item;
        });
    }

    public function withServiceNowLocations()
    {
        if($this->count() < 25)
        {
            return $this->map(function ($item) {
                $item->servicenowlocation = $item->getServiceNowLocation();
                return $item;
            });
        } else {
            $locs = ServiceNowLocation::all();
            return $this->map(function ($item) use ($locs) {
                $loc = $locs->where('sys_id',$item->loc_sys_id)->first();
                $item->servicenowlocation = $loc;
                return $item;
            });
        }
    }

    public function withAllRooms()
    {
        return $this->map(function ($item) {
            $item->rooms = $item->getAllRooms();
            return $item;
        });
    }

    public function withAddress()
    {
        return $this->map(function ($item) {
            $item->address = $item->getAddress();
            return $item;
        });
    }
}