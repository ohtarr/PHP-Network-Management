<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

//use App\Queries\BaseQuery as Query;

class ControllerTemplate extends Controller
{
    public static $query = Query::class;
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
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
        $object = Model::create($request->all());
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
        $resourceCollection = static::$query::apply($request,$id);
        return new Resource($resourceCollection->collection->first());
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
        $object = Model::findOrFail($id);
		$object->update($request->all());
		return new Resource($object);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  id  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $object = Model::findOrFail($id);
		$object->delete();
		return new Resource($object);
    }
}
