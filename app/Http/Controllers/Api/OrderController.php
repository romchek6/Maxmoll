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

    /**
     * Обновляет данные у заказа
     *
     * @return JsonResponse
     */
    public function update($id, Request $request): JsonResponse
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

        if (!empty($validatedData['warehouse_id'])) {
            // Проверка наличия товаров на новом складе
            $exception = $this->checkWarehouseItems($validatedData, $order);

            // Если какого-то товара нет на новом складе, вернуть ошибку
            if ($exception > 0) {
                return response()->json(['errors' => 'Недостаточно товара на складе'], 400);
            }

            // Обновить склад заказа на новый
            $this->updateOrderWarehouse($order, $validatedData['warehouse_id']);

            $this->UpdateNewItems($validatedData['items'], $validatedData['warehouse_id']);

            // Вернуть старые товары на предыдущий склад
            $this->returnOldItemsToWarehouse($order);
        }

        if (!empty($validatedData['items']) && empty($validatedData['warehouse_id'])) {

            $exception = $this->checkWarehouseItems($validatedData, $order);

            if ($exception > 0) {
                return response()->json(['errors' => 'Недостаточно товара на складе'], 400);
            }

            $this->returnOldItemsToWarehouse($order);

            $this->UpdateNewItems($validatedData['items'], $validatedData['warehouse_id']);
        }

        if (!empty($validatedData['customer'])) $order->update(['customer' => $validatedData['customer']]);

        return response()->json(['success' => 'Данные заказа обновлены']);
    }

    /**
     * Проверяет наличие товаров на складе
     *
     * @return int
     */
    protected function checkWarehouseItems($validatedData, $order): int
    {
        $exception = 0;

        $warehouse = $validatedData['warehouse_id'] ?? $order->warhouse_id;

        // если с новым складом пришли новые товары
        if (!empty($validatedData['items'])) {
            foreach ($validatedData['items'] as $item) {

                $stock = Stock::query()->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouse)
                    ->first();

                if (!$stock || $stock->stock < $item['count']) {
                    $exception++;
                    break;
                }
            }
        } else {
            // если изменился только склад
            $orderItems = OrderItem::query()->where('order_id', $order->id)->get();

            if (!empty($orderItems)) {
                foreach ($orderItems as $orderItem) {
                    $stock = Stock::query()->where('product_id', $orderItem['product_id'])
                        ->where('warehouse_id', $warehouse)
                        ->first();

                    if (!$stock || $stock->stock < $orderItem['count']) {
                        $exception++;
                        break;
                    }
                }
            }
        }

        return $exception;
    }

    /**
     * Обновляет склад
     *
     * @return int
     */
    protected function updateOrderWarehouse($order, $newWarehouseId): void
    {
        $order->update(['warehouse_id' => $newWarehouseId]);
    }

    /**
     * Возвращает старые товары на склад
     *
     * @return int
     */
    protected function returnOldItemsToWarehouse($order): void
    {
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
    }

    /**
     * Берёт новые товары со склада
     *
     * @return int
     */
    protected function UpdateNewItems($newItems, $warehouseId): void
    {
        if (!empty($newItems)) {
            foreach ($newItems as $newItem) {
                $stock = Stock::query()->where('product_id', $newItem['product_id'])
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock) {
                    // Обновить информацию о товаре на текущем складе
                    $stock->stock += $newItem['count'];
                    $stock->save();
                }
            }
        }
    }

}
