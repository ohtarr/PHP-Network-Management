<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Network Automation API",
 *     version="1.0.0",
 *     description="PHP Network Management Framework — REST API for provisioning, deprovisioning, management, reporting, and asset tracking of network infrastructure."
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="Primary API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="oauth2",
 *     type="oauth2",
 *     description="Azure AD OAuth2 — click Authorize and sign in with your Azure AD account.",
 *     @OA\Flow(
 *         flow="implicit",
 *         authorizationUrl="https://login.microsoftonline.com/07420c3d-c141-4c67-b6f3-f448e5adb67b/oauth2/v2.0/authorize",
 *         scopes={
 *             "openid": "Sign in and read user profile",
 *             "profile": "View your basic profile",
 *             "email": "View your email address",
 *             "api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user": "Access the Network Automation API as the signed-in user"
 *         }
 *     )
 * )
 *
 * @OA\Tag(name="Provisioning", description="Site provisioning operations (Netbox, DHCP, Mist)")
 * @OA\Tag(name="Deprovisioning", description="Site deprovisioning operations (Mist, DHCP, Netbox)")
 * @OA\Tag(name="Management", description="Device and site management operations")
 * @OA\Tag(name="Validation", description="Site configuration validation")
 * @OA\Tag(name="Reports", description="Network reporting endpoints")
 * @OA\Tag(name="SnipeIT", description="SnipeIT asset management operations")
 * @OA\Tag(name="Logs", description="Application activity log endpoints")
 * @OA\Tag(name="DepotOrders", description="Depot order management")
 * @OA\Tag(name="Orders", description="Netbox custom order object management")
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
