<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'third_party_app_id',
        'type',
        'amount',
        'fee',
        'total_amount',
        'status',
        'reference_id',
        'description',
        'refundable_until',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'refundable_until' => 'datetime',
    ];

    /**
     * Relationship: Transaction belongs to a User (initiator).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Transaction belongs to a Third-Party App.
     */
    public function thirdPartyApp()
    {
        return $this->belongsTo(User::class, 'third_party_app_id');
    }

    /**
     * Relationship: Transaction has one Fee.
     */
    public function fee()
    {
        return $this->hasOne(Fee::class);
    }

    /**
     * Relationship: Transaction may have one Refund.
     */
    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * Relationship: Transaction may be associated with an Invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'reference_id');
    }

    /**
     * Check if the transaction is refundable.
     */
    public function isRefundable(): bool
    {
        return $this->status === 'completed' && $this->refundable_until && now()->lessThanOrEqualTo($this->refundable_until);
    }
}
