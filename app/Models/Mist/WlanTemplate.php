<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;
use App\Models\Mist\SiteGroup;

class WlanTemplate extends BaseModel
{
    protected static $mistapp = "orgs";
    protected static $mistmodel = "templates";

    public static function find($id)
    {
        $path = static::getPath() . "/" . $id;
        return static::getQuery()->get($path)->first();
    }

    public static function where($key, $value)
    {
        return static::all()->where($key, $value);
    }

    public function getSites()
    {
        $siteids = $this->applies->site_ids;
        $sites = [];
        foreach($siteids as $siteid)
        {
            $sites[] = Site::find($siteid);
        }
        return collect($sites);
    }

    public function getSiteGroups()
    {
        $groupids = $this->applies->sitegroup_ids;
        $groups = [];
        foreach($groupids as $groupid)
        {
            $groups[] = SiteGroup::find($groupid);
        }
        return collect($groups);
    }
}