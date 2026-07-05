<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $table = 'skills';
    protected $primaryKey = 'skill_id';

    protected $fillable = [
        'skill_name',
    ];

    /**
     * Relationship: Skill belongs to many Users (Employees)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'employee_skills', 'skill_id', 'user_id')
                    ->withPivot('proficiency_level')
                    ->withTimestamps();
    }
}
