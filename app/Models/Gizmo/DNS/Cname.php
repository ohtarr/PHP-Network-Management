<?php

namespace App\Models\Gizmo\DNS;

use App\Models\Gizmo\DNS\DnsBaseModel;

class Cname extends DnsBaseModel
{
    public static function getType()
    {
        return "cname";
    }
}
