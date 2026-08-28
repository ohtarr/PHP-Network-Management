<?php

namespace App\Http\Controllers\ServiceNowV2;

use App\Models\ServiceNowV2\User;
use Illuminate\Http\Request;

class UserController extends ControllerTemplate
{
    public static $model = User::class;

    /**
     * @OA\Get(
     *     path="/servicenow/users",
     *     summary="List ServiceNow users",
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
     *         name="user_name",
     *         in="query",
     *         required=false,
     *         description="Filter by username",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         required=false,
     *         description="Filter by email address",
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
     *         description="List of ServiceNow users",
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
     *     path="/servicenow/users/{id}",
     *     summary="Get a single ServiceNow user by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The user's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User object",
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
     *     path="/servicenow/users",
     *     summary="Create a new ServiceNow user",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="User fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
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
     *     path="/servicenow/users/{id}",
     *     summary="Update a ServiceNow user by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The user's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="User fields to update")
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
     *     path="/servicenow/users/{id}",
     *     summary="Delete a ServiceNow user by sys_id",
     *     tags={"Service-Now"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The user's sys_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
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
