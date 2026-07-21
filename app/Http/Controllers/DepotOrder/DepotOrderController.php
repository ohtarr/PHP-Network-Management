<?php

namespace App\Http\Controllers\DepotOrder;

use App\Http\Controllers\Controller;
use App\Models\DepotOrder\DepotOrder;
use Illuminate\Http\Request;

class DepotOrderController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/depotorders",
     *     summary="Get a paginated list of depot orders",
     *     tags={"DepotOrders"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of results per page (default: 25)",
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of depot orders",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="per_page", type="integer", example=25)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);

        return response()->json(DepotOrder::orderBy('id', 'desc')->paginate($perPage));
    }

    /**
     * @OA\Post(
     *     path="/depotorders",
     *     summary="Create a new depot order",
     *     tags={"DepotOrders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Depot order fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Depot order created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $depotOrder = DepotOrder::create($request->all());

        return response()->json($depotOrder, 201);
    }

    /**
     * @OA\Get(
     *     path="/depotorders/{id}",
     *     summary="Get a single depot order by ID",
     *     tags={"DepotOrders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The depot order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Depot order object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Depot order not found")
     * )
     */
    public function show($id)
    {
        return response()->json(DepotOrder::findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/depotorders/{id}",
     *     summary="Update a depot order by ID",
     *     tags={"DepotOrders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The depot order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Depot order fields to update")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated depot order object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Depot order not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $depotOrder = DepotOrder::findOrFail($id);
        $depotOrder->update($request->all());

        return response()->json($depotOrder);
    }

    /**
     * @OA\Delete(
     *     path="/depotorders/{id}",
     *     summary="Delete a depot order by ID",
     *     tags={"DepotOrders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The depot order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted depot order object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Depot order not found")
     * )
     */
    public function destroy($id)
    {
        $depotOrder = DepotOrder::findOrFail($id);
        $depotOrder->delete();

        return response()->json($depotOrder);
    }
}
