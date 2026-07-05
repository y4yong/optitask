<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';
    protected $primaryKey = 'dept_id';

    protected $fillable = [
        'dept_name',
    ];

    /**
     * Relationship: Department has many Users
     */
    public function users()
    {
        return $this->hasMany(User::class, 'dept_id', 'dept_id');
    }
}
