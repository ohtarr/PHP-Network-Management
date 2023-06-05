<?php

namespace App\Models\ServiceNow;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\ServiceNow\IncidentResource as Resource;

class IncidentResourceCollection extends ResourceCollection
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
