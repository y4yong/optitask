<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'tasks';
    protected $primaryKey = 'task_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'task_id',
        'task_title',
        'description',
        'start_date',
        'due_date',
        'task_status',
        'priority',
        'employee_id',
        'manager_id',
        'manager_notes',
        'task_type',
        'task_file',
        'submission_file',
        'evidence_link',
    ];

    /**
     * Relationship: Task belongs to User (Employee)
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Relationship: Task belongs to User (Manager)
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id', 'user_id');
    }
}
