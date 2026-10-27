<?php

use App\Http\Controllers\AddressValidation\AddressValidationController;
use Illuminate\Support\Facades\Route;

Route::post('/', [AddressValidationController::class, 'validateAddress']);
