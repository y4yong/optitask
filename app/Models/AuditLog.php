<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'user_id',
        'action',
        'details',
        'timestamp',
    ];

    /**
     * Static helper to log actions to audit log.
     */
    public static function log($userId, $action, $details)
    {
        return self::create([
            'user_id' => $userId ?: 'SYSTEM',
            'action' => $action,
            'details' => $details,
            'timestamp' => now()
        ]);
    }
}
