<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $table = 'refunds';

    protected $fillable = [
        'transaction_id',
        'user_id',
        'third_party_app_id',
        'amount',
        'fee_refunded',
        'status',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_refunded' => 'decimal:2',
    ];

    /**
     * Relationship: Refund belongs to a Transaction.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relationship: Refund belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Refund belongs to a Third-Party App.
     */
    public function thirdPartyApp()
    {
        return $this->belongsTo(User::class, 'third_party_app_id');
    }
}
