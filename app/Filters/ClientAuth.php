<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class ClientAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if the client is logged in
        if (!session()->get('client_logged_in')) {
            // Redirect to client login if not logged in
            return redirect()->to(base_url('client/login'))->with('error', 'Please log in first.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing after request
    }
}
