<?php

namespace App\Models\Device\Cisco\IOSXE;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXEResource as Resource;

class CiscoIOSXEResourceCollection extends ResourceCollection
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
