<?php

namespace App\Models\Gizmo\DNS;

use App\Models\Gizmo\DNS\DnsBaseModel;

class Ptr extends DnsBaseModel
{
    protected static $path = "/api/dns/ptr";
}
