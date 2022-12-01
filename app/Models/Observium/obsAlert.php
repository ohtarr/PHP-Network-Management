<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsDevice;
use App\Models\Observium\obsAlertTest;
use App\Models\Observium\obsPort;
use App\Models\Observium\obsBgpPeer;

class obsAlert extends Model
{
	protected $connection = 'mysql-observium';
	protected $table = 'alert_table';
    protected $primaryKey = 'alert_table_id';

    public $timestamps = false;


    public function device()
    {
		return $this->belongsTo(obsDevice::class, 'device_id', 'device_id');
    }

	public function alertTest()
	{
		return $this->belongsTo(obsAlertTest::class, 'alert_test_id', 'alert_test_id');
	}

	public function alertContacts()
	{
		return $this->alertTest->alertContacts();
	}

	public function entity()
	{
		switch ($this->entity_type) {
			case 'device':
				$model = new obsDevice;
				$foreignKey = "device_id";
				break;
			case 'bgp_peer':
				$model = new obsBgpPeer;
				$foreignKey = "bgpPeer_id";
				break;
			case 'port':
				$model = new obsPort;
				$foreignKey = "port_id";
				break;			
			default:
				# code...
				break;
		}
        return $this->belongsTo($model, 'entity_id', $foreignKey);

		//$entity = $model::find($this->entity_id);
		//return $entity;
	}

	public function reset()
	{
		$this->alert_status = 1;
		$this->last_message = "";
		$this->save();
		$fresh = $this->fresh();
		if($fresh->alert_status !== 1 || $fresh->last_message !== "")
		{
			throw new Exception("Alert ID " . $alert->alert_table_id . " failed to reset!");
		}
		return true;
	}

}
