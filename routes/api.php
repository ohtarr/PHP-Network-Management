<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('/addresses', App\Models\Location\Address\AddressController::class);
Route::apiResource('/buildings', App\Models\Location\Building\BuildingController::class);
Route::apiResource('/rooms', App\Models\Location\Room\RoomController::class);
Route::apiResource('/sites', App\Models\Location\Site\SiteController::class);
Route::apiResource('/devices/aruba', App\Models\Device\Aruba\ArubaController::class);
Route::apiResource('/devices/cisco/ios', App\Models\Device\Cisco\IOS\CiscoIOSController::class);
Route::apiResource('/devices/cisco/iosxe', App\Models\Device\Cisco\IOSXE\CiscoIOSXEController::class);
Route::apiResource('/devices/cisco/iosxr', App\Models\Device\Cisco\IOSXR\CiscoIOSXRController::class);
Route::apiResource('/devices/cisco/nxos', App\Models\Device\Cisco\NXOS\CiscoNXOSController::class);
Route::apiResource('/devices/cisco', App\Models\Device\Cisco\CiscoController::class);
Route::apiResource('/devices/juniper', App\Models\Device\Juniper\JuniperController::class);
Route::apiResource('/devices/opengear', App\Models\Device\Opengear\OpengearController::class);
Route::apiResource('/devices/ubiquiti', App\Models\Device\Ubiquiti\UbiquitiController::class);
Route::apiResource('/devices', App\Models\Device\DeviceController::class);
Route::apiResource('/servicenow/incidents', App\Models\ServiceNow\IncidentController::class);
