<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use App\Services\FilterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;


class OrderController extends Controller
{
    /**
     * Возвращает коллекцию заказов с их товарами и их количеством.
     *
     * @return Collection | LengthAwarePaginator
     */
    public function index(FilterService $service, Request $request): Collection|LengthAwarePaginator
    {
        $orders = Order::query()->with('orderItems');

        return $service->filter($request, $orders);
    }

    /**
     * Создаёт заказ
     *
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // валидация
        try {
            $validatedData = $request->validate([
                'customer' => 'required|string|max:255',
                'items' => 'required|array',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.count' => 'required|integer|min:1',
                'warehouse_id' => 'required|integer|exists:warehouses,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        // создание заказа
        $order = Order::query()->create([
            'customer' => $validatedData['customer'],
            'warehouse_id' => $validatedData['warehouse_id'],
            'status' => 'active',
            'created_at' => Carbon::now()
        ]);

        // количество ошибок
        $exception = 0;

        foreach ($validatedData['items'] as $item) {

            $stock = Stock::query()->where('product_id', $item['product_id'])
                ->where('warehouse_id', $validatedData['warehouse_id'])
                ->first();

            // проверка количество продукта на складе
            if (empty($stock) || $stock->stock < $item['count']) {
                $exception++;
                break;
            }

        }

        // если какого то товара не достаточно, возвращаем ошибку
        if ($exception > 0) {
            $order->delete();
            return response()->json(['errors' => 'Недостаточно товара на складе'], 400);
        }

        foreach ($validatedData['items'] as $item) {
            $stock = Stock::query()->where('product_id', $item['product_id'])
                ->where('warehouse_id', $validatedData['warehouse_id'])
                ->first();

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'count' => $item['count'],
            ]);

            $stock->update([
                'stock' => $stock->stock - $item['count']
            ]);
        }

        return response()->json(['success' => 'Заказ успешно создан'], 200);
    }

    public function update($id, Request $request)
    {
        try {
            $validatedData = $request->validate([
                'customer' => 'string|max:255',
                'items' => 'array',
                'items.*.product_id' => 'integer|exists:products,id',
                'items.*.count' => 'integer|min:1',
                'warehouse_id' => 'integer|exists:warehouses,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        // проверяем на наличие заказа
        $order = Order::query()->find($id);
        if (empty($order)) {
            return response()->json(['errors' => 'Заказ не найден']);
        }

        // количество ошибок
        $exception = 0;

        // проверяем на наличие id склада, и если он есть проверяем
        // достаточно ли товаров на новом складе
        if (!empty($validatedData['warehouse_id'])) {

            if (!empty($validatedData['items'])) {
                foreach ($validatedData['items'] as $item) {

                    $stock = Stock::query()->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $validatedData['warehouse_id']);

                    if ($stock->stock < $item['count']) {
                        $exception++;
                        break;
                    }
                }
            } else {
                $orderItems = OrderItem::query()->where('order_id', $order->id)->get();

                if (!empty($orderItems)) {
                    foreach ($orderItems as $orderItem) {
                        $stock = Stock::query()->where('product_id', $orderItem['product_id'])
                            ->where('warehouse_id', $validatedData['warehouse_id']);

                        if ($stock->stock < $orderItem['count']) {
                            $exception++;
                            break;
                        }
                    }
                }

            }

            // если какого то товара не достаточно, возвращаем ошибку
            if ($exception > 0) {
                return response()->json(['errors' => 'Недостаточно товара на складе'], 400);
            }

            $order->update(['customer' => $validatedData['warehouse_id']]);
        }

        if (!empty($validatedData['items'])) {
            // проверяем можно есть ли нужное кол. новых товаров
            foreach ($validatedData['items'] as $item) {
                $stock = Stock::query()->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->first();

                // проверка количество продукта на складе
                if (empty($stock) || $stock->stock < $item['count']) {
                    $exception++;
                    break;
                }
            }

            if ($exception > 0) {
                return response()->json(['errors' => 'Недостаточно товара на складе'], 400);
            }

            // удаляем все позиции заказа и возвращаем на склады
            $oldItems = $order->orderItems()->get();
            foreach ($oldItems as $item) {
                $stock = Stock::query()
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->first();

                if (!empty($stock)) {
                    Stock::query()
                        ->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $order->warehouse_id)
                        ->update([
                            'stock' => $stock->stock + $item->count
                        ]);
                }
            }
            // удаляем все старые позиции заказа
            $order->orderItems()->delete();

            // добавляем новые товары в заказ
            foreach ($validatedData['items'] as $item) {
                $stock = Stock::query()
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->first();
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);
                Stock::query()
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->update([
                        'stock' => $stock->stock - $item['count']
                    ]);
            }
        }

        if (!empty($validatedData['customer'])) $order->update(['customer' => $validatedData['customer']]);

        return response()->json(['success' => 'Данные заказа обновлены']);
    }
}
