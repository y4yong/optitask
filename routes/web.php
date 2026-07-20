<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\EmployeeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup']);
});

// Authenticated Logout Route
Route::middleware('auth')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Admin Routes (Admin role required)
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/manage-users', [AdminController::class, 'manageUsers'])->name('manage_users');
    Route::post('/user/create', [AdminController::class, 'createUser'])->name('create_user');
    Route::post('/user/{user_id}/update', [AdminController::class, 'updateUser'])->name('update_user');
    Route::post('/user/{user_id}/delete', [AdminController::class, 'deleteUser'])->name('delete_user');
    Route::post('/user/{user_id}/reset-password', [AdminController::class, 'resetPassword'])->name('reset_password');
    Route::post('/user/{user_id}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('toggle_user_status');
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit');
    Route::get('/get-user-skills/{user_id}', [AdminController::class, 'getUserSkills'])->name('get_user_skills');
});

// Manager Routes (Manager role required)
Route::middleware(['auth', 'role:Manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerController::class, 'dashboard'])->name('dashboard');
    Route::get('/assign-task', [ManagerController::class, 'assignTaskForm'])->name('assign_tasks');
    Route::post('/assign-task', [ManagerController::class, 'assignTask']);
    Route::get('/verify-task', [ManagerController::class, 'verifyTaskForm'])->name('verify_tasks');
    Route::post('/verify-task/{task_id}/{action}', [ManagerController::class, 'verifyTask'])->name('verify_task_action');
    Route::get('/suggest-candidate', [ManagerController::class, 'suggestCandidate'])->name('suggest_candidate');
    Route::get('/notifications', [ManagerController::class, 'notifications'])->name('notification');
});

// Employee Routes (Employee role required)
Route::middleware(['auth', 'role:Employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/dashboard', [EmployeeController::class, 'dashboard'])->name('dashboard');
    Route::get('/tasks', [EmployeeController::class, 'tasks'])->name('tasks');
    Route::post('/start-task', [EmployeeController::class, 'startTask'])->name('start_task');
    Route::post('/submit-work', [EmployeeController::class, 'submitWork'])->name('submit_work');
    Route::get('/profile', [EmployeeController::class, 'skills'])->name('skills');
    Route::post('/profile/save-skill', [EmployeeController::class, 'saveSkill'])->name('save_skill');
    Route::post('/profile/delete-skill', [EmployeeController::class, 'deleteSkill'])->name('delete_skill');
    Route::post('/profile/save-department', [EmployeeController::class, 'saveDepartment'])->name('save_department');
    Route::post('/profile/update-email', [EmployeeController::class, 'updateEmail'])->name('update_email');
    Route::get('/performance', [EmployeeController::class, 'performance'])->name('performance');
    Route::get('/notifications', [EmployeeController::class, 'notifications'])->name('notification');
    Route::post('/ai-coach', [EmployeeController::class, 'aiCoach'])->name('ai_coach');
});
