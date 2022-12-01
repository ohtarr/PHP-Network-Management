<?php

namespace App\Models\Location\Building;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BuildingResourceCollection extends ResourceCollection
{
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
