<?php

namespace Modules\Cart\Entities;

use Modules\Cart\Traits\Uuid;
use Modules\Core\Traits\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory, Uuid;

    protected $fillable = ['customer_id', 'item_count', 'total_quantity', 'coupon_code', 'channel_code', 'store_code'];

    public function cartItems(): HasMany
    {
       return $this->hasMany(CartItem::class);
    }
    
}
