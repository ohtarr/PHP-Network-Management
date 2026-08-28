<?php

namespace App\Http\Controllers\ServiceNowV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ControllerTemplate extends Controller
{
    public static $model;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $params = $request->except(['limit', 'offset', 'sysparm_query']);

        $query = null;
        if ($request->has('sysparm_query')) {
            $query = static::$model::where($request->query('sysparm_query'));
        }
        foreach ($params as $key => $value) {
            $query = $query ? $query->where($key, $value) : static::$model::where($key, $value);
        }
        if (!$query) {
            $query = static::$model::getQuery();
        }

        if ($request->has('limit')) {
            $query->limit((int) $request->query('limit'));
        }
        if ($request->has('offset')) {
            $query->offset((int) $request->query('offset'));
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $object = static::$model::find($id);

        if (!$object) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        return response()->json($object);
    }

    public function store(Request $request)
    {
        $object = static::$model::create($request->all());

        return response()->json($object, 201);
    }

    public function update(Request $request, $id)
    {
        $object = static::$model::find($id);

        if (!$object) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $updated = $object->update($request->all());

        return response()->json($updated);
    }

    public function destroy($id)
    {
        $object = static::$model::find($id);

        if (!$object) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $object->delete();

        return response()->json(['message' => 'Record deleted successfully']);
    }
}
