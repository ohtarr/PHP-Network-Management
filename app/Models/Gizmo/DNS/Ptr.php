<?php

namespace App\Models\Gizmo\DNS;

use App\Models\Gizmo\DNS\DnsBaseModel;

class Ptr extends DnsBaseModel
{
    public static function getType()
    {
        return "ptr";
    }
}
