<?php

namespace App\Models\Device;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Device\DeviceResource as Resource;

class DeviceResourceCollection extends ResourceCollection
{
    public $collects = Resource::class;
    
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        return [
            'data'  =>  $this->collection,
        ];
    }
}
