<?php

namespace App\Models\Location\Site;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SiteResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if($request->has('servicenowlocation'))
        {
            $this->collection->withServiceNowLocations();
        }

        if($request->has('rooms'))
        {
            $this->collection->withAllRooms();
        }

        if($request->has('address'))
        {
            $this->collection->withAddress();
        }

        return [
            'data'  =>  $this->collection,
        ];
    }
}
