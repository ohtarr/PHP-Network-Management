<?php

namespace App\Models\Credential;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Device\Device;

class Credential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'class',
        'username',
        'passkey',
        'description',
        'options',
      ];

      public function devices()
      {
          return $this->hasMany(Device::class, 'credential_id', 'id',);
      }
}
