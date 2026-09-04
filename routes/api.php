<?php

use App\Http\Controllers\CheapestOfferController;
use App\Http\Controllers\OfferReservationController;
use App\Http\Controllers\SupplierImportController;
use Illuminate\Support\Facades\Route;

Route::get('/offers/cheapest', [CheapestOfferController::class, 'index']);
Route::post('/offers/{offer}/reservations', [OfferReservationController::class, 'store'])->whereNumber('offer');
Route::post('/suppliers/{supplier:slug}/imports', [SupplierImportController::class, 'store']);
