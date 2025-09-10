<?php
// app/Models/Asset.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',         // saham, crypto, emas, reksadana, properti
        'code',         // BBCA, BTC, XAU
        'name',         // Bank Central Asia, Bitcoin, Emas
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'deleted' => 'boolean',
    ];

    // Relasi ke Investment
    public function investments()
    {
        return $this->hasMany(Investment::class);
    }
}
