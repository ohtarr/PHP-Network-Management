<?php

/*
Library for accessing E911 GATEWAY ERLs
/**/

namespace App\Models\E911;

use App\Models\E911\E911;
//use \EmergencyGateway\EGW;
use App\Models\TMS\TMS;

class E911Erl extends E911
{

    protected $table = 'locations';
    //primary_Key of model.
    public $primaryKey = "location_id";

    protected $guarded = [];

    public function __construct()
    {
        parent::__construct();
        $this->all_url = env('E911_ERL_URL');
        $this->soap_url = env('E911_ERL_SOAP_URL');
        $this->soap_wsdl = env('E911_ERL_SOAP_WSDL');
    }

    public function add($name, array $address, $elin = null)
    {

        /* $address format
            [
                "LOC" => "ste 25",
                "HNO" => "123",
                "RD" => "test st",
                "A3" => "Omaha",
                "A1" => "NE",
                "country" => "us",
                "PC" => "68137",
            ]
        */
        $EGW = $this->getEgw();

/*         if($address['country'] == "CAN")
        {
            $elin = $this->getTMSElin();
            if(!$elin)
            {
                $elin = $this->reserveElin();
            }
            if(!$elin)
            {
                throw \Exception('Unable to find an ELIN for Canadian site!');
            }
        }
        print_r($elin); */
        try{
            $RESULT = $EGW->addERL($name,$address,$elin);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $RESULT;
    }

    public function remove($name)
    {

        $EGW = $this->getEgw();

        try{
            $RESULT = $EGW->deleteERL($name);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $RESULT;
    }

/*     public function getTMSElin()
    {
        $tms = new TMS(env('TMS_URL'),env('TMS_USERNAME'),env('TMS_PASSWORD'));
        $elins = $tms->getCaElins();
        $elin = $elins->where('name',$this->erl_id)->first();
        if($elin)
        {
            return $elin;
        }
    }

    public function reserveElin()
    {
        $tms = new TMS(env('TMS_URL'),env('TMS_USERNAME'),env('TMS_PASSWORD'));
        return $tms->reserveCaElin($this->erl_id);
    }

    public function releaseElin()
    {
        $elin = $this->getTMSElin();
        if(!$elin)
        {
            return null;
        }
        $tms = new TMS(env('TMS_URL'),env('TMS_USERNAME'),env('TMS_PASSWORD'));
        return $tms->releaseCaElin($elin['id']);
    } */

}