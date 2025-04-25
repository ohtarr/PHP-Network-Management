<?php

namespace App\Models\ServiceNow;

use ohtarr\ServiceNowModel;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\Regions;
use App\Models\Netbox\DCIM\SiteGroups;

class Location extends ServiceNowModel
{
	protected $guarded = [];

    public $table = "cmn_location";

    public $cache;

    private $netbox_site_map = [
		'name'			=>	'name',
		'u_description'	=>	'description',
		'latitude'		=>	'latitude',
		'longitude'		=>	'longitude',
		'time_zone'		=>	'time_zone',
	];

	private $netbox_address_map = [
		'sys_id'					=>	'SNOW_SYSID',
		'u_street_number'			=>	'STREET_NUMBER',
		'u_street_predirectional'	=>	'STREET_PREDIRECTIONAL',
		'u_street_name'				=>	'STREET_NAME',
		'u_street_suffix'			=>	'STREET_SUFFIX',
		'u_street_postdirectional'	=>	'STREET_POSTDIRECTIONAL',
		'u_secondary_unit_indicator'=>	'STREET2_SECONDARYUNITINDICATOR',
		'u_secondary_number'		=>	'STREET2_SECONDARYNUMBER',
		'city'						=>	'CITY',
		'state'						=>	'STATE',
		'zip'						=>	'POSTAL_CODE',
		'country'					=>	'COUNTRY',
		'u_priority'				=>	'PRIORITY',
	];

    private $netbox_sitegroup_map = [
		'Area Office Site'			=>	'AREA_OFFICE',
		'Corporate_Office_Site'		=>	'CORPORATE_OFFICE',
		'Data_Center_Site'			=>	'DATA_CENTER',
		'District_Office_Site'		=>	'DISTRICT_OFFICE',
		'Job_Site'					=>	'JOBSITE',
		'Joint Venture Site'		=>	'JOBSITE',
		'Joint_Venture_Site'		=>	'JOBSITE',
		'Shared_Office'				=>	'SHARED_OFFICE',
		'Vessel_Site'				=>	'VESSEL',
		'Barges'					=>	'VESSEL',
		'Dredge - Booster'			=>	'VESSEL',
		'Dredge - Bucket'			=>	'VESSEL',
		'Dredge - Cutter'			=>	'VESSEL',
		'Dredge - Hopper'			=>	'VESSEL',
		'Dredge - Specialty'		=>	'VESSEL',
		'Floting Cranes'			=>	'VESSEL',
		'Dredge - Specialty'		=>	'VESSEL',
		'Tug_Site'					=>	'VESSEL',
		'Shop_Site'					=>	'SHOP_SITE',
		'Yard_Site'					=>	'YARD_SITE',
	];

    public function __construct(array $attributes = [])
    {
        $this->snowbaseurl = env('SNOWBASEURL'); //https://mycompany.service-now.com/api/now/v1/table
        $this->snowusername = env("SNOWUSERNAME");
        $this->snowpassword = env("SNOWPASSWORD");
		parent::__construct($attributes);
    }

    public static function all($columns = [])
    {
        $model = new static;
        return $model->where('companyISNOTEMPTY')->get();
    }

    public static function allActive()
    {
        $model = new static;
        return $model->where('companyISNOTEMPTY')->where('u_network_mob_dateISNOTEMPTY')->where('u_network_demob_dateISEMPTY')->get();
    }

	public function getNetboxRegion()
	{
		$district = substr($this->name,0,3);
        $region = Regions::where('name', $district)->first();
        return $region;
	}

    public function getNetboxSiteGroup()
	{
        $sitegroupname = "JOBSITE";
        foreach($this->netbox_sitegroup_map as $snowkey => $netboxkey)
        {
            if($this->u_site_type == $snowkey)
            {
                $sitegroupname = $netboxkey;
                break;
            }
        }
        return SiteGroups::where('name', $sitegroupname)->first();
	}

    public function generateNetboxSiteParams()
	{
		$region = $this->getNetboxRegion();
		if(!isset($region->id))
		{
			print "No Region found!" . PHP_EOL;
			return null;
		} else {
            $body['region'] = $region->id;
        }
		
		$group = $this->getNetboxSiteGroup();
		if(!isset($group->id))
		{
			print "No Group found!" . PHP_EOL;
			return null;
		} else {
            $body['group'] = $group->id;
        }

		foreach($this->netbox_site_map as $snowkey => $nbxkey)
		{
			if($nbxkey == 'latitude' || $nbxkey == 'longitude')
			{
				$body[$nbxkey] = number_format($this->$snowkey,6);
			} elseif($nbxkey == 'time_zone'){
				if(!$this->$snowkey)
				{
					$body[$nbxkey] = "UTC";
				}
			} else {
				$body[$nbxkey] = $this->$snowkey;
			}
		}
		
		foreach($this->netbox_address_map as $snowkey => $nbxkey)
		{
			$body['custom_fields'][$nbxkey] = $this->$snowkey;
		}
		$body['slug'] = strtolower($this->name);
		return $body;
	}

    public function getNetboxSiteByName()
    {
        return Sites::where('name__ie', $this->name)->first();
    }

    public function getNetboxSiteBySysid()
    {
        return Sites::where('cf_SNOW_SYSID', $this->sys_id)->first();
    }

    public function getNetboxSite()
    {
        $nbxsite = $this->getNetboxSiteBySysid();
        if(!isset($nbxsite->id))
        {
            $nbxsite = $this->getNetboxSiteByName();
        }
        if(!isset($nbxsite->id))
        {
            return null;
        }
        return $nbxsite;
    }

    public function createNetboxSite()
    {
        $exists = $this->getNetboxSite();
        if($exists)
        {
            return null;
        }
        $params = $this->generateNetboxSiteParams();
        $nbxsite = Sites::create($params);
        return $nbxsite;
    }
}
