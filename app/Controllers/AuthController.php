<?php namespace App\Controllers;

use App\Models\AdminModel;
use CodeIgniter\Controller;

class AuthController extends Controller
{
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

        $admin = $model->where('email', $email)->first();

        if ($admin && password_verify($password, $admin['password'])) {
            $session->set([
                'isLoggedIn' => true,
                'admin_id'   => $admin['id'],
                'username'   => $admin['username'],
                'role'       => $admin['role']
            ]);
            return redirect()->to('/admin/dashboard');
        } else {
            return redirect()->back()->with('error', 'Invalid login credentials');
        }
    }

    public function logout()
    {
        // ✅ Clear admin session
        session()->remove(['isLoggedIn', 'admin_id', 'username', 'role']);
        session()->destroy();

        // ✅ Redirect with flash message
        return redirect()
            ->to(base_url('/login'))
            ->with('success', 'You have been logged out successfully.');
    }
}
