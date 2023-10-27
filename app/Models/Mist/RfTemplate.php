<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;

class RfTemplate extends BaseModel
{
    public static function all()
    {
        $path = "orgs/" . static::getOrgId() . "/rftemplates";
        return static::getMany($path);
    }

    public static function find($id)
    {
        $path = "orgs/" . static::getOrgId() . "/rftemplates/" . $id;
        return static::getOne($path);
    }

    public function getSites()
    {
        $matches = null;
        $sites = Site::all();
        foreach($sites as $site)
        {
            if($site->rftemplate_id == $this->id)
            {
                $matches[] = $site;
            }
        }
        return collect($matches);
    }
}