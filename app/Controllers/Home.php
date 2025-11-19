<?php

namespace App\Controllers;

use App\Models\PackageModel;

class Home extends BaseController
{
    protected PackageModel $packageModel;

    public function __construct()
    {
        $this->packageModel = new PackageModel();
        helper(['form', 'url']);
    }

    public function index(): string
    {
        $packages = $this->packageModel->findAll();
        return view('home/index', ['packages' => $packages]);
    }
}
