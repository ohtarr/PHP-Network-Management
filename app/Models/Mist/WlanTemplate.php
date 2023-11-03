<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;
use App\Models\Mist\SiteGroup;

class WlanTemplate extends BaseModel
{
    public static function all($columns = [])
    {
        $path = "orgs/" . static::getOrgId() . "/templates";
        return static::getMany($path);
    }

    public static function find($id)
    {
        $path = "orgs/" . static::getOrgId() . "/templates/" . $id;
        return static::getOne($path);
    }

    public function getSites()
    {
        $siteids = $this->applies->site_ids;
        $sites = null;
        foreach($siteids as $siteid)
        {
            $sites[] = Site::find($siteid);
        }
        return collect($sites);
    }

    public function getSiteGroups()
    {
        $groupids = $this->applies->sitegroup_ids;
        $groups = null;
        foreach($groupids as $groupid)
        {
            $groups[] = SiteGroup::find($groupid);
        }
        return collect($groups);
    }
}