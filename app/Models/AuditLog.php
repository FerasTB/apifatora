<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'admin_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    /**
     * Relationship: AuditLog belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: AuditLog belongs to an Admin.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
