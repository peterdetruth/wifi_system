<?php

namespace App\Controllers;

use App\Services\RouterProvisioningService;

class TestProvisioning extends BaseController
{
    public function run()
    {
        $service = new RouterProvisioningService();
        $service->runOnce();

        return 'Provisioning test executed';
    }
}
