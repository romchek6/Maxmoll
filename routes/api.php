<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WarehouseController;

// /api/warehouses - получает json всех складов
Route::apiResource('warehouses', WarehouseController::class)->only('index');

// /api/product - получает json всех товаров с их остатками на складе
Route::apiResource('products', ProductController::class)->only('index');

// /api/orders - получает json всех товаров с их остатками на складе
Route::apiResource('orders', OrderController::class)->only('index', 'store', 'update');
