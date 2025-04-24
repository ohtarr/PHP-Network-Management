<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class AsnRanges extends BaseModel
{
    protected $app = "ipam";
    protected $model = "asn-ranges";

    public function getNextAvailableAsn()
    {
        $url = env('NETBOX_BASE_URL') . "/api/" . $this->app . "/" . $this->model . "/" . $this->id . "/available-asns";
        $query = static::getQuery();
        return $query->customGet($url)->first();
    }

}