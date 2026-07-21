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
     * @OA\Get(
     *     path="/netbox/orders",
     *     summary="Get a list of Netbox custom orders",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of results to return (default: 50)",
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         required=false,
     *         description="Number of results to skip for pagination (default: 0)",
     *         @OA\Schema(type="integer", example=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of orders",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $limit  = (int) $request->get('limit', 50);
        $offset = (int) $request->get('offset', 0);

        $orders = Orders::limit($limit)->offset($offset)->get();

        return response()->json($orders);
    }

    /**
     * @OA\Get(
     *     path="/netbox/orders/{id}",
     *     summary="Get a single Netbox order by ID",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Order not found")
     * )
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
     * @OA\Post(
     *     path="/netbox/orders",
     *     summary="Create a new Netbox order",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Order fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $order = Orders::create($request->all());

        return response()->json($order, 201);
    }

    /**
     * @OA\Put(
     *     path="/netbox/orders/{id}",
     *     summary="Update a Netbox order by ID",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Order fields to update")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Order not found")
     * )
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
     * @OA\Delete(
     *     path="/netbox/orders/{id}",
     *     summary="Delete a Netbox order by ID",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Order not found")
     * )
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
