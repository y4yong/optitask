<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use App\Models\User;

class FileController extends Controller
{
    /**
     * Download or inline view a task file or submission file.
     *
     * @param string $taskId
     * @param string $type ('task' or 'submission')
     */
    public function download(Request $request, $taskId, $type)
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();
        $user = Auth::user();

        // Access Authorization
        if ($user->role === 'Employee' && $task->employee_id !== $user->user_id) {
            abort(403, 'Unauthorized access to task file.');
        }

        if ($user->role === 'Manager') {
            if ($task->manager_id && $task->manager_id !== $user->user_id) {
                // If assigned to another manager, check if in same department or general access
                if ($user->dept_id && $task->employee && $task->employee->dept_id && $user->dept_id !== $task->employee->dept_id) {
                    abort(403, 'Unauthorized access to task file.');
                }
            }
        }

        $rawPath = ($type === 'submission') ? $task->submission_file : $task->task_file;

        if (empty($rawPath)) {
            return back()->with('error', 'No file attached to this task.');
        }

        // If path is a full external URL
        if (str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
            return redirect()->away($rawPath);
        }

        // Clean up leading relative markers or prefixes
        $cleanPath = preg_replace('#^(\.\./|\./|storage/|public/)*#', '', $rawPath);
        $fileName = basename($cleanPath);
        $subFolder = ($type === 'submission') ? 'uploads/submissions/' : 'uploads/tasks/';

        // 1. Try Laravel Storage disk 'public' (Cloud & local storage compatible)
        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->response($cleanPath);
        }

        if (Storage::disk('public')->exists($subFolder . $fileName)) {
            return Storage::disk('public')->response($subFolder . $fileName);
        }

        // 2. Try public_path directly
        $publicFilePath = public_path($cleanPath);
        if (file_exists($publicFilePath) && is_file($publicFilePath)) {
            return response()->file($publicFilePath);
        }

        $publicFallback = public_path($subFolder . $fileName);
        if (file_exists($publicFallback) && is_file($publicFallback)) {
            return response()->file($publicFallback);
        }

        // 3. Try storage_path app/public
        $storageFilePath = storage_path('app/public/' . $cleanPath);
        if (file_exists($storageFilePath) && is_file($storageFilePath)) {
            return response()->file($storageFilePath);
        }

        $storageFallback = storage_path('app/public/' . $subFolder . $fileName);
        if (file_exists($storageFallback) && is_file($storageFallback)) {
            return response()->file($storageFallback);
        }

        // 4. Try storage_path app
        $appStoragePath = storage_path('app/' . $cleanPath);
        if (file_exists($appStoragePath) && is_file($appStoragePath)) {
            return response()->file($appStoragePath);
        }

        return back()->with('error', 'File not found on server storage: ' . $fileName);
    }
}
