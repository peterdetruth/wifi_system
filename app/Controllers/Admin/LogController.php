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
        // --- Filters ---
        $level   = $this->request->getGet('level');
        $type    = $this->request->getGet('type');
        $userId  = $this->request->getGet('user_id');
        $from    = $this->request->getGet('from');
        $to      = $this->request->getGet('to');

        // --- Sorting ---
        $sort = $this->request->getGet('sort');
        $dir  = $this->request->getGet('dir') ?? 'asc';

        // Secure allowed sortable columns
        $allowedSort = ['id', 'level', 'type', 'user_id', 'created_at'];

        $builder = $this->logModel;

        // --- Apply filters ---
        if (!empty($level)) {
            $builder = $builder->where('level', $level);
        }

        if (!empty($type)) {
            $builder = $builder->where('type', $type);
        }

        if (!empty($userId)) {
            $builder = $builder->where('user_id', $userId);
        }

        if (!empty($from)) {
            $builder = $builder->where('created_at >=', $from . ' 00:00:00');
        }

        if (!empty($to)) {
            $builder = $builder->where('created_at <=', $to . ' 23:59:59');
        }

        // --- Apply sorting ---
        if ($sort && in_array($sort, $allowedSort)) {
            $builder = $builder->orderBy($sort, $dir);
        } else {
            // Default sorting
            $builder = $builder->orderBy('created_at', 'desc');
        }

        // --- Pagination ---
        $perPage = 20;
        $logs = $builder->paginate($perPage, 'logs');
        $pager = $builder->pager;

        // --- Pass to view ---
        return view('admin/logs/index', [
            'logs'       => $logs,
            'pager'      => $pager,
            'level'      => $level,
            'type'       => $type,
            'user_id'    => $userId,
            'from'       => $from,
            'to'         => $to,
            'sort'       => $sort,
            'dir'        => $dir,
            'title'      => 'System Logs'
        ]);
    }
}
