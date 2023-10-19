<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'count'
    ];

    public function order(): belongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): belongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
