<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Call lazy expiry system-wide
        $this->autoExpireSubscriptions();

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = service('session');
    }

    protected function isSuperAdmin(): bool
    {
        return session()->get('role') === 'superadmin';
    }

    protected function requireLogin()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }
    }

    protected function autoExpireSubscriptions()
    {
        $db = \Config\Database::connect();

        /**
         * 1️⃣ EXPIRE SUBSCRIPTIONS THAT HAVE PASSED THEIR EXPIRY TIME
         */
        $expiredSubs = $db->table('subscriptions')
            ->select('id, client_id, package_id, expires_on')
            ->where('status', 'active')
            ->where('expires_on <', date('Y-m-d H:i:s'))
            ->get()
            ->getResultArray();

        if (!empty($expiredSubs)) {
            foreach ($expiredSubs as $sub) {
                // Update status → expired
                $db->table('subscriptions')
                    ->where('id', $sub['id'])
                    ->update(['status' => 'expired']);

                /**
                 * 2️⃣ LOG THE EXPIRED SUBSCRIPTION
                 */
                $db->table('system_logs')->insert([
                    'log_type'  => 'subscription_expired',
                    'message'   => "Subscription ID {$sub['id']} for client {$sub['client_id']} expired on {$sub['expires_on']}",
                ]);
            }
        }


        /**
         * 3️⃣ UPDATE CLIENT STATUS BASED ON ACTIVE SUBSCRIPTIONS
         */

        // Mark ACTIVE clients
        $db->query("
        UPDATE clients
        SET status = 'active'
        WHERE id IN (
            SELECT DISTINCT client_id FROM subscriptions
            WHERE status = 'active'
        )
    ");

        // Mark INACTIVE clients
        $db->query("
        UPDATE clients
        SET status = 'inactive'
        WHERE id NOT IN (
            SELECT DISTINCT client_id FROM subscriptions
            WHERE status = 'active'
        )
    ");
    }
}
