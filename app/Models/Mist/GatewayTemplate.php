<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;

class GatewayTemplate extends BaseModel
{
    protected static $mistapp = "orgs";
    protected static $mistmodel = "gatewaytemplates";

/*     public static function all($columns = [])
    {
        $path = "orgs/" . static::getOrgId() . "/gatewaytemplates";
        return static::getMany($path);
    } */

    public static function find($id)
    {
        $path = static::getPath() . "/" . $id;
        return static::getQuery()->get($path)->first();
    }

    public static function where($key, $value)
    {
        return static::all()->where($key, $value);
    }

    public function getSites()
    {
        $matches = null;
        $sites = Site::all();
        foreach($sites as $site)
        {
            if($site->gatewaytemplate_id == $this->id)
            {
                $matches[] = $site;
            }
        }
        return collect($matches);
    }
}