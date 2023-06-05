<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\Locations;
use App\Models\ServiceNow\Location\ServiceNowLocation;

class Sites extends BaseModel
{
    protected $app = "dcim";
    protected $model = "sites";

    public function formatAddress()
    {
        $address = [
            'street_number' =>  "",
            'street_predirectional' =>  "",
            'street_name' =>  "",
            'street_suffix' =>  "",
            'street_postdirectional' =>  "",
            'street2_secondaryunitindicator' =>  "",
            'street2_secondarynumber' =>  "",
            'city' =>  "",
            'state' =>  "",
            'postal_code' =>  "",
            'country' =>  "",
            'full'  =>  "",

        ];
        $line1 = "";
        $line2 = "";
        $line3 = "";
        if(isset($this->custom_fields->STREET_NUMBER))
        {
            $address['street_number'] = $this->custom_fields->STREET_NUMBER;
            $line1 .= $this->custom_fields->STREET_NUMBER . " ";
        }
        if(isset($this->custom_fields->STREET_PREDIRECTIONAL))
        {
            $address['street_predirectional'] = $this->custom_fields->STREET_PREDIRECTIONAL;
            $line1 .= $this->custom_fields->STREET_PREDIRECTIONAL . " ";
        }
        if(isset($this->custom_fields->STREET_NAME))
        {
            $address['street_name'] = $this->custom_fields->STREET_NAME;    
            $line1 .= $this->custom_fields->STREET_NAME . " ";
        }
        if(isset($this->custom_fields->STREET_SUFFIX))
        {
            $address['street_suffix'] = $this->custom_fields->STREET_SUFFIX; 
            $line1 .= $this->custom_fields->STREET_SUFFIX;
        }
        if(isset($this->custom_fields->STREET_POSTDIRECTIONAL))
        {
            $address['street_postdirectional'] = $this->custom_fields->STREET_POSTDIRECTIONAL;
            $line1 .= " " . $this->custom_fields->STREET_POSTDIRECTIONAL;
        }
        if(isset($this->custom_fields->STREET_SECONDARYUNITINDICATOR))
        {
            $address['street2_secondaryunitindicator'] = $this->custom_fields->STREET_SECONDARYUNITINDICATOR;
            $line2 .= $this->custom_fields->STREET_SECONDARYUNITINDICATOR . " ";
        }
        if(isset($this->custom_fields->STREET_SECONDARYNUMBER))
        {
            $address['street2_secondarynumber'] = $this->custom_fields->STREET_SECONDARYNUMBER;
            $line2 .= $this->custom_fields->STREET_SECONDARYNUMBER;
        }
        if(isset($this->custom_fields->CITY))
        {
            $address['city'] = $this->custom_fields->CITY;
            $line3 .= $this->custom_fields->CITY . ", ";
        }
        if(isset($this->custom_fields->STATE))
        {
            $address['state'] = $this->custom_fields->STATE;
            $line3 .= $this->custom_fields->STATE . " ";
        }
        if(isset($this->custom_fields->POSTAL_CODE))
        {
            $address['postal_code'] = $this->custom_fields->POSTAL_CODE;
            $line3 .= $this->custom_fields->POSTAL_CODE;
        }
        if(isset($this->custom_fields->COUNTRY))
        {
            $address['country'] = $this->custom_fields->COUNTRY;
        }
        $full = "";
        if($line1)
        {
            $full .= $line1 . PHP_EOL;
        }
        if($line2)
        {
            $full .= $line2 . PHP_EOL;
        }
        if($line3)
        {
            $full .= $line3;
        }
        $address['full'] = $full;
        return $address;
    }

    public function addressExists()
    {
        if(
            $this->custom_fields->STREET_NAME &&
            $this->custom_fields->CITY &&
            $this->custom_fields->STATE &&
            $this->custom_fields->POSTAL_CODE &&
            $this->custom_fields->COUNTRY
        )
        {
            return true;
        }
    }

    public function address()
    {
        if($this->addressExists())
        {
            return $this->formatAddress();
        }
    }

    public function coordinates()
    {
        if($this->latitude && $this->longitude)
        {
            return [
                'latitude'  =>  $this->latitude,
                'longitude' =>  $this->longitude,
            ];
        }
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            return true;
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            return true;
        }
        return false;
    }

    public function prefixes()
    {
        $prefixes = new Prefixes($this->query);
        return $prefixes->where('site_id', $this->id)->get();
    }

    public function locations()
    {
        $locations = new Locations($this->query);
        return $locations->where('site_id', $this->id)->get();
    }

    public function getServiceNowLocationByName()
    {
        $snowloc = ServiceNowLocation::where('name', $this->name)->first();
        if($snowloc)
        {
            return $snowloc;
        }        
    }

    public function getServiceNowLocationById()
    {
        if(isset($this->custom_fields->SNOW_SYSID))
        {
            $snowloc = ServiceNowLocation::find($this->custom_fields->SNOW_SYSID);
            if($snowloc)
            {
                return $snowloc;
            }
        }
    }

    public function getServiceNowLocation()
    {
        $snowloc = $this->getServiceNowLocationById();
        if(!$snowloc)
        {
            $snowloc = $this->getServiceNowLocationByName();
        }
        return $snowloc;
    }
    
}