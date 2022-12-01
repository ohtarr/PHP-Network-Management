<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsDevice;
use App\Models\Observium\obsAlert;

class obsPort extends Model
{
	protected $connection = 'mysql-observium';
	protected $table = 'ports';
    protected $primaryKey = 'port_id';

    public $timestamps = false;

	public function device()
	{
		return $this->belongsTo(obsDevice::class, 'device_id', 'device_id');
	}

	public function getAlerts()
	{
		return obsAlert::where('device_id',$this->device_id)->where('entity_type','port')->where('entity_id',$this->port_id)->get();
	}

	public function enablePolling()
	{
		$this->disabled = 0;
		$this->save();
		$fresh=$this->fresh();
		if($fresh->disabled !== 0)
		{
            throw new Exception("Port ID " . $this->port_id . " failed to enable!");
		}
		$this->resetAllAlerts();
		return true;
	}

	public function disablePolling()
	{
		$this->disabled=1;
		//$this->ignore=1;
		$this->save();
		$fresh = $this->fresh();
        //if($fresh->disabled !== 1 || $fresh->ignore !== 1)
        if($fresh->disabled !== 1)
		{
            throw new Exception("Port ID " . $this->port_id . " failed to disable!");
        }
		$this->resetAllAlerts();
        return true;
	}

	public function disableAlerting()
	{
		$this->ignore = 1;
		$this->save();
		$fresh=$this->fresh();
        if($fresh->ignore !== 1)
        {
			throw new Exception("Port ID " . $this->port_id . " failed to ignore!");
        }
		$this->resetAllAlerts();
        return true;
	}

	public function enableAlerting()
	{
		$this->ignore = 0;
		$this->save();
		$fresh=$this->fresh();
        if($fresh->ignore !== 0)
        {
            throw new Exception("Port ID " . $this->port_id . " failed to unignore!");
        }
		$this->resetAllAlerts();
        return true;
	}

	public function resetAllAlerts()
	{
		$alerts = $this->getAlerts();
		foreach($alerts as $alert)
		{
			$alert->alert_status = 1;
			$alert->last_message = "";
			$alert->save();
			$fresh = $alert->fresh();
			if($fresh->alert_status !== 1 || $fresh->last_message !== "")
			{
				throw new Exception("Alert ID " . $alert->alert_table_id . " failed to reset!");
			}
			return true;
		}
	}

}
