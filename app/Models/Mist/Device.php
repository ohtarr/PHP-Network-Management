<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class Device extends BaseModel
{
    use HasRolesAndAbilities;

    public static function all($columns = [])
    {
        $url = "orgs/" . static::getOrgId() . "/inventory";
        return static::hydrateMany(static::getQuery()->get($url));
    }

    public static function find($id)
    {
        $devices = static::all();
        foreach($devices as $device)
        {
            if(strtolower($device->id) == strtolower($id))
            {
                return $device;
            }
        }
    }

    public static function findByName($name)
    {
        $devices = static::all();
        foreach($devices as $device)
        {
            if(strtolower($device->name) == strtolower($name))
            {
                return $device;
            }
        }
    }

    public static function findSerial($serial)
    {
        return static::where("serial",$serial)->first();
    }

    public static function findMac($mac)
    {
        return static::where("mac",$mac)->first();
    }

    public static function where($key, $value)
    {
        $url = "orgs/" . static::getOrgId() . "/inventory" . "?" . $key . "=" . $value;
        return static::hydrateMany(static::getQuery()->get($url));
    }

    public static function claim($magic)
    {
        $params = [$magic];
        $path = "orgs/" . static::getOrgId() . "/inventory";
        return static::post($path, $params);
    }

    public function assignToSite($siteid)
    {
        if(!isset($this->mac))
        {
            return null;
        }
        $path = "orgs/" . static::getOrgId() . "/inventory";
        $params = [
            'op'        =>  'assign',
            'site_id'   =>  $siteid,
            'macs'      =>  [$this->mac],
        ];
        return static::put($path, $params);
    }

    public function unassign()
    {
        $path = "orgs/" . static::getOrgId() . "/inventory";
        $params = [
            'op'        =>  'unassign',
            'macs'      =>  [$this->mac],
        ];
        return static::put($path, $params);
    }

    public function getSite()
    {
        if(isset($this->site_id))
        {
            return Site::find($this->site_id);
        }
    }

    public function getSiteDevice()
    {
        if(!isset($this->site_id))
        {
            throw new \Exception('Object is missing {site_id}');
        }
        if(isset($this->name))
        {
            $path = "sites/" . $this->site_id . "/devices?type=all&name=" . $this->name;
            $device = Device::getMany($path)->first();
            foreach($device as $key=>$value)
            {
                $this->$key = $value;
            }
            return $this;
        }

        if(isset($this->id))
        {
            $devices = $this->getSite()->getDevices();
            foreach($devices as $device)
            {
                if($device->id == $this->id)
                {
                    foreach($device as $key=>$value)
                    {
                        $this->$key = $value;
                    }
                    return $this;
                }
            }
        }
    }

    public function getSiteDeviceStats()
    {
        if(!isset($this->site_id))
        {
            throw new \Exception('Object is missing {site_id}');
        }
        if(isset($this->id))
        {
            $path = "sites/" . $this->site_id . "/stats/devices/" . $this->id;
            $device = Device::getOne($path);
            foreach($device as $key=>$value)
            {
                $this->$key = $value;
            }
            return $this;
        }

    }

    public function getPortDetails()
    {
        if(!isset($this->mac))
        {
            throw new \Exception('Object is missing {mac}');
        }
        $url = "sites/" . $this->site_id . "/stats/ports/search?mac=" . $this->mac;
        $response =  static::getQuery()->get($url);
        return $response['results'];
    }

    public function update(array $attributes = [], array $options = [])
    {
        $path = "sites/" . $this->site_id . "/devices/" . $this->id;
        return $this->put($path, $attributes);
    }

    public static function getModelDefinitions()
    {
        return [
            'EX3400-48P'    =>  [
                'model'     =>  'EX3400-48P',
                'mistmodel' =>  'EX3400-48P',
                'pics'      =>  [
                    0   =>  48,
                    1   =>  2,
                    2   =>  4,
                ],
            ],
            'EX3400-24P'    =>  [
                'model'     =>  'EX3400-24P',
                'mistmodel' =>  'EX3400-24P',
                'pics'      =>  [
                    0   =>  24,
                    1   =>  2,
                    2   =>  4,
                ],
            ],
            'EX4100-48MP'    =>  [
                'model'     =>  'EX4100-48MP',
                'mistmodel' =>  'EX4100-48MP-CHAS',
                'pics'      =>  [
                    0   =>  48,
                    1   =>  4,
                    2   =>  4,
                ],
            ],
            'EX4100-48P'    =>  [
                'model'     =>  'EX4100-48P',
                'mistmodel' =>  'EX4100-48P-CHAS',
                'pics'      =>  [
                    0   =>  48,
                    1   =>  4,
                    2   =>  4,
                ],
            ],
            'EX2300-48P'    =>  [
                'model'     =>  'EX2300-48P',
                'mistmodel' =>  'EX2300-48P',
                'pics'      =>  [
                    0   =>  48,
                    1   =>  4,
                ],
            ],
            'EX2300-24P'    =>  [
                'model'     =>  'EX2300-24P',
                'mistmodel' =>  'EX2300-24P',
                'pics'      =>  [
                    0   =>  24,
                    1   =>  4,
                ],
            ],
            'EX2300-C-12P'    =>  [
                'model'     =>  'EX2300-C-12P',
                'mistmodel' =>  'EX2300-C-12P',
                'pics'      =>  [
                    0   =>  12,
                    1   =>  2,
                ],
            ],
            'QFX5120-48Y'    =>  [
                'model'     =>  'QFX5120-48Y',
                'mistmodel' =>  'QFX5120-48Y',
                'pics'      =>  [
                    0   =>  56,
                ],
            ],
        ];
    }

    public static function findModelTemplate($model)
    {
        foreach(static::getModelDefinitions() as $modeltemplate)
        {
            if(strtolower($model) == strtolower($modeltemplate['model']))
            {
                return $modeltemplate;
            }
            if(strtolower($model) == strtolower($modeltemplate['mistmodel']))
            {
                return $modeltemplate;
            }
        }
    }

    public function getSummary()
    {
        if(!isset($this->module_stat))
        {
            $this->getSiteDeviceStats();
        }
        $keys = [
            'id',
            'name',
            'hostname',
            'serial',
            'mac',
            'model',
            'org_id',
            'site_id',
            'uptime',
            'type',
            'version',
            'status',            
        ];
        $device = new \stdClass();
        foreach($keys as $key)
        {
            if(isset($this->$key))
            {
                $device->$key = $this->$key;
            }
        }
        if(isset($this->ip_config))
        {
            $device->mgmtint = $this->ip_config;
        }
        if(isset($this->module_stat))
        {
            $device->vc_member_count = count($this->module_stat);
        }

        return $device;
    }

    public function getSummaryDetails()
    {
        if(!isset($this->module_stat))
        {
            $this->getSiteDeviceStats();
        }
        $vclinkreg = "/\S+\-(\d+)\/(\d+)\/(\d+)/";
        $keys = [
            'id',
            'name',
            'hostname',
            'serial',
            'mac',
            'model',
            'org_id',
            'site_id',
            'uptime',
            'type',
            'version',
            'status',            
        ];
        $device = new \stdClass();
        foreach($keys as $key)
        {
            if(isset($this->$key))
            {
                $device->$key = $this->$key;
            }
        }
        if(isset($this->ip_config))
        {
            $device->mgmtint = $this->ip_config;
        }
        $device->vc_member_count = count($this->module_stat);
        $device->vc_members = [];
        $portdetails = $this->getPortDetails();
        foreach($this->module_stat as $vcmember)
        {
            unset($tmp);
            unset($template);
            $modulekeys = [
                'model',
                'serial',
                'mac',
                'version',
                'uptime',
                'vc_state',
                'vc_role',                
            ];
            $tmp = new \stdClass();
            foreach($modulekeys as $key)
            {
                if(isset($this->$key))
                {
                    $tmp->$key = $this->$key;
                }
            }
            $tmp->id = $vcmember['fpc_idx'];
            $template = static::findModelTemplate($vcmember['model']);
            $pics = [];
            foreach($template['pics'] as $picnum => $portstotal)
            {
                unset($ports);
                $pic = new \stdClass();
                $pic->id = $picnum;
                for ($currentport = 0; $currentport < $portstotal; $currentport++)
                {
                    unset($match);
                    $currentname = $vcmember['fpc_idx'] . "/" . $picnum . "/" . $currentport;
                    $reg = "#" . $currentname . "$#";
                    foreach($portdetails as $portdetail)
                    {

                        if(preg_match($reg, $portdetail['port_id']))
                        {
                            $match = $portdetail;
                            break;
                        }
                        if(isset($vcmember['vc_links']))
                        {
                            foreach($vcmember['vc_links'] as $vclink)
                            {
                                if(preg_match($vclinkreg, $vclink['port_id'], $hits))
                                {
                                    if($hits[1] == $vcmember['fpc_idx'])
                                    {
                                        if($hits[2] == $picnum)
                                        {
                                            if($hits[3] == $currentport)
                                            {
                                                $match = $vclink;
                                                $match['up'] = true; 
                                            }
                                        }
                                    }
                                }   
                            }
                        }
                    }
                    if(!isset($match['up']))
                    {
                        $match['up'] = false;
                    }
                    $match['id'] = $currentport;
                    $ports[] = (object)$match;
                }
                $pic->ports = $ports;
                $tmp->pics[] = $pic;
            }
            //get stack ports
            $device->vc_members[] = $tmp;
        }
        return $device;
    }

}