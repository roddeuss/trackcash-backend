<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',            // nama rule, contoh: "Gaji Bulanan", "Kontrakan"
        'type',            // income | expense
        'amount',          // nominal transaksi
        'category_id',     // kategori transaksi
        'bank_id',         // bank / rekening terkait (opsional)
        'asset_id',        // asset terkait (opsional)
        'frequency',       // daily, weekly, monthly, yearly
        'day_of_month',    // untuk monthly (1–31)
        'day_of_week',     // untuk weekly (0=Sun, 1=Mon...)
        'start_date',      // recurring mulai kapan
        'end_date',        // recurring berhenti kapan (opsional)
        'next_run_at',     // kapan rule ini akan dijalankan berikutnya
        'is_active',       // aktif / nonaktif
        'deleted',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
        'deleted' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where($query->qualifyColumn('deleted'), false);
    }

    // ============================
    //         RELATIONSHIP
    // ============================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    // ============================
    //       ACCESSORS
    // ============================

    public function getIsIncomeAttribute()
    {
        return $this->type === 'income';
    }

    public function getIsExpenseAttribute()
    {
        return $this->type === 'expense';
    }
}
