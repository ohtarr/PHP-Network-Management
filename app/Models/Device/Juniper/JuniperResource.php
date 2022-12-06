<?php

namespace App\Models\Device\Juniper;

use Illuminate\Http\Resources\Json\JsonResource;

class JuniperResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $attribs = $this->getAttributes();
        $relations = $this->getRelations();
        $return = array_merge($attribs,$relations);

        return $return;
    }
}
