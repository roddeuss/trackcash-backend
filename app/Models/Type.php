<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', // saham, crypto, emas, reksadana, properti
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
