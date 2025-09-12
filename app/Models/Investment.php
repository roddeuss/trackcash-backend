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
        'asset_id',            // relasi ke master assets
        'units',               // jumlah unit (lot, gram, coin, dll)
        'buy_price_per_unit',  // harga beli per unit
        'buy_date',
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'buy_date' => 'date',
        'buy_price_per_unit' => 'decimal:2',
        'current_price_per_unit' => 'decimal:2',
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

    // 🔹 Hitung total nilai investasi berdasarkan transaksi
    public function getTotalValueAttribute()
    {
        return $this->units * $this->buy_price_per_unit;
    }
}
