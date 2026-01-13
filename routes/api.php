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

//Route::apiResource('/addresses', App\Models\Location\Address\AddressController::class);
//Route::apiResource('/buildings', App\Models\Location\Building\BuildingController::class);
//Route::apiResource('/rooms', App\Models\Location\Room\RoomController::class);
//Route::apiResource('/sites', App\Models\Location\Site\SiteController::class);
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
//Route::apiResource('/netbox/devices', App\Models\Netbox\NetboxController::class);

//Route::get('/mist/device', [App\Models\Mist\MistController::class, 'getDeviceInventory']);
//Route::get('/mist/device/{deviceid}/', [App\Models\Mist\MistController::class, 'getDeviceInventory']);
//Route::get('/mist/device/{deviceid}/sitedevice', [App\Models\Mist\MistController::class, 'getDeviceInventory']);
//Route::get('/mist/device/{deviceid}/stats', [App\Models\Mist\MistController::class, 'getDeviceInventory']);
//Route::get('/mist/device/{deviceid}/portdetails', [App\Models\Mist\MistController::class, 'getDeviceInventory']);
//Route::get('/mist/site/{siteid}/device/{deviceid}', [App\Models\Mist\MistController::class, 'getDeviceInventoryById']);
//Route::get('/mist/site/{siteid}/device/{deviceid}/stats', [App\Models\Mist\MistController::class, 'SiteDeviceSummaryBySiteName']);
//Route::get('/mist/site/{siteid}/device/{deviceid}/portdetails', [App\Models\Mist\MistController::class, 'SiteDeviceSummaryBySiteName']);

Route::get('/mist/site', [App\Models\Mist\MistController::class, 'Sites']);
Route::get('/mist/site/summary', [App\Models\Mist\MistController::class, 'SitesSummary']);
Route::get('/mist/site/{siteid}/devicesummary', [App\Models\Mist\MistController::class, 'SiteDeviceSummary']);
Route::get('/mist/site/{siteid}/device/{deviceid}/details', [App\Models\Mist\MistController::class, 'SiteDeviceSummaryDetails']);
Route::post('/mist/claim/{sitecode?}', [App\Models\Mist\MistController::class, 'claimDevices']);

Route::get('provisioning/snowlocations', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getSnowLocations']);
Route::get('provisioning/snowlocation/{sitecode}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getSnowLocation']);

Route::get('provisioning/netbox/devicetypes', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getNetboxDeviceTypesSummarized']);
Route::get('provisioning/netboxsite/{sitecode}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getNetboxSite']);
Route::post('provisioning/netboxsite/{sitecode}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'deployNetboxSite']);
Route::post('provisioning/netboxsite/{sitecode}/devices', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'deployNetboxDevices']);
Route::get('provisioning/netboxsite/{sitecode}/addresses/{qty?}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getAvailableProvIps']);

Route::get('provisioning/dhcp/{sitecode}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getDhcpScopes']);
Route::get('provisioning/dhcp/overlap/{network}/{bitmask}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'getDhcpScopeOverlap']);
Route::post('provisioning/dhcp/{sitecode}/vlan/{vlan}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'deployDhcpScope']);
Route::post('provisioning/dhcp/{sitecode}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'deployDhcpScopes']);

Route::post('provisioning/mist/site/{sitecode}', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'deployMistSite']);
Route::post('provisioning/mist/site/{sitecode}/devices', [App\Http\Controllers\Provisioning\ProvisioningController::class, 'deployMistDevices']);

Route::get('deprovisioning/snowlocations/{days?}', [App\Http\Controllers\Deprovisioning\DeprovisioningController::class, 'getSnowLocations']);
Route::delete('deprovisioning/mist/site/{sitecode}', [App\Http\Controllers\Deprovisioning\DeprovisioningController::class, 'deleteMistSite']);
Route::delete('deprovisioning/mist/site/{sitecode}/devices', [App\Http\Controllers\Deprovisioning\DeprovisioningController::class, 'unassignMistDevices']);
Route::delete('deprovisioning/dhcp/{sitecode}', [App\Http\Controllers\Deprovisioning\DeprovisioningController::class, 'deleteSiteDhcpScopes']);
Route::delete('deprovisioning/dhcp/scope/{scope}', [App\Http\Controllers\Deprovisioning\DeprovisioningController::class, 'deleteDhcpScope']);

Route::delete('deprovisioning/netbox/site/{sitecode}', [App\Http\Controllers\Deprovisioning\DeprovisioningController::class, 'deleteNetboxSite']);

Route::get('validation/netboxsite/{sitecode}', [App\Http\Controllers\Validation\ValidationController::class, 'validateNetboxSite']);

Route::get('management/netbox/{sitecode}/devices/', [App\Http\Controllers\Management\ManagementController::class, 'getSiteSummary']);
Route::get('management/netbox/sites/', [App\Http\Controllers\Management\ManagementController::class, 'getNetboxSites']);
Route::get('management/search', [App\Http\Controllers\Management\ManagementController::class, 'searchOutputs']);

Route::get('reports/sitesubnets', [App\Http\Controllers\Reports\ReportsController::class, 'siteSubnetReport']);
Route::get('reports/dhcp/orphanedscopes', [App\Http\Controllers\Reports\ReportsController::class, 'getOrphanedDhcpScopes']);
Route::get('reports/opengear/status', [App\Http\Controllers\Reports\ReportsController::class, 'getOpengearStatus']);