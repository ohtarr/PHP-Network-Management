<?php

namespace App\Models\Observium;

use Illuminate\Database\Eloquent\Model;
use App\Models\Observium\obsAlert;
use App\Models\Observium\obsAlertContact;

class obsAlertTest extends Model
{
    protected $connection = 'mysql-observium';
	protected $table = 'alert_tests';
    protected $primaryKey = 'alert_test_id';

    public function alerts()
    {
        return $this->hasMany(obsAlert::class, 'alert_test_id', 'alert_test_id');
    }

	public function alertContacts()
	{
		return $this->belongsToMany(obsAlertContact::class, 'alert_contacts_assoc', 'alert_checker_id', 'contact_id');
	}
}
