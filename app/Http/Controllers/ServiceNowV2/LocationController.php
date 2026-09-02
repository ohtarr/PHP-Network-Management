<?php

namespace App\Http\Controllers\ServiceNowV2;

use App\Models\ServiceNowV2\Location;
use Illuminate\Http\Request;

class LocationController extends ControllerTemplate
{
    public static $model = Location::class;

    /**
     * @OA\Get(
     *     path="/servicenow/locations",
     *     summary="List ServiceNow locations",
     *     description="Always excludes records with an empty company field, regardless of other filters applied.",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sys_id",
     *         in="query",
     *         required=false,
     *         description="Filter by ServiceNow sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=false,
     *         description="Filter by location name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sysparm_query",
     *         in="query",
     *         required=false,
     *         description="Raw ServiceNow encoded query fragment, for filters that need an operator other than equals (e.g. 'sys_created_on>javascript:gs.daysAgo(60)'). ANDed together with any other filters passed alongside it, and with the always-applied companyISNOTEMPTY filter.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sysparm_fields",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of fields to return (sysparm_fields), to limit the response to specific columns.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         required=false,
     *         description="Alias for sysparm_fields. Comma-separated list of fields to return. Ignored if sysparm_fields is also provided.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of results to return (sysparm_limit)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         required=false,
     *         description="Number of results to skip (sysparm_offset)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of ServiceNow locations",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        return parent::index($request);
    }

    /**
     * @OA\Get(
     *     path="/servicenow/locations/{id}",
     *     summary="Get a single ServiceNow location by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The location's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Record not found")
     * )
     */
    public function show($id)
    {
        return parent::show($id);
    }

    /**
     * @OA\Post(
     *     path="/servicenow/locations",
     *     summary="Create a new ServiceNow location",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Location fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Location created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        return parent::store($request);
    }

    /**
     * @OA\Patch(
     *     path="/servicenow/locations/{id}",
     *     summary="Update a ServiceNow location by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The location's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Location fields to update")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Record not found")
     * )
     */
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    /**
     * @OA\Delete(
     *     path="/servicenow/locations/{id}",
     *     summary="Delete a ServiceNow location by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The location's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Record deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Record not found")
     * )
     */
    public function destroy($id)
    {
        return parent::destroy($id);
    }
}
