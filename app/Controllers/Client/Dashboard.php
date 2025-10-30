<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\SubscriptionModel;
use App\Models\VoucherModel;

class Dashboard extends BaseController
{
    protected $subscriptionModel;
    protected $voucherModel;

    public function __construct()
    {
        $this->subscriptionModel = new SubscriptionModel();
        $this->voucherModel = new VoucherModel();
        helper(['url', 'form', 'time']);
    }

    /**
     * Display client dashboard with subscriptions and redeemed/used vouchers
     */
    public function index()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login');
        }

        // Fetch subscriptions for this client
        $subscriptions = $this->subscriptionModel
            ->select('
                subscriptions.*,
                packages.name AS package_name,
                packages.duration_length,
                packages.duration_unit,
                routers.name AS router_name
            ')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->join('routers', 'routers.id = subscriptions.router_id', 'left')
            ->where('subscriptions.client_id', $clientId)
            ->orderBy('subscriptions.start_date', 'DESC')
            ->findAll();

        // Fetch vouchers that have been redeemed/used by this client
        $vouchers = $this->voucherModel
            ->select('
                vouchers.*,
                packages.name AS package_name,
                packages.duration_length,
                packages.duration_unit
            ')
            ->join('packages', 'packages.id = vouchers.package_id', 'left')
            ->where('vouchers.used_by_client_id', $clientId)
            ->orderBy('vouchers.updated_at', 'DESC')
            ->findAll();

        $data = [
            'title' => 'Dashboard',
            'subscriptions' => $subscriptions,
            'vouchers' => $vouchers
        ];

        return view('client/dashboard/index', $data);
    }
}
