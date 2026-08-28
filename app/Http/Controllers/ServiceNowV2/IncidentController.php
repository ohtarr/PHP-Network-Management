<?php

namespace App\Http\Controllers\ServiceNowV2;

use App\Models\ServiceNowV2\Incident;
use Illuminate\Http\Request;

class IncidentController extends ControllerTemplate
{
    public static $model = Incident::class;

    /**
     * @OA\Get(
     *     path="/servicenow/incidents",
     *     summary="List ServiceNow incidents",
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
     *         name="number",
     *         in="query",
     *         required=false,
     *         description="Filter by incident number (e.g. INC0012345)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="short_description",
     *         in="query",
     *         required=false,
     *         description="Filter by short description",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="assignment_group",
     *         in="query",
     *         required=false,
     *         description="Filter by assignment group (sys_id or display value of the referenced sys_user_group record)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sysparm_query",
     *         in="query",
     *         required=false,
     *         description="Raw ServiceNow encoded query fragment, for filters that need an operator other than equals (e.g. 'sys_created_on>javascript:gs.daysAgo(60)'). ANDed together with any other filters passed alongside it.",
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
     *         description="List of ServiceNow incidents",
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
     *     path="/servicenow/incidents/{id}",
     *     summary="Get a single ServiceNow incident by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The incident's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Incident object",
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
     *     path="/servicenow/incidents",
     *     summary="Create a new ServiceNow incident",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Incident fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Incident created successfully",
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
     *     path="/servicenow/incidents/{id}",
     *     summary="Update a ServiceNow incident by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The incident's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Incident fields to update")
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
     *     path="/servicenow/incidents/{id}",
     *     summary="Delete a ServiceNow incident by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The incident's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Incident deleted successfully",
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
