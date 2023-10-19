<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'customer',
        'created_at',
        'completed_at',
        'warehouse_id',
        'status',
    ];

    public function warehouse(): belongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderItems(): hasMany
    {
        return $this->hasMany(OrderItem::class);
    }

}
