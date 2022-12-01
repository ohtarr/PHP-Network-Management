<?php

namespace App\Models\Location\Room;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gizmo\TeamsLocation;
use App\Models\Location\Room\RoomCollection;
use App\Models\Location\Building\Building;
use App\Models\TMS\TMS;
use App\Models\E911\E911Erl;

class Room extends Model
{

    protected $connection = 'mysql-whereuat';

    protected $casts = [
        'data'  =>  'json',
    ];

    public function newCollection(array $models = []) 
    { 
       return new RoomCollection($models); 
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function getAddress()
    {
        return $this->building->getAddress();
    }

    public function get911Contact()
    {
        return $this->building->get911Contact();
    }

    public function getCoordinates()
    {
        return $this->building->getCoordinates();
    }

    public function getTeamsLocation()
    {
        return TeamsLocation::find($this->teams_location_id);
    }

    public function isDefaultRoomInDefaultBuilding()
    {
        if(($this->building->default_room_id == $this->id) && ($this->building->site->default_building_id == $this->building->id))
        {
            return true;
        } else {
            return false;
        }
    }

    public function isDefaultRoom()
    {
        if($this->building->default_room_id == $this->id)
        {
            return true;
        } else {
            return false;
        }
    }

 /*    public function syncTeamsLocation()
    {
        print "ROOM:syncTeamsLocation()\n";
        $teamsLocationId = $this->teams_location_id;
        if(!$teamsLocationId)
        {
            print "TEAMS LOCATION ID not found....\n";
            if($this->isDefaultRoomInDefaultBuilding())
            {
                print "DEFAULT ROOM of DEFAULT BUILDING for site {$this->building->site->name} detected...  finding TEAMS DEFAULT LOCATION...\n";
                $civic = $this->getAddress()->getTeamsCivic();
                if($civic)
                {
                    $location = $civic->getTeamsDefaultLocation();
                } else {
                    $error = "Failed to obtain TEAMS CIVIC!\n";
                    print $error;
                    throw new \Exception($error);                    
                }

                if(!$location)
                {
                    $error = "Failed to obtain TEAMS DEFAULT LOCATION!\n";
                    print $error;
                    throw new \Exception($error);
                } else {
                    $teamsLocationId = $location->locationId;
                    print "TEAMS DEFAULT LOCATION with ID {$teamsLocationId} found...\n";
                    $this->teams_location_id = $teamsLocationId;
                    $this->save();
                }
            } else {
                print "Creating a new TEAMS LOCATION...\n";
                $teamsLocationId = $this->createTeamsLocation();
            }
        }
        return $teamsLocationId;
    } */

    public function createTeamsLocation()
    {
        $civicId = $this->getAddress()->teams_civic_id;
        if(!$civicId)
        {
            $error = "Failed to obtain TEAMS CIVIC ID!\n";
            print $error;
            throw new \Exception($error);
        }
        $teamsloc = new TeamsLocation;
        $teamsloc->civicAddressId = $civicId;
        $teamsloc->location = $this->building->site->name . " - " . $this->building->name . " - " . $this->name;
        $teamsLocationId = $teamsloc->save();
        $this->teams_location_id = $teamsLocationId;
        $this->save();
        return $teamsLocationId;
    }

    public function getE911Erl()
    {
        $erlname = $this->building->site->name . "_" . $this->id;
        return E911Erl::all()->where('erl_id',$erlname)->first();
    }

    public function getE911ErlLoc()
    {
        $return = "";
        $street2 = $this->getAddress()->getStreet2Attribute();
        if($street2)
        {
            $return .= $street2 . " - ";
        }
        $return .= $this->building->name . " - " . $this->name;
        $return = substr($return, 0, 50);
        return $return;
    }

    public function getErlName()
    {
        return $this->building->site->name . "_" . $this->id;        
    }

    public function addE911Erl()
    {
        $erlname = $this->getErlName();
        $rmaddress = $this->getAddress();
        $number = null;

        $address = [
            "LOC"       => $this->getE911ErlLoc(),
            "HNO"       => $rmaddress->street_number,
            "PRD"       => $rmaddress->predirectional,
            "RD"        => $rmaddress->street_name,
            "STS"       => $rmaddress->street_suffix,
            "POD"       => $rmaddress->postdirectional,
            "A3"        => $rmaddress->city,
            "A1"        => $rmaddress->state,
            //"country"   => $rmaddress::iso3166ToAlpha3($rmaddress->country),
            "country"   => $rmaddress->country,            
            "PC"        => $rmaddress->postal_code,
        ];

        if($address['country'] == "CAN")
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
            $number = $elin['number'];
        }

        E911Erl::add($erlname, $address, $number);
        $erl = $this->getE911Erl();
        return $erl;
    }

    public function getTMSElin()
    {
        $tms = new TMS(env('TMS_URL'),env('TMS_USERNAME'),env('TMS_PASSWORD'));
        $elins = $tms->getCaElins();
        $elin = $elins->where('name',$this->getErlName())->first();
        if($elin)
        {
            return $elin;
        }
    }

    public function reserveElin()
    {
        $tms = new TMS(env('TMS_URL'),env('TMS_USERNAME'),env('TMS_PASSWORD'));
        return $tms->reserveCaElin($this->getErlName());
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
    }


}
