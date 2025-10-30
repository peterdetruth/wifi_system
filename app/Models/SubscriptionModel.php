<?php namespace App\Models;

use CodeIgniter\Model;

class SubscriptionModel extends Model
{
    protected $table = 'subscriptions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'package_id',
        'router_id',
        'start_date',
        'expires_on',
        'status',
    ];

    protected $useTimestamps = false; // handled manually via start_date & expires_on
    protected $returnType    = 'array';

    /**
     * ✅ Get all subscriptions for a specific client (joined with package + router)
     */
    public function getClientSubscriptions($clientId)
    {
        return $this->select('
                subscriptions.*,
                packages.name AS package_name,
                packages.type AS package_type,
                packages.account_type AS package_account_type,
                packages.price AS package_price,
                routers.name AS router_name,
                routers.ip_address AS router_ip
            ')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->join('routers', 'routers.id = subscriptions.router_id', 'left')
            ->where('subscriptions.client_id', $clientId)
            ->orderBy('subscriptions.start_date', 'DESC')
            ->findAll();
    }

    /**
     * ✅ Get a single subscription with all details
     */
    public function getSubscriptionWithDetails($id, $clientId = null)
    {
        $builder = $this->select('
                subscriptions.*,
                packages.name AS package_name,
                packages.type AS package_type,
                packages.account_type AS package_account_type,
                packages.price AS package_price,
                packages.duration_length,
                packages.duration_unit,
                routers.name AS router_name,
                routers.ip_address AS router_ip
            ')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->join('routers', 'routers.id = subscriptions.router_id', 'left')
            ->where('subscriptions.id', $id);

        if ($clientId !== null) {
            $builder->where('subscriptions.client_id', $clientId);
        }

        return $builder->first();
    }

    /**
     * ✅ Cancel a subscription safely
     */
    public function cancelSubscription($id, $clientId)
    {
        return $this->where('id', $id)
                    ->where('client_id', $clientId)
                    ->set(['status' => 'cancelled'])
                    ->update();
    }

    /**
     * ✅ Create a new subscription record
     */
    public function createSubscription($data)
    {
        try {
            return $this->insert($data, true);
        } catch (\Exception $e) {
            log_message('error', 'Failed to create subscription: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Auto mark expired subscriptions as expired
     */
    public function deactivateExpired()
    {
        return $this->where('expires_on <', date('Y-m-d H:i:s'))
                    ->where('status', 'active')
                    ->set(['status' => 'expired'])
                    ->update();
    }

        /**
     * ✅ Get all expired but still active subscriptions
     */
    public function getExpiredActiveSubscriptions(): array
    {
        return $this->where('status', 'active')
                    ->where('expires_on <', date('Y-m-d H:i:s'))
                    ->findAll();
    }
}
