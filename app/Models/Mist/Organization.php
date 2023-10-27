<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use App\Models\Mist\NetworkTemplate;
use App\Models\Mist\WlanTemplate;
use App\Models\Mist\GatewayTemplate;
use App\Models\Mist\RfTemplate;

class Organization extends BaseModel
{
    public static function get()
    {
        $path = "orgs/" . static::getOrgId();
        return static::hydrateOne(static::getQuery()->first($path));
    }

    public static function getSettings()
    {
        $qb = static::getQuery();
        return $qb->get("orgs/" . static::getOrgId() . "/setting");
    }

    public static function getAdmins()
    {
        $qb = static::getQuery();
        return $qb->get("orgs/" . static::getOrgId() . "/admins");
    }

    public static function getLicenses()
    {
        $qb = static::getQuery();
        return $qb->get("orgs/" . static::getOrgId() . "/licenses");
    }

    public static function getSites()
    {
        return Site::all();
    }

    public static function getInventory()
    {
        return Device::all();
    }

    public static function getRfTemplates()
    {
        return RfTemplate::all();
    }

    public static function getRfTemplate($id)
    {
        return RfTemplate::find($id);
    }

    public static function getNetworkTemplates()
    {
        return NetworkTemplate::all();
    }

    public static function getNetworkTemplate($id)
    {
        return NetworkTemplate::find($id);
    }

    public static function getGatewayTemplates()
    {
        return GatewayTemplate::all();
    }

    public static function getGatewayTemplate($id)
    {
        return GatewayTemplate::find($id);
    }

    public static function getWlanTemplates()
    {
        return WlanTemplate::all();
    }

    public static function getWlanTemplate($id)
    {
        return WlanTemplate::find($id);
    }
}