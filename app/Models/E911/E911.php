<?php

/*
Library for accessing E911 Gateway
/**/

namespace App\Models\E911;

use Illuminate\Database\Eloquent\Model;
use \EmergencyGateway\EGW;

class E911 extends Model
{
    //primary_Key of model.
    public $username;
    public $password;
    public $snmp_community;
    public $soap_url;
    public $soap_wsdl;
    //url suffix to access ALL endpoint
    public $all_url = "";

    protected $connection = 'mysql-E911';

    protected $guarded = [];

    public $where = [];

/*     public static function init()
    {
        static::$username = env('E911_SOAP_USER');
        static::$password = env('E911_SOAP_PASS');
        static::$snmp_community = env('E911_SNMP_RW');
    } */

    public function __construct()
    {
        $this->username = env('E911_SOAP_USER');
        $this->password = env('E911_SOAP_PASS');
        $this->snmp_community = env('E911_SNMP_RW');
    }

    public function getEgw()
    {
        return new EGW(
            $this->soap_url,
            $this->soap_wsdl,
            $this->username,
            $this->password,
            $this->snmp_community,
        );
    }

}