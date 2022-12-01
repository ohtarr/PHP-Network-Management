<?php

//Example Model to place in your App folder.

namespace App\Models\ServiceNow;

use ohtarr\ServiceNowModel;
use GuzzleHttp\Client as GuzzleHttpClient;
use App\Models\Location\Address;
use App\Models\Location\Site;
use App\Models\Location\Contact;
use App\Models\ServiceNow\ServiceNowUser;
use App\Models\Location\Building;
use App\Models\Location\Room;

class ServiceNowLocation extends ServiceNowModel
{
	protected $guarded = [];

    public $table = "cmn_location";

    public $cache;

    public function __construct(array $attributes = [])
    {
        $this->snowbaseurl = env('SNOWBASEURL'); //https://mycompany.service-now.com/api/now/v1/table
        $this->snowusername = env("SNOWUSERNAME");
        $this->snowpassword = env("SNOWPASSWORD");
		parent::__construct($attributes);
    }

    public function getBusinessContact()
    {
        if($this->contact)
        {
            return ServiceNowUser::find($this->contact['value']);
        }
    }

    public function getSite()
    {
        return Site::where('loc_sys_id',$this->sys_id)->first();
    }

    public function getItContact()
    {
        if($this->u_on_site_contact)
        {
            return ServiceNowUser::find($this->u_on_site_contact['value']);
        }
    }

    public function getItManagerContact()
    {
        if($this->u_field_support_manager)
        {
            return ServiceNowUser::find($this->u_field_support_manager['value']);
        }
    }

    public function get911Contact()
    {
        if($contact = $this->getBusinessContact())
        {
            return $contact;
        }

        if ($contact = $this->getItContact()){
            return $contact;
        }

        if ($contact = $this->getItManagerContact()){
            return $contact;
        }
    }

    public function syncSite()
    {
        print "SerivceNowLocation::syncSite()\n";
        //Check if site exists, if so blow up
        $site = $this->getSite();

        if($site)
        {
            print "Existing SITE with ID {$site->id} found.\n";
            if($site->name != $this->name)
            {
                $site->name = $this->name;
                $site->save();
            }
        } else {
            print "SITE not found.  Creating new SITE....";
            $site = $this->createNewSite();
            if($site)
            {
                print "SITE with ID " . $site->id . " created.\n";
            } else {
                print "FAILED to create SITE...\n";
            }
        }

        //$site->syncAdd();
        return $site;
    }


    public function createNewSite()
    {
        $site = new Site;
        $site->name = $this->name;
        $site->loc_sys_id = $this->sys_id;
        $site->save();
        return $site;
    }

    public function hasValidFields()
    {
        $valid = 1;

        if(!$this->latitude)
        {
            $valid = 0;
        }
        if(!$this->longitude)
        {
            $valid = 0;
        }
        if(!$this->country)
        {
            $valid = 0;
        }
        
        if($valid == 1)
        {
            return true;
        } else {
            return false;
        }
    }

    public function cacheAll($force = false)
    {
        if($force || !$this->cache)
        {
            $this->cache = $this->all();
        }
        return $this->cache;
    }

}
