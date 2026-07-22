<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;

class FileController extends Controller
{
    /**
     * Download or inline view a task file or submission file.
     *
     * @param Request $request
     * @param string $taskId
     * @param string $type ('task' or 'submission')
     */
    public function download(Request $request, $taskId, $type)
    {
        $task = Task::where('task_id', $taskId)->first();

        if (!$task) {
            return $this->renderErrorPage('Task Not Found', "Task with ID '{$taskId}' could not be located.");
        }

        $user = Auth::user();

        // Access Authorization:
        // - Admin & Manager can view any task/submission files
        // - Employee can view files for tasks assigned to them
        if ($user->role === 'Employee' && $task->employee_id !== $user->user_id) {
            return $this->renderErrorPage('Access Restricted', 'You do not have permission to view files for this assignment.');
        }

        $rawPath = ($type === 'submission') ? $task->submission_file : $task->task_file;

        if (empty($rawPath)) {
            return $this->renderErrorPage('No File Attached', 'No attachment file was provided for this task.');
        }

        // If path is a full external URL
        if (str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
            return redirect()->away($rawPath);
        }

        // Clean path and generate filename
        $cleanPath = preg_replace('#^(\.\./|\./|storage/|public/)*#', '', $rawPath);
        $fileName = basename($cleanPath);
        $subFolder = ($type === 'submission') ? 'uploads/submissions/' : 'uploads/tasks/';

        $candidatePaths = array_unique([
            $cleanPath,
            $subFolder . $fileName,
            $fileName,
            'public/' . $cleanPath,
            'app/public/' . $cleanPath,
        ]);

        $disksToTest = array_unique([
            'public',
            'local',
            config('filesystems.default', 'local'),
        ]);

        // 1. Try Flysystem Storage Disks (Laravel Cloud & Local Storage compatible)
        foreach ($disksToTest as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                foreach ($candidatePaths as $relPath) {
                    if ($disk->exists($relPath)) {
                        $content = $disk->get($relPath);
                        $mimeType = method_exists($disk, 'mimeType') ? (@$disk->mimeType($relPath) ?: 'application/octet-stream') : 'application/octet-stream';
                        
                        if ($mimeType === 'application/octet-stream' || str_ends_with(strtolower($fileName), '.pdf')) {
                            if (str_ends_with(strtolower($fileName), '.pdf')) $mimeType = 'application/pdf';
                            elseif (str_ends_with(strtolower($fileName), '.jpg') || str_ends_with(strtolower($fileName), '.jpeg')) $mimeType = 'image/jpeg';
                            elseif (str_ends_with(strtolower($fileName), '.png')) $mimeType = 'image/png';
                            elseif (str_ends_with(strtolower($fileName), '.avif')) $mimeType = 'image/avif';
                            elseif (str_ends_with(strtolower($fileName), '.webp')) $mimeType = 'image/webp';
                        }

                        return response($content, 200, [
                            'Content-Type'        => $mimeType,
                            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                            'Cache-Control'       => 'no-cache, private',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Continue checking other disks/paths
            }
        }

        // 2. Try direct filesystem paths (Public path & Storage path)
        $physicalPaths = [
            public_path($cleanPath),
            public_path($subFolder . $fileName),
            storage_path('app/public/' . $cleanPath),
            storage_path('app/public/' . $subFolder . $fileName),
            storage_path('app/' . $cleanPath),
            storage_path('app/' . $subFolder . $fileName),
        ];

        foreach ($physicalPaths as $absPath) {
            if (file_exists($absPath) && is_file($absPath)) {
                return response()->file($absPath, [
                    'Content-Disposition' => 'inline; filename="' . $fileName . '"'
                ]);
            }
        }

        return $this->renderErrorPage('File Attachment Not Found', "The requested file <strong>" . e($fileName) . "</strong> could not be found on server storage. It may have been deleted or not uploaded properly.");
    }

    /**
     * Render a styled error page instead of redirecting back.
     */
    private function renderErrorPage($title, $message)
    {
        $html = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title} - OptiTask</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #FFF9FA; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
                .card { background: #ffffff; padding: 40px; border-radius: 28px; border: 2px solid #FFE5EC; text-align: center; max-width: 460px; width: 100%; box-shadow: 0 20px 40px rgba(251, 111, 146, 0.08); }
                .icon { width: 56px; height: 56px; background: #FFE5EC; color: #FB6F92; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; font-size: 24px; font-weight: bold; }
                h2 { font-size: 20px; font-weight: 800; margin: 0 0 10px 0; color: #1e293b; text-transform: uppercase; letter-spacing: -0.02em; }
                p { font-size: 13px; color: #64748b; font-weight: 600; line-height: 1.6; margin: 0 0 24px 0; }
                .btn { display: inline-block; width: 100%; padding: 14px 0; background: #FB6F92; color: #ffffff; border-radius: 16px; text-decoration: none; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: 0 8px 16px rgba(251, 111, 146, 0.25); transition: all 0.2s; border: none; cursor: pointer; }
                .btn:hover { background: #ff5c83; transform: translateY(-1px); }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='icon'>!</div>
                <h2>{$title}</h2>
                <p>{$message}</p>
                <button onclick='window.close()' class='btn'>Close Window</button>
            </div>
        </body>
        </html>
        ";

        return response($html, 404)->header('Content-Type', 'text/html');
    }
}
