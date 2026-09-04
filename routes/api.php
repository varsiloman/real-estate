<?php

use App\Http\Controllers\SupplierImportController;
use Illuminate\Support\Facades\Route;

Route::post('/suppliers/{supplier:slug}/imports', [SupplierImportController::class, 'store']);
