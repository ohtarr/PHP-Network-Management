<?php

namespace App\Models\Device\Opengear;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Device\Opengear\OpengearResource as Resource;

class OpengearResourceCollection extends ResourceCollection
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
