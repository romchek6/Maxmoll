<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Возвращает коллекцию товаров с их остатками на складах.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
       return ProductResource::collection(Product::query()->with('stocks')->get());
    }
}
