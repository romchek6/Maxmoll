<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FilterService
{
    /**
     * Функция фильтрует заказы
     *
     * @param Request $request Объект запроса.
     * @param Builder $orders Объект построителя запросов.
     *
     * @return Order|LengthAwarePaginator Возвращает коллекцию заказов или объект пагинатора.
     */
    public function filter(Request $request,Builder $orders): Collection | LengthAwarePaginator
    {
        // если указан статус применяем фильтр по статусу
        if ($request->has('status')) {
            $status = $request->status;
            $orders->where('status', $status);
        }

        // если указана пагинация, применяем пагинацию
        if ($request->has('paginate')){
            $orders = $orders->paginate($request->paginate);
            return $orders;
        }

        return $orders->get();
    }

}
