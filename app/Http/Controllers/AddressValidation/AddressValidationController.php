<?php

namespace App\Http\Controllers\AddressValidation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AddressValidationController extends Controller
{
    public function validateAddress(Request $request)
    {
        $start = microtime(true);
        $POSTED = $request->collect();
        $RESPONSE = ['success' => false, 'error' => ''];

        // Stamp the total execution time and return the response as JSON
        $respond = function (int $status = 200) use (&$RESPONSE, $start) {
            $RESPONSE['time'] = round(microtime(true) - $start, 2);
            return response()->json($RESPONSE, $status);
        };

        //Required Parameters
        $params = [
            "street",
            "city",
            "state",
            "zip",
            "country",
        ];

        foreach ($params as $param){
            if($request->isNotFilled($param)){
                $RESPONSE['error'] = "Missing or empty parameter [{$param}]";
                return $respond(422);
            }
            if(preg_match('/^\s|\s{2,}|\s$|[\t\r\n]/', $POSTED[$param])){
                $RESPONSE['error'] = "[{$param}] has invalid white space (SPACE, TAB, OR CARRIAGE RETURNS)";
                return $respond(422);
            }
        }
        if($POSTED["country"] != "USA" && $POSTED["country"] != "CAN"){
            $RESPONSE['error'] = "[country] is not valid! (USA or CAN)";
        }
        if(!empty($RESPONSE['error'])){
            return $respond(422);
        }

        try{
            $addressobj = \EmergencyGateway\Address::fromString(
                $POSTED['street'],
                $POSTED['city'],
                $POSTED['state'],
                $POSTED['country'],
                $POSTED['zip'],
                $POSTED->get('name')
            );

            $EGW = new \EmergencyGateway\EGW(
                env("E911_ERL_SOAP_URL"),
                env("E911_ERL_SOAP_WSDL"),
                env("E911_SOAP_USER"),
                env("E911_SOAP_PASS"),
                ""
            );
            $RESPONSE['debug']['e911_submitted_address'] = $addressobj->__toArray();
            $RESPONSE['debug']['e911_response'] = (array) $EGW->validateAddress($addressobj);
        }catch (\Exception $e) {
            // catch exceptions as BAD data
            $RESPONSE['error'] = "Caught exception {$e->getMessage()}";
            return $respond(502);
        }

        if (($RESPONSE['debug']['e911_response']['status'] ?? null) == "0"){
            $RESPONSE['success'] = true;
            $RESPONSE['error'] = "";
        }
        return $respond();
    }
}
