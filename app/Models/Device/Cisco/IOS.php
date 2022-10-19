<?php

namespace App\Models\Device\Cisco;

class IOS extends \App\Models\Device\Cisco\Cisco
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
