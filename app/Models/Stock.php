<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'stock'
    ];

    public function product(): belongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): belongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
