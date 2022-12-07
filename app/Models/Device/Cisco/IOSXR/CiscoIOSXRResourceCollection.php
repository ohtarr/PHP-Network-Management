<?php

namespace App\Models\Device\Cisco\IOSXR;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXRResource as Resource;

class CiscoIOSXRResourceCollection extends ResourceCollection
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
