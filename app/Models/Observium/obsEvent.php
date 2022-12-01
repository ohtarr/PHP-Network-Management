<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsDevice;
use App\Models\Observium\obsBgpPeer;
use App\Models\Observium\obsPort;

class obsEvent extends Model
{
	protected $connection = 'mysql-observium';
	protected $table = 'eventlog';
    protected $primaryKey = 'event_id';
    public $timestamps = false;

    public function device()
    {
		return $this->belongsTo(obsDevice::class, 'device_id', 'device_id');
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
	}


}
