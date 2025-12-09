<?php

namespace App\Controllers;

use App\Models\AdminModel;
use CodeIgniter\Controller;
use App\Services\LogService;

class AuthController extends Controller
{
    protected LogService $logService;

    public function __construct()
    {
        $this->logService = new LogService();
    }

    public function login()
    {
        return view('auth/login');
    }

    public function processLogin()
    {
        $session = session();
        $model = new AdminModel();

        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        $ip = $this->request->getIPAddress();

        $admin = $model->where('email', $email)->first();

        if ($admin && password_verify($password, $admin['password'])) {
            $session->set([
                'isLoggedIn' => true,
                'admin_id'   => $admin['id'],
                'username'   => $admin['username'],
                'role'       => $admin['role']
            ]);

            // ✅ Log successful login
            $this->logService->info(
                'login',
                "Admin login successful",
                ['email' => $email, 'role' => $admin['role']],
                $admin['id'],
                $ip
            );

            return redirect()->to('/admin/dashboard');
        } else {
            // ✅ Log failed login attempt
            $this->logService->warning(
                'login',
                "Admin login failed",
                ['email' => $email],
                $admin['id'] ?? null,
                $ip
            );

            return redirect()->back()->with('error', 'Invalid login credentials');
        }
    }

    public function logout()
    {
        $session = session();
        $adminId = $session->get('admin_id');
        $username = $session->get('username');
        $ip = $this->request->getIPAddress();

        // ✅ Log logout
        $this->logService->info(
            'login',
            "Admin logged out",
            ['username' => $username],
            $adminId,
            $ip
        );

        // Clear session
        $session->remove(['isLoggedIn', 'admin_id', 'username', 'role']);
        $session->destroy();

        return redirect()
            ->to(base_url('/login'))
            ->with('success', 'You have been logged out successfully.');
    }
}
