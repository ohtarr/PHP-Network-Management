<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\WlanTemplate;

class SiteGroup extends BaseModel
{
    public static function all()
    {
        $path = "orgs/" . static::getOrgId() . "/sitegroups";
        return static::getMany($path);
    }

    public static function first()
    {
        $objects = static::all();
        return $objects->first();
    }

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
        return $results;
    }

    public static function create($name)
    {
        $params = ['name'   =>  $name];
        $path = "orgs/" . static::getOrgId() . "/sitegroups";
        return static::post($path, $params);
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
        $templates = WlanTemplate::all();
        foreach($templates as $template)
        {
            if(isset($template['applies']['sitegroup_ids']))
            {
                if(is_array($template['applies']['sitegroup_ids']))
                {
                    foreach($template['applies']['sitegroup_ids'] as $groupip)
                    {
                        if($groupid == $this->id)
                        {
                            $tmps[] = $template['id'];
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