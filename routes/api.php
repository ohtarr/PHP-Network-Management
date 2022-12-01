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

Route::apiResource('address', App\Models\Location\Address\AddressController::class);
Route::apiResource('building', App\Models\Location\Building\BuildingController::class);
Route::apiResource('room', App\Models\Location\Room\RoomController::class);
Route::apiResource('site', App\Models\Location\Site\SiteController::class);
Route::apiResource('device', App\Models\Device\DeviceController::class);