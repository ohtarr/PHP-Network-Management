<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

class Locations extends BaseModel
{
    protected $app = "dcim";
    protected $model = "locations";

    public function coordinates()
    {
        if(isset($this->custom_fields->LATITUDE) && isset($this->custom_fields->LONGITUDE))
        {
            return [
                'latitude'  =>  $this->custom_fields->LATITUDE,
                'longitude' =>  $this->custom_fields->LONGITUDE,
            ];
        } else {
            $parent = $this->parent();
            if(isset($parent->id))
            {
                return $parent->coordinates();
            } else {
                return $this->site()->coordinates();
            }
        }
    }

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

        ];
        if(isset($this->custom_fields->STREET_NUMBER))
        {
            $address['street_number'] = $this->custom_fields->STREET_NUMBER;   
        }
        if(isset($this->custom_fields->STREET_PREDIRECTIONAL))
        {
            $address['street_predirectional'] = $this->custom_fields->STREET_PREDIRECTIONAL;
        }
        if(isset($this->custom_fields->STREET_NAME))
        {
            $address['street_name'] = $this->custom_fields->STREET_NAME;    
        }
        if(isset($this->custom_fields->STREET_SUFFIX))
        {
            $address['street_suffix'] = $this->custom_fields->STREET_SUFFIX; 
        }
        if(isset($this->custom_fields->STREET_POSTDIRECTIONAL))
        {
            $address['street_postdirectional'] = $this->custom_fields->STREET_POSTDIRECTIONAL;
        }
        if(isset($this->custom_fields->STREET_SECONDARYUNITINDICATOR))
        {
            $address['street2_secondaryunitindicator'] = $this->custom_fields->STREET_SECONDARYUNITINDICATOR;
        }
        if(isset($this->custom_fields->STREET_SECONDARYNUMBER))
        {
            $address['street2_secondarynumber'] = $this->custom_fields->STREET_SECONDARYNUMBER;
        }
        if(isset($this->custom_fields->CITY))
        {
            $address['city'] = $this->custom_fields->CITY;
        }
        if(isset($this->custom_fields->STATE))
        {
            $address['state'] = $this->custom_fields->STATE;
        }
        if(isset($this->custom_fields->POSTAL_CODE))
        {
            $address['postal_code'] = $this->custom_fields->POSTAL_CODE;
        }
        if(isset($this->custom_fields->COUNTRY))
        {
            $address['country'] = $this->custom_fields->COUNTRY;
        }
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
        } else {
            if($this->parent())
            {
                return $this->parent()->address();
            } else {
                return $this->site()->address();
            }
        }
    }

    public function devices()
    {
        return Devices::where('location_id', $this->id)->get();
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            if($parent = $this->parent())
            {
                return $parent->polling();
            } else {
                return $this->site()->polling();
            }                        
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            if($parent = $this->parent())
            {
                return $parent->alerting();
            } else {
                return $this->site()->alerting();
            }                        
        }
        return false;
    }

}