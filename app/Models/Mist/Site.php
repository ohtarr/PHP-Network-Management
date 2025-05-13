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
    public static function all($columns = [])
    {
        $path = "orgs/" . static::getOrgId() . "/sites";
        return static::getMany($path);
    }

    public static function first()
    {
        $path = "orgs/" . static::getOrgId() . "/sites";
        $objects = static::getMany($path);
        return $objects->first();
    }

    public static function find(string $id)
    {
        $path = "sites/" . $id;
        return static::getOne($path);
    }

    public static function findByName(string $name)
    {
        $path = "orgs/" . static::getOrgId() . "/sites";
        $sites = static::getMany($path);
        foreach($sites as $site)
        {
            if(strtolower($site->name) == strtolower($name))
            {
                return $site;
            }
        }
    }

    public function getDevices($type="all")
    {
        $path = "sites/" . $this->id . "/devices?type=" . $type;
        return Device::getMany($path);
    }

    public function getDeviceStats($type="all")
    {
        $path = "sites/" . $this->id . "/stats/devices?type=" . $type;
        return Device::getMany($path);
    }

    public static function getDeviceStatsBySiteId($siteid, $type="all")
    {
        $path = "sites/" . $siteid . "/stats/devices?type=" . $type;
        return Device::getMany($path);
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
        return $results;
    }

    public function getSettings()
    {
        $qb = static::getQuery();
        return $qb->get("sites/" . $this->id . "/setting");
    }

    public function getInventory()
    {
        $path = "orgs/" . static::getOrgId() . "/inventory?site_id=" . $this->id;
        return Device::getMany($path);
    }

    public static function create(array $params)
    {
        $path = "orgs/" . static::getOrgId() . "/sites";
        return static::post($path, $params);
    }

    public function update(array $attributes = [], array $options = [])
    {
        $qb = static::getQuery();
        $path = "sites/" . $this->id;
        return $qb->put($path, $attributes);
    }

    public function updateSettings(array $params)
    {
        $qb = static::getQuery();
        $path = "sites/" . $this->id . "/setting";
        return $qb->put($path, $params);
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
        return static::deleteOne($path);
    }

    public function getAssets()
    {
        $qb = static::getQuery();
        $path = "sites/" . $this->id . "/assets";
        return $qb->get($path);
    }

    public function getDiscoveredSwitches()
    {
        $qb = static::getQuery();
        $path = "sites/" . $this->id . "/stats/discovered_switches/search";
        return $qb->get($path);
    }

    public function setRfTemplate($templateid)
    {

        //put($path, $params)
    }

    public function getRfTemplate()
    {
        return RfTemplate::find($this->rftemplate_id);
    }

    public function getNetworkTemplate()
    {
        return NetworkTemplate::find($this->networktemplate_id);
    }

    public function getGatewayTemplate()
    {
        return GatewayTemplate::find($this->gatewaytemplate_id);
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