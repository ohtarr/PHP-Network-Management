<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;

class NetworkTemplate extends BaseModel
{
    public static function all($columns = [])
    {
        $path = "orgs/" . static::getOrgId() . "/networktemplates";
        return static::getMany($path);
    }

    public static function find($id)
    {
        $path = "orgs/" . static::getOrgId() . "/networktemplates/" . $id;
        return static::getOne($path);
    }

    public function getSites()
    {
        $matches = null;
        $sites = Site::all();
        foreach($sites as $site)
        {
            if($site->networktemplate_id == $this->id)
            {
                $matches[] = $site;
            }
        }
        return collect($matches);
    }
}