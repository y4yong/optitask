<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\AuditLog;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle authentication login attempt.
     */
    public function login(Request $request)
    {
        $request->validate([
            'worker_id' => 'required|string',
            'password' => 'required|string',
        ]);

        $workerId = strtoupper($request->input('worker_id'));
        $password = $request->input('password');
        $user = User::find($workerId);

        if ($user) {
            if ($user->account_status === 'Suspended') {
                $reason = $user->suspension_reason ? " Reason: " . $user->suspension_reason : "";
                return back()->withErrors([
                    'worker_id' => 'Access Denied: Your account has been suspended.' . $reason
                ])->withInput();
            }

            if (Hash::check($password, $user->password)) {
                Auth::login($user);
                AuditLog::log($user->user_id, 'LOGIN', 'User logged in successfully');

                if ($user->role === 'Admin') {
                    return redirect()->route('admin.dashboard');
                } elseif ($user->role === 'Manager') {
                    return redirect()->route('manager.dashboard');
                } elseif (strtolower($user->role) === 'employee') {
                    if (!$user->isProfileComplete()) {
                        return redirect()->route('employee.skills')->with('warning', 'Notice: Please select your department and add at least one skill to complete your profile and unlock your task dashboard.');
                    }
                    return redirect()->route('employee.dashboard');
                }
            }
        }

        return back()->withErrors([
            'worker_id' => 'Error: Invalid Worker ID or password.'
        ])->withInput();
    }

    /**
     * Show the registration form.
     */
    public function showSignup()
    {
        return view('auth.signup');
    }

    /**
     * Handle account registration.
     */
    public function signup(Request $request)
    {
        $request->validate([
            'worker_id' => 'required|string',
            'security_word' => 'required|string',
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:password',
        ]);

        $userId = strtoupper($request->input('worker_id'));
        $securityWord = $request->input('security_word');
        $fullName = $request->input('full_name');
        $email = $request->input('email');
        $password = $request->input('password');

        // Check if user already exists
        if (User::find($userId)) {
            return back()->withErrors(['worker_id' => 'Error: This Worker ID is already registered.'])->withInput();
        }

        // Determine required key and role
        $prefix = substr($userId, 0, 2);
        $role = 'Employee';
        $requiredKey = 'STAFF_UKM';

        if ($prefix === 'AD') {
            $role = 'Admin';
            $requiredKey = 'ADMIN_BOSS';
        } elseif ($prefix === 'MG') {
            $role = 'Manager';
            $requiredKey = 'MGR_OPTI';
        }

        // Security key validation
        if ($securityWord !== $requiredKey) {
            return back()->withErrors([
                'security_word' => 'Access Denied: Invalid Security Word for the ' . $role . ' role.'
            ])->withInput();
        }

        $user = User::create([
            'user_id' => $userId,
            'username' => $fullName,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'account_status' => 'Active',
            'dept_id' => null,
        ]);

        AuditLog::log($userId, 'REGISTER', "Registered new account as $role");

        return redirect()->route('login')->with('success', 'Success: ' . $role . ' account created. You may now login.');
    }

    /**
     * Log user out of the application.
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId) {
            AuditLog::log($userId, 'LOGOUT', 'User logged out');
        }

        return redirect()->route('login');
    }
}
