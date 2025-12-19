<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\SubscriptionModel;
use App\Models\RouterModel;
use App\Models\PackageModel;
use App\Libraries\RouterService;

class Subscriptions extends BaseController
{
    protected $subscriptionModel;
    protected $routerModel;
    protected $packageModel;

    public function __construct()
    {
        $this->subscriptionModel = new SubscriptionModel();
        $this->routerModel = new RouterModel();
        $this->packageModel = new PackageModel();
        helper(['form', 'url']);
    }

    /**
     * Display all subscriptions for the logged-in client
     */
    public function index()
    {
        $clientId = session()->get('client_id');

        $perPage = 10; // Number of subscriptions per page

        $subscriptions = $this->subscriptionModel
            ->select('subscriptions.*, 
                packages.name AS package_name, 
                packages.account_type AS package_account_type, 
                packages.type AS package_type, 
                packages.price AS price, 
                routers.name AS router_name')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->join('routers', 'routers.id = subscriptions.router_id', 'left')
            ->where('subscriptions.client_id', $clientId)
            ->orderBy('subscriptions.start_date', 'DESC')
            ->paginate($perPage); // <- Use paginate instead of findAll

        $pager = $this->subscriptionModel->pager;

        $data = [
            'title' => 'My Subscriptions',
            'subscriptions' => $subscriptions,
            'pager' => $pager,
            'perPage' => $perPage
        ];

        return view('client/subscriptions/index', $data);
    }


    /**
     * View single subscription details
     */
    public function view($id)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login');
        }

        $subscription = $this->subscriptionModel
            ->select('subscriptions.*, 
                    packages.name AS package_name, 
                    packages.account_type AS package_account_type, 
                    packages.type AS package_type, 
                    packages.price AS price, 
                    routers.name AS router_name')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->join('routers', 'routers.id = subscriptions.router_id', 'left')
            ->where('subscriptions.client_id', $clientId)
            ->where('subscriptions.id', $id)
            ->first();

        if (!$subscription) {
            return redirect()->to('/client/subscriptions')->with('error', 'Subscription not found.');
        }

        $data = [
            'title' => 'Subscription Details',
            'subscription' => $subscription
        ];

        return view('client/subscriptions/view', $data);
    }

    /**
     * Cancel a subscription (if still active)
     */
    public function cancel($id)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login');
        }

        try {
            $updated = $this->subscriptionModel->cancelSubscription($id, $clientId);

            if ($updated) {
                return redirect()->to('/client/subscriptions')->with('success', 'Subscription cancelled successfully.');
            }

            return redirect()->to('/client/subscriptions')->with('error', 'Unable to cancel subscription.');
        } catch (\Exception $e) {
            return redirect()->to('/client/subscriptions')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Attempt to reconnect user via RouterOS
     */
    public function reconnect($id)
    {
        $subscription = $this->subscriptionModel->find($id);
        if (!$subscription) {
            return redirect()->back()->with('error', 'Subscription not found.');
        }

        if ($subscription['status'] !== 'active') {
            return redirect()->back()->with('error', 'Subscription not active.');
        }

        $routerService = new \App\Libraries\RouterService(simulate: false);
        $clientUsername = session()->get('client_username') ?? 'client_' . $subscription['client_id'];

        $result = $routerService->reconnectClient($subscription['router_id'], $clientUsername);

        if ($result) {
            return redirect()->back()->with('success', 'Reconnected successfully.');
        }

        return redirect()->back()->with('error', 'Failed to reconnect.');
    }

    /**
     * Renew a package — redirects client to payment or package view
     */
    public function renew($id)
    {
        $subscription = $this->subscriptionModel->find($id);

        if (!$subscription) {
            return redirect()->to('/client/subscriptions')->with('error', 'Subscription not found.');
        }

        return redirect()->to('/client/packages/view/' . $subscription['package_id']);
    }
}
