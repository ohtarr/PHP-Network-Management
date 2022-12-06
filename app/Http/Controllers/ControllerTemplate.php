<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

//use App\Queries\BaseQuery as Query;

class ControllerTemplate extends Controller
{
/*     public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class; */

    public static $model;
    public static $query;
    public static $resource;

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
		if ($user->cant('read', static::$model)) {
			abort(401, 'You are not authorized');
        }
        //Apply proper queries and retrieve a ResourceCollection object.
        $resourceCollection = static::$query::apply($request);
        return $resourceCollection;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = auth()->user();
		if ($user->cant('create', static::$model)) {
			abort(401, 'You are not authorized');
        }
        $object = static::$model::create($request->all());
        return $object;
    }

    /**
     * Display the specified resource.
     *
     * @param  id  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $user = auth()->user();
		if ($user->cant('read', static::$model)) {
			abort(401, 'You are not authorized');
        }
        $resourceCollection = static::$query::apply($request,$id);
        return new static::$resource($resourceCollection->collection->first());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  id  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
		if ($user->cant('update', static::$model)) {
			abort(401, 'You are not authorized');
        }
        $object = static::$model::findOrFail($id);
		$object->update($request->all());
		return new static::$resource($object);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  id  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth()->user();
		if ($user->cant('delete', static::$model)) {
			abort(401, 'You are not authorized');
        }
        $object = static::$model::findOrFail($id);
		$object->delete();
		return new static::$resource($object);
    }
}
