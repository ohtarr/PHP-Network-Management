<?php

namespace App\Models\Device\Cisco\IOS;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Device\Cisco\IOS\CiscoIOSResource as Resource;

class CiscoIOSResourceCollection extends ResourceCollection
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
