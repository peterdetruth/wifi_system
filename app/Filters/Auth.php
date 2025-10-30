<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class Auth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // ✅ Check if admin is logged in
        if (!session()->get('isLoggedIn') || !session()->get('admin_id')) {
            return redirect()
                ->to(base_url('/login'))
                ->with('error', 'Please log in as an admin first.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing after
    }
}
