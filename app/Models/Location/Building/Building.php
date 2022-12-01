<?php

namespace App\Models\Location\Building;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\Location\Room\Room;
use App\Models\Location\Address\Address;
use App\Models\Location\Site\Site;
use App\Models\Location\Contact\Contact;
use App\Models\Location\Building\BuildingCollection;

class Building extends Model
{

    protected $connection = 'mysql-whereuat';
    //protected $hidden = ['Address'];

    public function newCollection(array $models = []) 
    { 
       return new BuildingCollection($models); 
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function Address()
    {
        return $this->belongsTo(Address::class);
    }
    
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function defaultRoom()
    {
        return $this->hasOne(Room::class,'id','default_room_id');
    }

    public function getAddress()
    {
        if($this->Address)
        {
            return $this->Address;
        } elseif($this->isDefaultBuilding()) {
            return null;
        }
        return $this->site->defaultBuilding->getAddress();
    }

    public function isDefaultBuilding()
    {
        if($this->site->defaultBuilding->id == $this->id)
        {
            return true;
        }
    }

    public function getDefaultRoom()
    {
        return Room::find($this->default_room_id);
    }

    public function get911Contact()
    {
        $bldgcontact = $this->contact;
        if($bldgcontact)
        {
            return $bldgcontact;
        }
        $sitecontact = $this->site->getContact();
        if($sitecontact)
        {
            return $sitecontact;
        }
    }

    public function getCoordinates()
    {
        if($this->lat && $this->lon)
        {
            $coordinates = $this->lat . "," . $this->lon;
        }
        $loc = $this->site->getServiceNowLocation();
        if($loc)
        {
            $coordinates = $loc->latitude . "," . $loc->longitude;
            return $coordinates;
        }
    }

    public function syncDefaultRoom()
    {
        $msg = get_class() . "::" . __FUNCTION__ . "\n";
        print $msg;
        Log::info($msg);
        $defaultRoom = $this->defaultRoom;
        if(!$defaultRoom)
        {
            print "DEFAULT ROOM not found, creating new...\n";
            $defaultRoom = $this->createDefaultRoom();
            if(!$defaultRoom)
            {
                throw new \Exception("Failed to create DEFAULT ROOM!");
            }
            print "DEFAULT ROOM with ID {$defaultRoom->id} was created...\n";
        }
        print "DEFAULT ROOM with ID {$defaultRoom->id} was found...\n";
        return $defaultRoom;
    }

    public function createDefaultRoom()
    {
        $room = new Room;
        $room->name = "DEFAULT_ROOM";
        $room->description = "Default Room created for site {$this->site->name}";
        $room->building_id = $this->id;
        $room->save();
        $this->default_room_id = $room->id;
        $this->save();
        return $room;
    }

}
