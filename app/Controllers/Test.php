<?php

namespace App\Controllers;

class Test extends BaseController
{
    public function envcheck()
    {
        echo 'Key: ' . getenv('MPESA_CONSUMER_KEY');
    }
}
