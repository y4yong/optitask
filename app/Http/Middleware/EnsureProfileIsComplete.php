<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && strtolower($user->role) === 'employee') {
            if (!$user->isProfileComplete()) {
                $allowedRouteNames = [
                    'employee.skills',
                    'employee.save_skill',
                    'employee.delete_skill',
                    'employee.save_department',
                    'employee.update_email',
                    'logout',
                    'task.file.download',
                ];

                $currentRouteName = $request->route() ? $request->route()->getName() : null;

                if (!in_array($currentRouteName, $allowedRouteNames)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Profile incomplete. Please select a department and add at least one skill first.'
                        ], 403);
                    }

                    return redirect()->route('employee.skills')->with('warning', 'Action Restricted: Please select your department and add at least one skill to complete your profile and unlock full workspace features.');
                }
            }
        }

        return $next($request);
    }
}
