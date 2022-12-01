<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsGroup;

class obsGroupAssoc extends Model
{
    protected $connection = 'mysql-observium';
	protected $table = 'groups_assoc';
    protected $primaryKey = 'group_assoc_id';
	public $timestamps = false;

    public function group()
    {
        return $this->belongsTo(obsGroup::class, 'group_id', 'group_id');
    }

}

