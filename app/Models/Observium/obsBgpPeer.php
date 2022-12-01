<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use Apps\Models\Observium\obsDevice;

class obsBgpPeer extends Model
{
	protected $connection = 'mysql-observium';
	protected $table = 'bgpPeers';
    protected $primaryKey = 'bgpPeer_id';



	public function device()
	{
        return $this->belongsTo(obsDevice::class, 'device_id', 'device_id');
	}

	public function peer()
	{
        return $this->belongsTo(obsDevice::class, 'peer_device_id', 'device_id');
	}

}
