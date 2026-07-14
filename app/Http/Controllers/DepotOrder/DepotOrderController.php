<?php

namespace App\Http\Controllers\DepotOrder;

use App\Http\Controllers\Controller;
use App\Models\DepotOrder\DepotOrder;
use Illuminate\Http\Request;

class DepotOrderController extends Controller
{
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
        $perPage = (int) $request->get('per_page', 25);

        return response()->json(DepotOrder::orderBy('id', 'desc')->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $depotOrder = DepotOrder::create($request->all());

        return response()->json($depotOrder, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return response()->json(DepotOrder::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $depotOrder = DepotOrder::findOrFail($id);
        $depotOrder->update($request->all());

        return response()->json($depotOrder);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $depotOrder = DepotOrder::findOrFail($id);
        $depotOrder->delete();

        return response()->json($depotOrder);
    }
}
