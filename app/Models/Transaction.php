<?php
// app/Models/Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'category_id',
        'type',              // income, expense, investment_buy, investment_sell
        'amount',
        'date',
        'note',
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'deleted' => 'boolean',
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
