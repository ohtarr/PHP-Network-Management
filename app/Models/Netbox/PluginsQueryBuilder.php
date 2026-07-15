<?php

namespace App\Models\Netbox;

use App\Models\Netbox\QueryBuilder;

class PluginsQueryBuilder extends QueryBuilder
{
    public $headers;
    public $search;
    public $model;

    public function buildUrl()
    {
        return env('NETBOX_BASE_URL') . "/api/plugins/" . $this->model->getApp() . "/" . $this->model->getModel() . "/";
    }

}
