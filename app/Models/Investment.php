<?php
// app/Models/Investment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset_id',
        'units',               // total units yang masih dimiliki
        'average_buy_price',   // harga rata-rata beli
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'average_buy_price' => 'decimal:2',
        'deleted' => 'boolean',
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function transactions()
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    // 📊 Hitung nilai beli total (cost basis)
    public function getTotalBuyValueAttribute()
    {
        return $this->units * $this->average_buy_price;
    }

    // 📊 Hitung nilai saat ini → API inject harga
    public function getCurrentValue($marketPrice)
    {
        return $this->units * $marketPrice;
    }

    // 📊 Hitung Profit / Loss → API inject harga
    public function getProfitLoss($marketPrice)
    {
        $cost = $this->units * $this->average_buy_price;
        return ($this->units * $marketPrice) - $cost;
    }
}
