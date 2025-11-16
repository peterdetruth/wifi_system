<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FeatureRequestModel;

class Features extends BaseController
{
    public function index()
    {
        $model = new FeatureRequestModel();
        $data['features'] = $model->orderBy('id', 'DESC')->findAll();

        return view('admin/features/index', $data);
    }

    public function create()
    {
        return view('admin/features/create');
    }

    public function store()
    {
        $model = new FeatureRequestModel();

        $model->save([
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description')
        ]);

        return redirect()->to('/admin/features')->with('success', 'Feature added successfully.');
    }

    public function markComplete($id)
    {
        $model = new FeatureRequestModel();
        $model->update($id, ['status' => 'complete']);

        return redirect()->to('/admin/features')->with('success', 'Feature marked as complete.');
    }

    public function delete($id)
    {
        $model = new FeatureRequestModel();
        $model->delete($id);

        return redirect()->to('/admin/features')->with('success', 'Feature deleted successfully.');
    }
}
