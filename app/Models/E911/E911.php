<?php

/*
small library for accessing "Gizmo" API in a Laravel-esque fashion.
/**/

namespace App\Models\E911;

use Illuminate\Database\Eloquent\Model;
use \EmergencyGateway\EGW;

class E911 extends Model
{
    //primary_Key of model.
    //public static $username;
    //public static $password;
    //public static $snmp_community;
    //public static $soap_url;
    //public static $soap_wsdl;
    //url suffix to access ALL endpoint
    public static $all_url = "";

    protected $connection = 'mysql-E911';

    protected $guarded = [];

    public $where = [];

    public static function init()
    {
        static::$username = env('E911_SOAP_USER');
        static::$password = env('E911_SOAP_PASS');
        static::$snmp_community = env('E911_SNMP_RW');
    }

    public static function getEgw()
    {
        return new EGW(
            static::$soap_url,
            static::$soap_wsdl,
            static::$username,
            static::$password,
            static::$snmp_community
        );
    }

}