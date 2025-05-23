<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\WlanTemplate;

class SiteGroup extends BaseModel
{
    protected static $mistapp = "orgs";
    protected static $mistmodel = "sitegroups";

/*     public static function all($columns = [])
    {
        $path = "orgs/" . static::getOrgId() . "/sitegroups";
        return static::getMany($path);
    } */

/*     public static function first()
    {
        $objects = static::all();
        return $objects->first();
    } */

    public static function find(string $id)
    {
        $sitegroups = static::all();
        foreach($sitegroups as $sitegroup)
        {
            if($sitegroup->id == $id)
            {
                return $sitegroup;
            }
        }
    }

    public static function whereSite($siteid)
    {
        $results = [];
        $sitegroups = static::all();
        foreach($sitegroups as $sitegroup)
        {
            if(isset($sitegroup->site_ids))
            {
                foreach($sitegroup->site_ids as $sid)
                {
                    if(strtolower($sid) == strtolower($siteid))
                    {
                        $results[] = $sitegroup;
                        break;
                    }
                }
            }
        }
        return collect($results);
    }

    public static function create($name)
    {
        $params = ['name'   =>  $name];
        return static::post(static::getPath(), $params);
    }

    public function addToWlanTemplate($templateid)
    {
        $template = WlanTemplate::find($templateid);
        if(!$template)
        {
            return null;
        }
        $params['applies'] = $template->applies;
        $params['applies']['sitegroup_ids'][] = $this->id;
        $path = "orgs/" . static::getOrgId() . "/template/" . $template->id;
        return WlanTemplate::put($path, $params);
    }

    public function getWlanTemplates()
    {
        $tmps = [];
        $templates = WlanTemplate::all();
        foreach($templates as $template)
        {
            if(isset($template->applies->sitegroup_ids))
            {
                if(is_array($template->applies->sitegroup_ids))
                {
                    foreach($template->applies->sitegroup_ids as $groupid)
                    {
                        if($groupid == $this->id)
                        {
                            $tmps[] = $template->id;
                        }
                    }
                }
            }
        }
        return $tmps;
    }

    public function delete()
    {
        $path = "orgs/" . static::getOrgId() . "/sitegroups/" . $this->id;
        return static::deleteOne($path);
    }
}