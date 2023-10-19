<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price'
    ];

    public function orderItems(): hasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stocks(): hasMany
    {
        return $this->hasMany(Stock::class);
    }

}
