<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;
use App\Models\Mist\Ap;
use App\Models\Mist\DeviceSwitch;
use App\Models\Mist\Gateway;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class Device extends BaseModel
{
    use HasRolesAndAbilities;

    protected static $mistapp = "orgs";
    protected static $mistmodel = "inventory";
/*     public static function getPath()
    {
        return "orgs/" . static::getOrgId() . "/inventory";
    } */

    public static function hydrateOne($data)
    {
        $types = [
            'switch'    =>  DeviceSwitch::class,
            'gateway'   =>  Gateway::class,
            'ap'        =>  Ap::class,
        ];
        if(isset($data->type))
        {
            foreach($types as $type => $class)
            {
                $match = 0;
                if($data->type == $type)
                {
                    $match = 1;
                    $object = new $class;
                    break;
                }
            }
            if($match == 0)
            {
                //print "Failed to determine TYPE for device {$data->serial} : *" . $data->type . "*" . PHP_EOL;
                return null;
            }
        }
        foreach($data as $key => $value)
        {
            $object->$key = $value;
        }
        return $object;
    }

    public static function hydrateMany($response)
    {
        $objects = [];
        foreach($response as $item)
        {
            $object = static::hydrateOne($item);
            if($object)
            {
                $objects[] = $object;
            }
        }
        return collect($objects);
    }

/*     public static function all($columns = [])
    {
        $url = "orgs/" . static::getOrgId() . "/inventory";
        return static::hydrateMany(static::getQuery()->get($url));
    } */

    public static function find($id)
    {
        return static::getQuery()->get();
    }

    public static function findById($id)
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
            if(!isset($device->name))
            {
                continue;
            }
            if(strtolower($device->name) == strtolower($name))
            {
                return $device;
            }
        }
    }

    public static function findBySerial($serial)
    {
        return static::where("serial",$serial)->where('vc', 'true')->first();
    }

    public static function findByMac($mac)
    {
        return static::where("mac",$mac)->first();
    }

/*     public static function where($key, $value)
    {
        $url = "orgs/" . static::getOrgId() . "/inventory?vc=true&" . $key . "=" . $value;
        return static::hydrateMany(static::getQuery()->get($url));
    } */

    public function isVcMaster()
    {
        if($this->mac == $this->vc_mac)
        {
            return true;
        } else {
            return false;
        }
    }

    public function getVcMaster()
    {
        if(isset($this->vc_mac))
        {
            return static::findByMac($this->vc_mac);
        }
    }

    public function getVcMembers()
    {
        if(isset($this->vc_mac))
        {
            return static::where('vc', true)->where('vc_mac', $this->vc_mac)->get();
        }
    }

    public function getVirtualChassis()
    {
        $vc = $this->getVcMaster();
        if(!$vc)
        {
            return null;
        }
        $details = $vc->getSiteDeviceStats();
        $members = $this->getVcMembers();
        if(!isset($details->module_stat))
        {
            return null;
        }

        $switch['id'] = $vc->id;
        $switch['name'] = $vc->name;
        $switch['mac'] = $vc->mac;
        $switch['serial'] = $vc->serial;
        $switch['site_id'] = $vc->site_id;
        $switch['connected'] = $vc->connected;
        if(isset($details->ip_stat->ip))
        {
            $switch['ip'] = $details->ip_stat->ip;
        }
        foreach($details->module_stat as $FPC)
        {
            $invmember = $members->where('mac', $FPC->mac)->first();
            $switch['members'][$FPC->fpc_idx]['member_id'] = $FPC->fpc_idx;            
            $switch['members'][$FPC->fpc_idx]['mac'] = $FPC->mac;
            $switch['members'][$FPC->fpc_idx]['serial'] = $FPC->serial;
            $switch['members'][$FPC->fpc_idx]['vc_role'] = $FPC->vc_role;
            $switch['members'][$FPC->fpc_idx]['model'] = $invmember->model;
            $switch['members'][$FPC->fpc_idx]['magic'] = $invmember->magic;
            $switch['members'][$FPC->fpc_idx]['id'] = $invmember->id;
            $switch['members'][$FPC->fpc_idx]['version'] = $invmember->version;
        }
        return $switch;
    }

    public static function claim($magic)
    {
        $params = [$magic];
        $path = "orgs/" . static::getOrgId() . "/inventory";
        return static::post($path, $params);
    }

    public static function assignDevicesToSite(array $devices, $siteid)
    {
        $path = "orgs/" . static::getOrgId() . "/inventory";
        $params = [
            'op'        =>  'assign',
            'site_id'   =>  $siteid,
            'macs'      =>  $devices,
        ];
        return static::getQuery()->put($path, $params);
    }

    public function assignToSite($siteid)
    {
        if(isset($this->mac))
        {
            $path = "orgs/" . static::getOrgId() . "/inventory";
            $params = [
                'op'        =>  'assign',
                'site_id'   =>  $siteid,
                'macs'      =>  [$this->mac],
            ];
            return $this->getQuery()->put($path, $params);
        }
    }

    public function unassign()
    {
        $path = "orgs/" . static::getOrgId() . "/inventory";
        $params = [
            'op'        =>  'unassign',
            'macs'      =>  [$this->mac],
        ];
        return $this->getQuery()->put($path, $params);
    }

    public function getSite()
    {
        if(isset($this->site_id))
        {
            return Site::find($this->site_id);
        }
    }

    public function getSiteDevice($type = "all")
    {
        if(!isset($this->site_id))
        {
            throw new \Exception('Object is missing {site_id}');
        }
        if(isset($this->name))
        {
            $path = "sites/" . $this->site_id . "/devices?type={$type}&name=" . $this->name;
            $device = Device::get($path)->first();
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
            $device = Device::get($path)->first();
            $array = $device->toArray();
            foreach($array as $key=>$value)
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
        $url = "sites/" . $this->site_id . "/stats/ports/search";
        $response =  static::getQuery()->where('mac',$this->mac)->get($url, 1);
        return $response->results;
    }

    public function update(array $attributes = [], array $options = [])
    {
        $path = "sites/" . $this->site_id . "/devices/" . $this->id;
        return $this->getQuery()->put($path, $attributes);
    }

    public function getSummary()
    {
        if(!isset($this->ip_stat))
        {
            $this->getSiteDeviceStats();
        }
        $this->custom = new \stdClass();
        if(isset($this->module_stat))
        {
            $this->custom->vc_member_count = count($this->module_stat);
        }
        return $this;
    }

/*     public function getSummary2()
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
            'ip',
            'model',
            'org_id',
            'site_id',
            'uptime',
            'type',
            'version',
            'status',
            'num_clients',
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
        if(isset($this->lldp_stat))
        {
            $device->lldp_stat = $this->lldp_stat;
        }
        if(isset($this->module_stat))
        {
            $device->vc_member_count = count($this->module_stat);
        }

        return $device;
    } */

    public function mapInterfaces()
    {

    }

/*     public function getSummaryDetails()
    {
        if(!isset($this->module_stat))
        {
            $this->getSiteDeviceStats();
        }
        $vclinkreg = "/\S+\-(\d+)\/(\d+)\/(\d+)/";
        $this->custom = new \stdClass();
        $this->custom->vc_member_count = count($this->module_stat);
        $this->custom->vc_members = [];
        $portdetails = $this->getPortDetails();
        foreach($this->module_stat as $vcmember)
        {
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
                if(isset($vcmember->$key))
                {
                    $tmp->$key = $vcmember->$key;
                }
            }
            $tmp->id = $vcmember->fpc_idx;
            $template = static::findModelTemplate($vcmember->model);
            $pics = [];
            foreach($template['pics'] as $picnum => $portstotal)
            {
                unset($ports);
                $pic = new \stdClass();
                $pic->id = $picnum;
                for ($currentport = 0; $currentport < $portstotal; $currentport++)
                {
                    unset($match);
                    $currentname = $vcmember->fpc_idx . "/" . $picnum . "/" . $currentport;
                    $reg = "#" . $currentname . "$#";
                    foreach($portdetails as $portdetail)
                    {
                        if(preg_match($reg, $portdetail->port_id))
                        {
                            $match = $portdetail;
                            break;
                        }
                        if(isset($vcmember->vc_links))
                        {
                            foreach($vcmember->vc_links as $vclink)
                            {
                                if(preg_match($vclinkreg, $vclink->port_id, $hits))
                                {
                                    if($hits[1] == $vcmember->fpc_idx)
                                    {
                                        if($hits[2] == $picnum)
                                        {
                                            if($hits[3] == $currentport)
                                            {
                                                $match = $vclink;
                                                $match->up = true; 
                                            }
                                        }
                                    }
                                }   
                            }
                        }
                    }
                    if(!isset($match))
                    {
                        $match = new \stdClass();
                    }
                    if(!isset($match->up))
                    {
                        $match->up = false;
                    }
                    $match->id = $currentport;
                    $ports[] = (object)$match;
                }
                $pic->ports = $ports;
                $tmp->pics[] = $pic;
            }
            //get stack ports
            //$device->vc_members[] = $tmp;
            $this->custom->vc_members[] = $tmp;
        }
        return $this;
    } */

    public function getSummaryDetails()
    {
        $vcmembers = $this->getVcMembers();

        //if(!isset($this->module_stat))
        //{
        //    $master->getSiteDeviceStats();
        //}
        $this->getSiteDeviceStats();
        $vclinkreg = "/\S+\-(\d+)\/(\d+)\/(\d+)/";
        $this->custom = new \stdClass();
        $this->custom->vc_member_count = count($this->module_stat);
        $this->custom->vc_members = [];
        $portdetails = $this->getPortDetails();
        $modulekeys = [
            //'model',
            'serial',
            'mac',
            'version',
            'uptime',
            'vc_state',
            'vc_role',
        ];
        foreach($this->module_stat as $vcmember)
        {
            //if NO vc member ID, skip
            if(!isset($vcmember->fpc_idx))
            {
                continue;
            }
            $tmp = new \stdClass();
            //grab the key=>values we care about
            foreach($modulekeys as $key)
            {
                if(isset($vcmember->$key))
                {
                    $tmp->$key = $vcmember->$key;
                }
            }
            $tmp->id = $vcmember->fpc_idx;
            //Grab model from getVcMembers() output
            foreach($vcmembers as $vcm)
            {
                if($vcmember->mac == $vcm->mac)
                {
                    $tmp->model = $vcm->model;
                }
            }
            //calculate number of ports on each pic
            foreach($vcmember->pics as $pic)
            {
                $numports = 0;
                foreach($pic->port_groups as $portgroup)
                {
                    $numports = $numports + $portgroup->count;
                }
                $pics[$pic->index] = $numports; 
            }
            //build custom pic/port information
            foreach($pics as $picnum => $portstotal)
            {
                unset($ports);
                $pic = new \stdClass();
                $pic->id = $picnum;
                //Go through all ports and match up port details from getPortDetails() method.
                for ($currentport = 0; $currentport < $portstotal; $currentport++)
                {
                    unset($match);
                    //   SWITCH_ID/PIC_ID/PORT_ID = 0/0/0 as an example
                    $currentname = $vcmember->fpc_idx . "/" . $picnum . "/" . $currentport;
                    $reg = "#" . $currentname . "$#";
                    foreach($portdetails as $portdetail)
                    {
                        //If port_id matches regex above, set it and move on
                        if(preg_match($reg, $portdetail->port_id))
                        {
                            $match = $portdetail;
                            break;
                        }
                        //If port is being used for VC_LINK, show status of it.
                        if(isset($vcmember->vc_links))
                        {
                            foreach($vcmember->vc_links as $vclink)
                            {
                                if(preg_match($vclinkreg, $vclink->port_id, $hits))
                                {
                                    if($hits[1] == $vcmember->fpc_idx)
                                    {
                                        if($hits[2] == $picnum)
                                        {
                                            if($hits[3] == $currentport)
                                            {
                                                $match = $vclink;
                                                $match->up = true; 
                                            }
                                        }
                                    }
                                }   
                            }
                        }
                    }
                    if(!isset($match))
                    {
                        $match = new \stdClass();
                    }
                    if(!isset($match->up))
                    {
                        $match->up = false;
                    }
                    $match->id = $currentport;
                    $ports[] = (object)$match;
                }
                $pic->ports = $ports;
                $tmp->pics[] = $pic;
            }
            //get stack ports
            //$device->vc_members[] = $tmp;
            $this->custom->vc_members[] = $tmp;
        }
        return $this;
    }

/*     public function getSummaryDetails2()
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
        $device->custom = new \stdClass();
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
                if(isset($vcmember[$key]))
                {
                    $tmp->$key = $vcmember[$key];
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
            $device->custom->vc_members[] = $tmp;
        }
        return $device;
    } */

    public function getDhcpId()
    {
        if(isset($this->vc_mac))
        {
            $mac = $this->vc_mac;
        } elseif(isset($this->mac)) {
            $mac = $this->mac;
        }
        if(!isset($mac))
        {
            return null;
        }
        $hex = bin2hex($mac . "-0");
        $formattedHex = chunk_split($hex, 2, '-');
        return rtrim($formattedHex, '-');
    }

}