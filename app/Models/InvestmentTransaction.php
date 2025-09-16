<?php
// app/Models/InvestmentTransaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestmentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_id',
        'transaction_id', // relasi ke transaksi cashflow
        'type', // buy / sell
        'units',
        'price_per_unit',
        'transaction_date',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
