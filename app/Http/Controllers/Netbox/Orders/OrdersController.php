<?php

namespace App\Http\Controllers\Netbox\Orders;

use App\Http\Controllers\Controller;
use App\Models\Netbox\PLUGINS\CUSTOMOBJECTS\Orders;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth:api');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $limit  = (int) $request->get('limit', 50);
        $offset = (int) $request->get('offset', 0);

        $orders = Orders::limit($limit)->offset($offset)->get();

        return response()->json($orders);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order = Orders::find($id);

        if (!$order || !isset($order->id)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $order = Orders::create($request->all());

        return response()->json($order, 201);
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
        $order = Orders::find($id);

        if (!$order || !isset($order->id)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $updated = $order->update($request->all());

        return response()->json($updated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $order = Orders::find($id);

        if (!$order || !isset($order->id)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully'], 200);
    }
}
