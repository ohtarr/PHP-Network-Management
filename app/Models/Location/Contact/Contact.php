<?php

namespace App\Models\Location\Contact;

use Illuminate\Database\Eloquent\Model;
use App\Models\Location\Site\Site;
use App\Models\Location\Building\Building;

class Contact extends Model
{

    protected $connection = 'mysql-whereuat';

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

}
