<?php

//Example Model to place in your App folder.

namespace App\Models\ServiceNow;

use ohtarr\ServiceNowModel;
use GuzzleHttp\Client as GuzzleHttpClient;

class ServiceNowUser extends ServiceNowModel
{
	protected $guarded = [];

	public $table = "sys_user";

    public $cache;

    public function __construct(array $attributes = [])
    {
        $this->snowbaseurl = env('SNOWBASEURL'); //https://mycompany.service-now.com/api/now/v1/table
        $this->snowusername = env("SNOWUSERNAME");
        $this->snowpassword = env("SNOWPASSWORD");
		parent::__construct($attributes);
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
