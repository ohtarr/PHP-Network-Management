<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsPort;
use App\Models\Observium\obsAlert;
use App\Models\Observium\obsBgpPeer;
use App\Models\Observium\obsEvent;
use App\Models\Observium\obsGroup;
use App\Models\ServiceNow\ServiceNowLocation;
use Illuminate\Support\Facades\Log;

class obsDevice extends Model
{
    protected $connection = 'mysql-observium';
	protected $table = 'devices';
    protected $primaryKey = 'device_id';
	public $timestamps = false;

	public function ports()
	{
		return $this->hasMany(obsPort::class, 'device_id', 'device_id');
	}

    public function alerts()
    {
        return $this->hasMany(obsAlert::class, 'device_id', 'device_id');
    }

    public function bgpPeers()
    {
        return $this->hasMany(obsBgpPeer::class, 'device_id', 'device_id');
    }

    public function events()
    {
        return $this->hasMany(obsEvent::class, 'device_id', 'device_id');
    }

    public function getActiveAlerts()
    {
        //return obsAlert::where("device_id",$this->device_id)->where("alert_status",0)->get();
		return $this->alerts()->where("alert_status",0)->get();
    }

    public function groups()
    {
        return $this->belongsToMany(obsGroup::class, 'group_table', 'device_id', 'group_id');
    }

    public function loc()
    {
        return $this->belongsTo(obsDeviceLocation::class, 'device_id', 'device_id');
    }

	public function getPort($portname)
	{
		$port = $this->ports()->where('ifDescr',$portname)->first();
		if($port)
		{
			return $port;
		} else {
			$port = $this->ports()->where('ifName',$portname)->first();
			if($port)
			{
				return $port;
			}
		}
			return null;
	}

	public function disableAllPorts()
	{
		foreach($this->ports as $port)
		{
			$port->disablePolling();
			$port->disableAlerting();
			$port->resetAllAlerts();
		}
	}

    public function enablePolling()
    {
        $this->disabled = 0;
        $this->save();
        $fresh=$this->fresh();
        if($fresh->disabled !== 0)
        {
            throw new Exception("Device ID " . $this->device_id . " failed to enable polling!");
        }
        return true;
    }

    public function disablePolling()
    {
        $this->disabled=1;
        $this->ignore=1;
        $this->save();
        $fresh = $this->fresh();
        if($fresh->disabled !== 1 || $fresh->ignore !== 1)
        {
            throw new Exception("Device ID " . $this->device_id . " failed to disable polling!");
        }
        return true;
    }

    public function enableAlerting()
    {
        $this->ignore = 0;
        $this->save();
        $fresh=$this->fresh();
        if($fresh->ignore !== 0)
        {
            throw new Exception("Device ID " . $this->device_id . " failed to enable alerting!");
        }
        return true;
    }

    public function disableAlerting()
	{
        $this->ignore = 1;
        $this->save();
        $fresh=$this->fresh();
        if($fresh->ignore !== 1)
        {
            throw new Exception("Device ID " . $this->device_id . " failed to disable alerting!");
        }
        return true;
    }

    public function resetAllAlerts()
    {
        $alerts = $this->getActiveAlerts();
        foreach($alerts as $alert)
        {
			$alert->reset();
        }
		return true;
    }

	public function initialDiscovery()
	{
        $this->disableAlerting();
        $this->discover();
        $this->poll();
        $this->disablePolling();
        $this->disableAllPorts();
		$this->resetAllAlerts();
	}

	public function getSiteCode()
	{
		return strtolower(substr($this->hostname,0,8));
	}

	public function getServiceNowLocation()
	{
		return ServiceNowLocation::where('name','=',$this->getSiteCode())->first();
    }

    public function poll()
	{
        $polled1 = $this->last_polled;
        $command = 'php ' . env('OBSERVIUM_ROOT_FOLDER') . "poller.php -h " . $this->device_id;
        shell_exec($command);
        $this->refresh();
        $polled2 = $this->last_polled;
        if($polled1 != $polled2)
        {
            return true;
        }
        return false;
    }

    public function discover()
	{
        $discovered1 = $this->last_discovered;
        $command = 'php ' . env('OBSERVIUM_ROOT_FOLDER') ."discovery.php -h " . $this->device_id;
        shell_exec($command);
        $this->refresh();
        $discovered2 = $this->last_discovered;
        if($discovered1 != $discovered2)
        {
            return true;
        }
        return false;
    }
    
    public static function addDevice($hostname)
    {
        $exists = self::where('hostname',$hostname)->first();
		if(!$exists)
		{
            $command = 'php ' . env('OBSERVIUM_ROOT_FOLDER') ."add_device.php " . $hostname;
			shell_exec($command);
			$device = obsDevice::where('hostname',$hostname)->first();
			if($device)
			{
                $device->initialDiscovery();
				$message = "Device ID " . $device->device_id . " added successfully!";
				print $message . "\n";
                Log::info($message);
			} else {
				$message = "Device " . $hostname . " Failed to add!";
				print $message . "\n";
				throw new \Exception($message);
			}
		} else {
			$message = "Device " . $hostname . " Failed to Add.  Device already exists!";
			print $message . "\n";
			throw new \Exception($message);
		}
		return $device;
    }

    public static function deleteDevice($hostname)
    {
        $check = self::where('hostname',$hostname)->first();
		$device = null;
		if($check)
		{
            $command = 'php ' . env('OBSERVIUM_ROOT_FOLDER') ."delete_device.php " . $hostname;
			shell_exec($command);
			$device = obsDevice::where('hostname',$hostname)->first();
			if($device)
			{
				$message = "Device ID " . $device->device_id . " failed to delete!";
				print $message . "\n";
				throw new \Exception($message);
            } else {
				$message = "Device " . $hostname . " deleted successfully!";
				print $message . "\n";
                Log::info($message);
                return true;
			}
		} else {
			$message = "Device " . $hostname . " Failed to delete.  Device doesn't exist!";
			print $message . "\n";
			throw new \Exception($message);
		}
    }
}
