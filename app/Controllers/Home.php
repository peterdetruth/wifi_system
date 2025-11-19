<?php

namespace App\Controllers;

use App\Models\PackageModel;

class Home extends BaseController
{
    public function index(): string
    {
        $packageModel = new PackageModel();
        $packages = $packageModel->findAll();

        return view('home', ['packages' => $packages]);
    }
}

