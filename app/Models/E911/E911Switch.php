<?php

/*
small library for accessing "Gizmo" API in a Laravel-esque fashion.
/**/

namespace App\Models\E911;

use App\Models\E911\E911;
use \EmergencyGateway\EGW;

class E911Switch extends E911
{

    protected $table = 'layer2_switches';
    //primary_Key of model.
    public $primaryKey = "switch_id";

    protected $guarded = [];

    //Initialize the model with the BASE_URL from env.
    public static function init()
    {
        parent::init();
        static::$all_url = env('E911_SWITCH_URL');
        static::$soap_url = env('E911_SWITCH_SOAP_URL');
        static::$soap_wsdl = env('E911_SWITCH_SOAP_WSDL');
    }

    public static function add($ip, $vendor, $erl, $name)
    {

        $EGW = static::getEgw();

        $params = [
            'switch_ip'             =>  $ip,
            'switch_vendor'         =>  $vendor,
            'switch_erl'            =>  $erl,
            'switch_description'    =>  $name,
        ];

        try{
            $RESULT = $EGW->add_switch($params);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $RESULT;
    }

    public static function modify($ip, $vendor, $erl, $name)
    {

        $EGW = static::getEgw();

        $params = [
            'switch_ip'             =>  $ip,
            'switch_vendor'         =>  $vendor,
            'switch_erl'            =>  $erl,
            'switch_description'    =>  $name,
        ];

        try{
            $RESULT = $EGW->update_switch($params);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $RESULT;
    }

    public static function remove($ip)
    {

        $EGW = static::getEgw();

        try{
            $RESULT = $EGW->delete_switch($ip);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $RESULT;
    }

}
E911Switch::init();