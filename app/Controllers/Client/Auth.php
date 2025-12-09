<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Services\LogService;
use Config\Database;

class Auth extends BaseController
{
    protected $clientModel;
    protected LogService $logService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->clientModel = new ClientModel();
        $this->logService = new LogService();
    }

    public function register()
    {
        if (session()->get('client_logged_in')) {
            return redirect()->to('/client/dashboard');
        }

        return view('client/auth/register', ['title' => 'Client Register']);
    }

    public function registerPost()
    {
        try {
            $post = $this->request->getPost();

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
                $clientId = $this->clientModel->getInsertID();
                $this->logService->info(
                    'registration',
                    'Client registered successfully',
                    ['username' => $post['username'], 'email' => $post['email']],
                    $clientId,
                    $this->request->getIPAddress()
                );

                return redirect()->to('/client/login')->with('success', 'Registration successful. Please login.');
            }

            $dbError = $this->clientModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to register: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function login()
    {
        if (session()->get('client_logged_in')) {
            return redirect()->to('/client/dashboard');
        }

        return view('client/auth/login', ['title' => 'Client Login']);
    }

    public function loginPost()
    {
        try {
            $post = $this->request->getPost();
            $rules = ['username' => 'required', 'password' => 'required'];

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('error', $this->validator->getErrors());
            }

            $identifier = $post['username'];
            $client = $this->clientModel
                ->where('username', $identifier)
                ->orWhere('email', $identifier)
                ->orWhere('phone', $identifier)
                ->first();

            $ip = $this->request->getIPAddress();

            if (! $client) {
                $this->logService->warning(
                    'login',
                    'Client login failed: user not found',
                    ['identifier' => $identifier],
                    null,
                    $ip
                );
                return redirect()->back()->withInput()->with('error', 'User not found.');
            }

            if (! password_verify($post['password'], $client['password'])) {
                $this->logService->warning(
                    'login',
                    'Client login failed: invalid password',
                    ['username' => $client['username']],
                    $client['id'],
                    $ip
                );
                return redirect()->back()->withInput()->with('error', 'Invalid credentials.');
            }

            session()->set([
                'client_id'        => $client['id'],
                'client_username'  => $client['username'],
                'client_full_name' => $client['full_name'],
                'client_logged_in' => true
            ]);

            $this->logService->info(
                'login',
                'Client login successful',
                ['username' => $client['username']],
                $client['id'],
                $ip
            );

            return redirect()->to('/client/dashboard')->with('success', 'Welcome back, ' . $client['full_name']);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function logout()
    {
        $session = session();
        $clientId = $session->get('client_id');
        $username = $session->get('client_username');
        $ip = $this->request->getIPAddress();

        $this->logService->info(
            'login',
            'Client logged out',
            ['username' => $username],
            $clientId,
            $ip
        );

        session()->remove(['client_logged_in', 'client_id', 'client_name', 'client_email']);
        session()->destroy();

        return redirect()
            ->to(base_url('client/login'))
            ->with('success', 'You have been logged out successfully.');
    }
}
