<?php
// app/Models/Bank.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'account_name',
        'balance',
        'created_by',
        'updated_by',
        'deleted',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'deleted' => 'boolean',
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where($query->qualifyColumn('deleted'), false);
    }
}
