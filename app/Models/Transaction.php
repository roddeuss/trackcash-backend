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
        'bank_id',         // relasi ke bank (rekening sumber/tujuan)
        'asset_id',        // opsional: relasi ke asset kalau transaksi terkait investasi
        'category_id',     // income / expense / investment
        'amount',          // jumlah uang
        'description',     // catatan transaksi
        'transaction_date',
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime', // ✅ tambahkan ini
        'deleted' => 'boolean',
    ];

    // Relasi
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // 🔹 Hitung apakah transaksi ini income/expense
    public function getIsIncomeAttribute()
    {
        return $this->category && $this->category->type === 'income';
    }

    public function getIsExpenseAttribute()
    {
        return $this->category && $this->category->type === 'expense';
    }

    public function scopeActive($query)
    {
        return $query->where($query->qualifyColumn('deleted'), false);
    }
}
