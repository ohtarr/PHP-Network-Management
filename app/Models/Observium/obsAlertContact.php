<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsAlertTest;

class obsAlertContact extends Model
{
	protected $connection = 'mysql-observium';
	protected $table = 'alert_contacts';
	protected $primaryKey = 'contact_id';

	public function AlertTests()
	{
		return $this->belongsToMany(obsAlertTest::class, 'alert_contacts_assoc', 'contact_id', 'alert_checker_id');
	}
}
