<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class SiteGroups extends BaseModel
{
    protected $app = "dcim";
    protected $model = "site-groups";

    private $snow_map = [
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
}