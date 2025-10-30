<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use Config\Database;

class Auth extends BaseController
{
    protected $clientModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->clientModel = new ClientModel();
    }

    /**
     * Show registration form
     */
    public function register()
    {
        // If logged in, redirect to dashboard
        if (session()->get('client_logged_in')) {
            return redirect()->to('/client/dashboard');
        }

        $data = [
            'title' => 'Client Register',
        ];
        return view('client/auth/register', $data);
    }

    /**
     * Handle registration POST
     */
    public function registerPost()
    {
        try {
            $post = $this->request->getPost();

            // Basic validation (Model also has rules)
            $rules = [
                'full_name' => 'required|min_length[3]',
                'username'  => 'required|min_length[3]|is_unique[clients.username]',
                'email'     => 'permit_empty|valid_email|is_unique[clients.email]',
                'password'  => 'required|min_length[6]',
                'phone'     => 'required|min_length[6]'
            ];

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('error', $this->validator->getErrors());
            }

            $saveData = [
                'full_name'    => $post['full_name'],
                'username'     => $post['username'],
                'account_type' => $post['account_type'] ?? 'personal',
                'email'        => $post['email'] ?? null,
                'password'     => password_hash($post['password'], PASSWORD_DEFAULT),
                'phone'        => $post['phone'],
                'status'       => 'active'
            ];

            if ($this->clientModel->save($saveData)) {
                return redirect()->to('/client/login')->with('success', 'Registration successful. Please login.');
            }

            // Model errors or DB error
            $dbError = $this->clientModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to register: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Show login form
     */
    public function login()
    {
        if (session()->get('client_logged_in')) {
            return redirect()->to('/client/dashboard');
        }

        $data = [
            'title' => 'Client Login',
        ];
        return view('client/auth/login', $data);
    }

    /**
     * Handle login POST
     */
    public function loginPost()
    {
        try {
            $post = $this->request->getPost();
            $rules = [
                'username' => 'required',
                'password' => 'required'
            ];

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('error', $this->validator->getErrors());
            }

            // Allow login by username or phone or email (try username first)
            $identifier = $post['username'];

            $client = $this->clientModel
                ->where('username', $identifier)
                ->orWhere('email', $identifier)
                ->orWhere('phone', $identifier)
                ->first();

            if (! $client) {
                return redirect()->back()->withInput()->with('error', 'User not found.');
            }

            if (! password_verify($post['password'], $client['password'])) {
                return redirect()->back()->withInput()->with('error', 'Invalid credentials.');
            }

            // Set session for client
            session()->set([
                'client_id'        => $client['id'],
                'client_username'  => $client['username'],
                'client_full_name' => $client['full_name'],
                'client_logged_in' => true
            ]);

            return redirect()->to('/client/dashboard')->with('success', 'Welcome back, ' . $client['full_name']);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Logout client
     */
    public function logout()
    {
        // ✅ Clear client session
        session()->remove(['client_logged_in', 'client_id', 'client_name', 'client_email']);
        session()->destroy();

        // ✅ Redirect with flash message
        return redirect()
            ->to(base_url('client/login'))
            ->with('success', 'You have been logged out successfully.');
    }
}
