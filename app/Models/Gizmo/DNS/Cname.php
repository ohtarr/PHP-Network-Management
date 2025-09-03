<?php

namespace App\Models\Gizmo\DNS;

use App\Models\Gizmo\DNS\DnsBaseModel;

class Cname extends DnsBaseModel
{
    protected static $path = "/api/dns/cname";
}
