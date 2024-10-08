<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'bills';

    protected $fillable = [
        'user_id',
        'bill_type',
        'amount',
        'bill_info',
        'due_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    /**
     * Relationship: Bill belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Bill may have an Invoice.
     */
    public function invoice()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if the bill is overdue.
     */
    public function isActive(): bool
    {
        return $this->status == 'active';
    }
}
