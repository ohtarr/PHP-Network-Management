<?php

namespace App\Collections;

use Illuminate\Database\Eloquent\Collection;

class DeviceCollection extends Collection 
{

    public function parsed()
    {
        return $this->transform(function ($item, $key) {
            return $item->parse();
        });
    }

    public function withoutData()
    {
        return $this->transform(function ($item, $key) {
            return $item->withoutData();
        });
    }

}