<?php
namespace App\Controllers;

use App\Models\RouterModel;
use Config\Database;

class Routers extends BaseController
{
    protected $routerModel;

    public function __construct()
    {
        $this->routerModel = new RouterModel();
    }

    protected function loginCheck() {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error','Please login')->send();
        }
    }

    public function index()
    {
        $this->loginCheck();
        $data['routers'] = $this->routerModel->orderBy('id','DESC')->findAll();
        echo view('admin/routers/index', $data);
    }

    public function create()
    {
        $this->loginCheck();
        echo view('admin/routers/create');
    }

    public function store()
    {
        try {
            $data = $this->request->getPost();

            if ($this->routerModel->save($data)) {
                return redirect()->to('/admin/routers')->with('success', 'Router created successfully.');
            }

            $dbError = $this->routerModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to create router: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->loginCheck();
        $router = $this->routerModel->find($id);
        if (! $router) return redirect()->to('/admin/routers')->with('error','Router not found');

        echo view('admin/routers/edit', ['router' => $router]);
    }

    public function update($id)
    {
        try {
            $data = $this->request->getPost();

            if ($this->routerModel->update($id, $data)) {
                return redirect()->to('/admin/routers')->with('success', 'Router updated successfully.');
            }

            $dbError = $this->routerModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update router: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            if ($this->routerModel->delete($id)) {
                return redirect()->to('/admin/routers')->with('success', 'Router deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/routers')->with('error', 'Failed to delete router: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/routers')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
