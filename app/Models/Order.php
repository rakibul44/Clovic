<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $attributes = ['status' => 'pending', 'payment_status' => 'unpaid', 'currency' => 'BDT'];

    protected $guarded = ['id'];

    protected $casts = ['restocked_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function shipping()
    {
        return $this->hasOne(ShippingRecord::class);
    }
}
