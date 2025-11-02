<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MpesaLogsModel;

class MpesaLogs extends BaseController
{
    public function index()
    {
        $model = new MpesaLogsModel();

        // Fetch last 50 logs (newest first)
        $logs = $model->orderBy('id', 'DESC')->findAll(50);

        // If AJAX request (for auto-refresh)
        if ($this->request->isAJAX()) {
            return view('admin/mpesa_logs_table', ['logs' => $logs]);
        }

        // Normal page load
        return view('admin/mpesa_logs', ['logs' => $logs]);
    }
}
