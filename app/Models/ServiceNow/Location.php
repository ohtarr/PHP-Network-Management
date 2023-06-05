<?php

namespace App\Models\ServiceNow;

use ohtarr\ServiceNowModel;

class Location extends ServiceNowModel
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
}
