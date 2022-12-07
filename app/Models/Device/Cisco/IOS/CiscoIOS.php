<?php

namespace App\Models\Device\Cisco\IOS;

use App\Models\Device\Cisco\Cisco;

class CiscoIOS extends Cisco
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    public $discover_commands = [
    ];

    public $discover_regex = [
    ];

    public $parser = "\ohtarr\Cisco\IOS\Parser";

}
