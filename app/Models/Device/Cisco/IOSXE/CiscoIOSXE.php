<?php

namespace App\Models\Device\Cisco\IOSXE;

use App\Models\Device\Cisco\Cisco;

class CiscoIOSXE extends Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    public $parser = "\ohtarr\Cisco\IOS\Parser";
 
}
