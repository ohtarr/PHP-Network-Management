<?php

namespace App\Models\LibreNMS;

class SearchBuilder
{
    public $search;
    public $model;

    public function where($column, $value)
    {
        $this->search[$column] = $value;
        return $this;
    }

    public function get($path = null)
    {
        //$this->model->setSearch($this->search);
        return $this->model->get($path, $this->search);
    }

    public function first($path = null)
    {
        return $this->model->first($path, $this->search);
    }
}
