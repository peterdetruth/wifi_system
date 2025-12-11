<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LogModel;

class LogController extends BaseController
{
    protected $logModel;

    public function __construct()
    {
        $this->logModel = new LogModel();
    }

    public function index()
    {
        $level   = $this->request->getGet('level');
        $type    = $this->request->getGet('type');
        $userId  = $this->request->getGet('user_id');
        $from    = $this->request->getGet('from');
        $to      = $this->request->getGet('to');

        $builder = $this->logModel;

        if ($level) {
            $builder = $builder->where('level', $level);
        }

        if ($type) {
            $builder = $builder->where('type', $type);
        }

        if ($userId) {
            $builder = $builder->where('user_id', $userId);
        }

        if ($from) {
            $builder = $builder->where('created_at >=', $from . ' 00:00:00');
        }

        if ($to) {
            $builder = $builder->where('created_at <=', $to . ' 23:59:59');
        }

        $perPage = 20;
        $logs    = $builder->orderBy('created_at', 'desc')->paginate($perPage, 'logs');
        $pager   = $builder->pager;

        return view('admin/logs/index', [
            'logs'     => $logs,
            'pager'    => $pager,
            'level'    => $level,
            'type'     => $type,
            'user_id'  => $userId,
            'from'     => $from,
            'to'       => $to,
            'title'    => 'System Logs'
        ]);
    }
}
