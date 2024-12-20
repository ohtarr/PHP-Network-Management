<?php

namespace App\Models\Device;

use Illuminate\Database\Eloquent\Model;
use App\Models\Device\Device;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Output extends Model
{
    protected $table = 'outputs';

    protected $fillable = [
        'id',
        'type',
        'data',
        'deleted_at',
        'created_at',
        'updated_at',
      ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    //Custom Accessor for data.  If json_decoded into an array, return array, otherwise return raw string.
    protected function data(): Attribute
    {
        return Attribute::make(
            get: function(string $value) {
                $array = json_decode($value,1);
                if(is_array($array))
                {
                    return $array;
                } else {
                    return $value;
                }
            }
        );
    }

}
