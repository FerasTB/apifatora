<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Mass assignable attributes
    protected $fillable = [
        'name',
        'email',
        'phone_verified_at',
        'phone',
        'password',
        'role',
        'balance',
        'notification_preferences',
        'ids',
    ];

    // Hidden attributes for arrays and JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Cast attributes to native types
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'notification_preferences' => 'array',
        'balance' => 'decimal:2',
    ];

    /**
     * Check if the user has the 'admin' role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user has the 'third_party_app' role.
     */
    public function isThirdPartyApp(): bool
    {
        return $this->role === 'third_party_app';
    }

    /**
     * Relationship: User has many Bills.
     */
    public function bills()
    {
        return $this->hasMany(Bill::class, 'bill_id');
    }

    /**
     * Relationship: User has many Invoices.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'invoice_id');
    }

    /**
     * Relationship: User has many Transactions (as the initiator).
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'transaction_id');
    }

    /**
     * Relationship: User has many Refunds.
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class, 'refund_id');
    }

    /**
     * Relationship: User has many Transactions as a third-party app.
     */
    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'third_party_app_id');
    }

    /**
     * Relationship: User has many Refunds as a third-party app.
     */
    public function receivedRefunds()
    {
        return $this->hasMany(Refund::class, 'third_party_app_id');
    }

    /**
     * Relationship: User has many Audit Logs.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
