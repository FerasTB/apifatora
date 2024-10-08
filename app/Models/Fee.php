<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    use HasFactory;

    protected $table = 'fees';

    protected $fillable = [
        'transaction_id',
        'amount',
        'payment_system_account_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relationship: Fee belongs to a Transaction.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
