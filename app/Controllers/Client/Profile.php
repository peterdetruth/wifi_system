<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\ClientModel;

class Profile extends BaseController
{
    public function index()
    {
        $clientModel = new ClientModel();
        $clientId = session()->get('client_id');

        try {
            $data['client'] = $clientModel->find($clientId);
        } catch (\Exception $e) {
            session()->setFlashdata('error', 'Error loading profile: ' . $e->getMessage());
            $data['client'] = null;
        }

        return view('client/profile/index', $data);
    }

    public function update()
    {
        $clientModel = new ClientModel();
        $clientId = session()->get('client_id');

        $data = [
            'full_name' => $this->request->getPost('full_name'),
            'email'     => $this->request->getPost('email'),
            'phone'     => $this->request->getPost('phone'),
        ];

        $validationRules = [
            'full_name' => 'required|min_length[3]',
            'email'     => 'required|valid_email',
            'phone'     => 'required|min_length[10]|max_length[15]',
        ];

        if (! $this->validate($validationRules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        try {
            $clientModel->update($clientId, $data);
            session()->setFlashdata('success', 'Profile updated successfully!');
        } catch (\Exception $e) {
            session()->setFlashdata('error', 'Error updating profile: ' . $e->getMessage());
        }

        return redirect()->to('/client/profile');
    }
}
