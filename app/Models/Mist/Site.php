<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Device;
use App\Models\Mist\NetworkTemplate;
use App\Models\Mist\WlanTemplate;
use App\Models\Mist\GatewayTemplate;
use App\Models\Mist\RfTemplate;
use App\Models\Mist\SiteGroup;

class Site extends BaseModel
{
    protected static $mistapp = "orgs";
    protected static $mistmodel = "sites";

    public static function find(string $id)
    {
        return static::all()->where('id', $id)->first();
    }

    public static function where($key, $value)
    {
        return static::all()->where($key, $value);
    }

    public static function findByName(string $name)
    {
        return static::where('name', $name)->first();
    }

    public function getDevices($type="all")
    {
        $path = "sites/" . $this->id . "/devices";
        return Device::where('type', $type)->get($path);
    }

    public function getDeviceStats($type="all")
    {
        $path = "sites/" . $this->id . "/stats/devices";
        return Device::where('type', $type)->get($path);
    }

    public static function getDeviceStatsBySiteId($siteid, $type="all")
    {
        $path = "sites/" . $siteid . "/stats/devices";
        return Device::where('type', $type)->get($path);
    }

    public static function getAllSummarized()
    {
        $sites = static::all();
        foreach($sites as $site)
        {
            unset($tmp);
            $tmp = new \stdClass();
            $tmp->id = $site->id;
            $tmp->name = $site->name;
            $array[] = $tmp;
        }
        return $array;
    }

    public function getDeviceSummary($type = "all")
    {
        $devices = $this->getDeviceStats($type);
        $results = [];
        foreach($devices as $device)
        {
            unset($tmp);
            $tmp = $device->getSummary();
            $results[] = $tmp;
        }
        return collect($results);
    }

    public function getSettings()
    {
        return static::getQuery()->get("sites/" . $this->id . "/setting", 1);
    }

    public function updateSettings(array $params)
    {
        $path = "sites/" . $this->id . "/setting";
        return static::getQuery()->put($path, $params);
    }

    public function getSiteGroups()
    {
        return SiteGroup::whereSite($this->id);
    }

    public function addToSiteGroup($groupid)
    {
        $groupids = [];
        if(isset($this->sitegroup_ids))
        {
            if(is_array($this->sitegroup_ids))
            {
                $groupids = $this->sitegroup_ids;
            }
        }
        foreach($groupids as $gid)
        {
            if(strtolower($gid) == strtolower($groupid))
            {
                print "Group aleady exists!\n";
                return false;
            }
        }
        $groupids[] = $groupid;
        $params = [
            'sitegroup_ids' =>  $groupids,
        ];
        //$this->sitegroup_ids = $groupids;
        $this->update($params);
        return $this->fresh();
    }

    public function addVariable(array $variables)
    {
        $existingvars = [];
        $settings = $this->getSettings();
        if(isset($settings['vars']))
        {
            if(is_array($settings['vars']))
            {
                $existingvars = $settings['vars'];
            }
        }
        $newvars = $existingvars + $variables;
        $params['vars'] = $newvars;
        return $this->updateSettings($params);
    }

    public function delete()
    {
        $path = "sites/" . $this->id;
        return static::getQuery()->delete($path);
    }

    public function getAssets()
    {
        $qb = static::getQuery();
        $path = "sites/" . $this->id . "/assets";
        return $qb->get($path);
    }

    public function getDiscoveredSwitches()
    {
        $path = "sites/" . $this->id . "/stats/discovered_switches/search";
        return static::getQuery()->get($path);
    }

    public function setRfTemplate($templateid)
    {

        //put($path, $params)
    }

    public function getRfTemplate()
    {
        if(!isset($this->rftemplate_id))
        {
            return null;
        }
        if($this->rftemplate_id)
        {
            return RfTemplate::find($this->rftemplate_id);
        }
    }

    public function getNetworkTemplate()
    {
        if(!isset($this->networktemplate_id))
        {
            return null;
        }
        if($this->networktemplate_id)
        {
            return NetworkTemplate::find($this->networktemplate_id);
        }
    }

    public function getGatewayTemplate()
    {
        if(!isset($this->gatewaytemplate_id))
        {
            return null;
        }
        if($this->gatewaytemplate_id)
        {
        return GatewayTemplate::find($this->gatewaytemplate_id);
        }
    }

    public function getWlanTemplates()
    {
        $tmps = null;
        $templates = WlanTemplate::all();
        foreach($templates as $template)
        {
            if(isset($template->applies['site_ids']))
            {
                if(is_array($template->applies['site_ids']))
                {
                    foreach($template->applies['site_ids'] as $siteid)
                    {
                        if($siteid == $this->id)
                        {
                            $tmps[] = $template['id'];
                        }
                    }
                }
            }
        }
        return $tmps;
    }
}