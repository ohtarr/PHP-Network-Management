<?php

namespace App\Models\ServiceNowV2;

#[\AllowDynamicProperties]
class Location extends BaseModel
{
    protected static $table = 'cmn_location';

    public static function getQuery()
    {
        return parent::getQuery()->where('companyISNOTEMPTY');
    }
}
