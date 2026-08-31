<?php

namespace App\Models\ServiceNowV2;

use \GuzzleHttp\Client as GuzzleClient;
use \GuzzleHttp\Exception\ClientException;

class QueryBuilder
{
    protected $search = [];
    protected $limit;
    protected $offset;
    public $model;

    public function buildUrl()
    {
        return env('SNOWBASEURL') . '/' . $this->model->getTable();
    }

    public static function getGuzzleClient()
    {
        return new GuzzleClient([
            'auth' => [env('SNOWUSERNAME'), env('SNOWPASSWORD')],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function hydrateOne($data)
    {
        $object = new $this->model;
        foreach ($data as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }

    public function hydrateMany($results)
    {
        $objects = [];
        foreach ($results as $item) {
            $objects[] = $this->hydrateOne($item);
        }
        return collect($objects);
    }

    public function where($column, $operator = null, $value = null)
    {
        $argscount = count(func_get_args());
        if ($argscount == 1) {
            $this->search[] = $column;
        } elseif ($argscount == 2) {
            $this->search[] = $column . '=' . $operator;
        } else {
            $this->search[] = $column . $operator . $value;
        }
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function format_query()
    {
        $query = [];
        if (!empty($this->search)) {
            $query['sysparm_query'] = implode('^', $this->search);
        }
        if (isset($this->limit)) {
            $query['sysparm_limit'] = $this->limit;
        }
        if (isset($this->offset)) {
            $query['sysparm_offset'] = $this->offset;
        }
        return $query;
    }

    public function get()
    {
        $client = static::getGuzzleClient();
        $params = ['query' => $this->format_query()];
        try {
            $response = $client->request('GET', $this->buildUrl(), $params);
        } catch (ClientException $e) {
            //ServiceNow returns 404 (not 200 with an empty result) for a list query that matches zero rows.
            if ($e->getResponse()->getStatusCode() === 404) {
                return collect();
            }
            throw $e;
        }
        $body = json_decode($response->getBody()->getContents(), true);
        return $this->hydrateMany($body['result']);
    }

    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    public function find($id)
    {
        $client = static::getGuzzleClient();
        try {
            $response = $client->request('GET', $this->buildUrl() . '/' . $id);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
        $body = json_decode($response->getBody()->getContents(), true);
        if (empty($body['result'])) {
            return null;
        }
        return $this->hydrateOne($body['result']);
    }

    public function post($body)
    {
        $client = static::getGuzzleClient();
        $params = ['body' => json_encode($body)];
        $response = $client->request('POST', $this->buildUrl(), $params);
        $body = json_decode($response->getBody()->getContents(), true);
        return $this->hydrateOne($body['result']);
    }

    public function put($id, $body)
    {
        $client = static::getGuzzleClient();
        $params = ['body' => json_encode($body)];
        $response = $client->request('PUT', $this->buildUrl() . '/' . $id, $params);
        $body = json_decode($response->getBody()->getContents(), true);
        return $this->hydrateOne($body['result']);
    }

    public function patch($id, $body)
    {
        $client = static::getGuzzleClient();
        $params = ['body' => json_encode($body)];
        $response = $client->request('PATCH', $this->buildUrl() . '/' . $id, $params);
        $body = json_decode($response->getBody()->getContents(), true);
        return $this->hydrateOne($body['result']);
    }

    public function delete($id)
    {
        $client = static::getGuzzleClient();
        $response = $client->request('DELETE', $this->buildUrl() . '/' . $id);
        $statuscode = $response->getStatusCode();
        return $statuscode == 204 || $statuscode == 200;
    }
}
