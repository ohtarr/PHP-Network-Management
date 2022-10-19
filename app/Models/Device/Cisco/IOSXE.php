<?php

namespace App\Models\Device\Cisco;

class IOSXE extends \App\Models\Device\Cisco\Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    public $parser = "\ohtarr\Cisco\IOS\Parser";
 
}
