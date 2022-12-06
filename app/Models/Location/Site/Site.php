<?php

namespace App\Models\Location\Site;

use Illuminate\Database\Eloquent\Model;
use App\Models\ServiceNow\ServiceNowLocation;
use App\Models\ServiceNow\ServiceNowUser;
use App\Models\Location\Address\Address;
use App\Models\Location\Contact\Contact;
use App\Models\Location\Building\Building;
use App\Dhcp;
use App\Models\Location\Site\SiteCollection;
use App\Models\Device\Device;
use App\Models\Observium\Device as obsDevice;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class Site extends Model
{
    use HasRolesAndAbilities;

    protected $connection = 'mysql-whereuat';

    public $loc;

    protected $hidden = ['defaultBuilding'];

    public function newCollection(array $models = []) 
    { 
       return new SiteCollection($models); 
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    public function defaultBuilding()
    {
        return $this->hasOne(Building::class, 'id', 'default_building_id');
    }

    public function getContact()
    {
        return $this->defaultBuilding->contact;
    }

    public function getServiceNowLocation()
    {
        if(!$this->loc && $this->loc_sys_id) {
            $this->loc = ServiceNowLocation::find($this->loc_sys_id);
        }
        return $this->loc;
    }

    public function getAllRooms()
    {
        foreach($this->buildings as $building)
        {
            foreach($building->rooms as $room)
            {
                $array[] = $room;
            }

        }
        return collect($array);
    }

    public function getAddress()
    {
        return $this->defaultBuilding->getAddress();
    }

    public function getCoordinates()
    {
        $loc = $this->getServiceNowLocation();
        if($loc)
        {
            $coordinates = $loc->latitude . "," . $loc->longitude;
        }
        return $coordinates;
    }

    public function getBusinessContact()
    {
        $location = $this->getServiceNowLocation();
        return ServiceNowUser::find($location->contact['value']);
    }

    public function getItContact()
    {
        $location = $this->getServiceNowLocation();
        return ServiceNowUser::find($location->u_on_site_contact['value']);
    }

    public function get911Contact()
    {
        //return $this->contact;
        return $this->getContact();
    }

    public function syncAdd()
    {
        $this->syncAddress();
        $this->syncContact();
        $this->syncDefaultBuilding();
        return $this->refresh();
    }

    public function syncAddress()
    {
        if(!$this->addressIsSynced())
        {
            $address = $this->createOrUpdateAddress();
        } else {
            $address = $this->getAddress();
        }
        return $address;

    }

    public function getScopes()
    {
        return Dhcp::findSiteScopes($this->name);
    }

    public function syncContact()
    {
        print "Obtaining existing CONTACT...\n";
        $contact = $this->getContact();
        if(!$contact)
        {
            print "CONTACT doesn't exist, creating...\n";
            $contact = $this->createNewContact();
            if(!$contact)
            {
                $error = "Failed to create CONTACT!\n";
                print $error;
                throw new \Exception($error);
            }
            print "CONTACT with ID {$contact->id} was created...\n";
        }
        print "CONTACT with ID {$contact->id} was found...\n";
        return $contact;
    }

    public function syncDefaultBuilding()
    {
        print "Obtaining DEFAULT BUILDING...\n";
        $defaultBuilding = $this->defaultBuilding;
        if(!$defaultBuilding)
        {
            print "DEFAULT BUILDING was not found... creating new...\n";
            $defaultBuilding = $this->createDefaultBuilding();
            if(!$defaultBuilding)
            {
                $error = "Failed to get create DEFAULT BUILDING!\n";
                print $error;
                throw new \Exception($error);
            }
            print "DEFAULT BUILDING with ID {$defaultBuilding->id} was created...\n";
        }
        print "DEFAULT BUILDING with ID {$defaultBuilding->id} was found...\n";
        return $defaultBuilding;
    }

    public function createNewContact()
    {
        $econtact = $this->getServiceNowLocation()->get911Contact();
        if(!$econtact)
        {
            $error = "Failed to get ServiceNowLocation 911Contact!\n";
            print $error;
            throw new \Exception($error);
            return null;
        }
        $contact = new Contact;
        $contact->name = $this->name . "_911_CONTACT";
        $contact->description = "Default Contact created by system.";
        $contact->phone = preg_replace('/\D+/', '', $econtact->phone);
        $contact->email = $econtact->email;
        $contact->save();
        $defaultBuilding = $this->defaultBuilding;
        $defaultBuilding->contact_id = $contact->id;
        $defaultBuilding->save();
        return $contact;
    }

    public function createOrUpdateAddress()
    {
        $serviceNowLocation = $this->getServiceNowLocation();
        $defaultBuilding = $this->defaultBuilding;
        if(!$defaultBuilding)
        {
            return null;
        }
        if($serviceNowLocation)
        {
            if($this->getAddress())
            {
                $address = $this->getAddress();
            } else {
                $address = new Address;
            }
            foreach($address->snowAddressMapping as $addressKey => $snowKey)
            {
                $address->$addressKey = $serviceNowLocation->$snowKey;
            }
            $address->save();

            $defaultBuilding->address_id = $address->id;
            $defaultBuilding->save();

            return $address;
        }
    }

    public function createDefaultBuilding()
    {
        $building = new Building;
        $building->name = "DEFAULT_BUILDING";
        $building->description = "{$this->name}";
        $building->site_id = $this->id;
        $building->save();
        //$building->syncAdd();
        //$building->createDefaultRoom();
        $this->default_building_id = $building->id;
        $this->save();
        return $building;
    }

    public function addressIsSynced()
    {
        $address = $this->getAddress();
        $snowloc = $this->getServiceNowLocation();
        if(!$address || !$snowloc)
        {
            return false;
        }
        $matches = true;

        foreach($address->snowAddressMapping as $addressKey => $snowKey)
        {
            if($address->$addressKey != $snowloc->$snowKey)
            {
                $matches = false;
                break;
            }
        }

        return $matches;
    }

    public function getAllDevices()
    {
        $devices = Device::where('data->name', 'like', $this->name . '%')->get();
        return $devices;
    }

    public function isNetworkActive()
    {
        $loc = $this->getServiceNowLocation();
        if($loc)
        {
            if($loc['u_network_mob_date'] && !$loc['u_network_demob_date'])
            {
                return true;
            }
        }
        return false;
    }

}
