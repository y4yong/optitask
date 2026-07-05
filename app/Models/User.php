<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Primary key settings for custom string ID (e.g. EM001)
     */
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'username',
        'email',
        'password',
        'role',
        'account_status',
        'suspension_reason',
        'dept_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Relationship: User belongs to a Department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'dept_id');
    }

    /**
     * Relationship: User has many Tasks (as Employee)
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'employee_id', 'user_id');
    }

    /**
     * Relationship: User has many Notifications
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    /**
     * Relationship: User has many Skills
     */
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'employee_skills', 'user_id', 'skill_id')
                    ->withPivot('proficiency_level')
                    ->withTimestamps();
    }
}
