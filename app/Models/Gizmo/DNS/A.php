<?php

namespace App\Models\Gizmo\DNS;

use App\Models\Gizmo\DNS\DnsBaseModel;

class A extends DnsBaseModel
{
    public static function getType()
    {
        return "a";
    }
}
