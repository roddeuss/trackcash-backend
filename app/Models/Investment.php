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
        'asset_id',               // relasi ke master assets
        'units',
        'buy_price_per_unit',
        'buy_date',
        'current_price_per_unit',
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
}
