<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Sites;

#[\AllowDynamicProperties]
class Asns extends BaseModel
{
    protected $app = "ipam";
    protected $model = "asns";

    public static function getNextAvailable()
    {
        $available = AsnRanges::where('name','AUTO-PROVISIONING')->first()->getNextAvailableAsn();
        if(isset($available->asn))
        {
            return $available->asn;
        }
    }

    public function getAssignedSites()
    {
        return Sites::where('asn_id',$this->id)->get();
    }

    public function unassignAllSites()
    {
        $sites = $this->getAssignedSites();
        foreach($sites as $site)
        {
            $newasns = [];
            foreach($site->asns as $asn)
            {
                if($asn->id != $this->id)
                {
                    $newasns[] = $asn->id;
                }
            }
            $params['asns'] = $newasns;
            $site->update($params);
        }
    }

    public function assignToSite($siteid)
    {
        $site = Sites::find($siteid);
        if(!$site)
        {
            return null;
        }
        $params['asns'] = [$this->id];
        $site->update($params);
        $site = Sites::find($siteid);
        return $site;
    }
}