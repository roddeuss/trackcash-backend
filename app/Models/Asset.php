<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'type_id',     // foreign key ke tabel types
        'asset_code',        // BBCA, BTC, XAU
        'asset_name',        // Bank Central Asia, Bitcoin, Emas
        'quantity',    // jumlah lot, gram, coin, dll
        'lot_size',    // jumlah lot, gram, coin, dll
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'deleted' => 'boolean',
        'quantity' => 'decimal:8', // presisi tinggi untuk crypto/emas
    ];

    // Relasi ke Type
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    // Relasi ke Investment
    public function investments()
    {
        return $this->hasMany(Investment::class);
    }
}
