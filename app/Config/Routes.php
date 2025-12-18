<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// ==============================
// 🔐 AUTH (Admin)
// ==============================
$routes->get('/login', 'AuthController::login');
$routes->post('/login', 'AuthController::processLogin');
$routes->get('/logout', 'AuthController::logout');

// ==============================
// 🧭 ADMIN ROUTES (Require auth)
// ==============================
$routes->group('admin', ['filter' => 'auth', 'namespace' => 'App\Controllers'], static function ($routes) {

    // Dashboard
    $routes->get('/', 'Dashboard::index');
    $routes->get('dashboard', 'Dashboard::index');

    // 🧩 Admins
    $routes->group('admins', static function ($routes) {
        $routes->get('/', 'Admins::index');
        $routes->get('create', 'Admins::create');
        $routes->post('store', 'Admins::store');
        $routes->get('edit/(:num)', 'Admins::edit/$1');
        $routes->post('update/(:num)', 'Admins::update/$1');
        $routes->get('delete/(:num)', 'Admins::delete/$1');
    });

    // 💼 Packages
    $routes->group('packages', static function ($routes) {
        $routes->get('/', 'Packages::index');
        $routes->get('create', 'Packages::create');
        $routes->post('store', 'Packages::store');
        $routes->get('edit/(:num)', 'Packages::edit/$1');
        $routes->post('update/(:num)', 'Packages::update/$1');
        $routes->get('delete/(:num)', 'Packages::delete/$1');
    });

    // 👥 Clients
    $routes->group('clients', static function ($routes) {
        $routes->get('/', 'Clients::index');
        $routes->get('create', 'Clients::create');
        $routes->post('store', 'Clients::store');
        $routes->get('view/(:num)', 'Clients::view/$1');
        $routes->get('edit/(:num)', 'Clients::edit/$1');
        $routes->post('update/(:num)', 'Clients::update/$1');
        $routes->get('delete/(:num)', 'Clients::delete/$1');
        // ✅ New route for account recharge (admin only)
        $routes->post('recharge/(:num)', 'Clients::recharge/$1');
    });

    // 🌐 Routers
    $routes->group('routers', static function ($routes) {
        $routes->get('/', 'Routers::index');
        $routes->get('create', 'Routers::create');
        $routes->post('store', 'Routers::store');
        $routes->get('view/(:num)', 'Routers::view/$1');
        $routes->get('edit/(:num)', 'Routers::edit/$1');
        $routes->post('update/(:num)', 'Routers::update/$1');
        $routes->post('delete/(:num)', 'Routers::delete/$1');
    });

    // 🎟️ Vouchers
    $routes->group('vouchers', static function ($routes) {
        $routes->get('/', 'Vouchers::index');
        $routes->get('create', 'Vouchers::create');
        $routes->post('store', 'Vouchers::store');
        $routes->get('edit/(:num)', 'Vouchers::edit/$1');
        $routes->post('update/(:num)', 'Vouchers::update/$1');
        $routes->post('delete/(:num)', 'Vouchers::delete/$1');
    });

    // 💳 Transactions
    $routes->group('transactions', static function ($routes) {
        $routes->get('/', 'Transactions::index');
    });

    // 📱 M-PESA Logs (NEW)
    $routes->get('mpesa-logs', '\App\Controllers\MpesaLogs::index');

    // 📞 M-PESA Logs (legacy view, optional)
    $routes->group('mpesa', static function ($routes) {
        $routes->get('/', 'Mpesa::index');
    });

    // 👥 Subscribers
    $routes->group('subscribers', static function ($routes) {
        $routes->get('active', 'Subscribers::active');
        $routes->get('expired', 'Subscribers::expired');
    });

    // 💰 Payments (Admin)
    $routes->group('payments', static function ($routes) {
        $routes->get('/', 'Payments::index');
    });
});

$routes->group('admin/features', ['namespace' => 'App\Controllers\Admin'], function ($routes) {
    $routes->get('/', 'Features::index');
    $routes->get('create', 'Features::create');
    $routes->post('store', 'Features::store');
    $routes->get('complete/(:num)', 'Features::markComplete/$1');
    $routes->get('delete/(:num)', 'Features::delete/$1');
});

$routes->get('admin/maintenance/schema', 'Admin\MaintenanceController::checkSchema');
$routes->get('admin/maintenance/schema-detailed', 'Admin\MaintenanceController::checkSchema');
$routes->get('admin/logs', 'Admin\LogController::index');

// ==============================
// 💸 M-PESA CALLBACK
// ==============================
$routes->post('/mpesa/callback', 'Mpesa::callback');

// ==============================
// 👤 CLIENT ROUTES
// ==============================
$routes->group('client', ['namespace' => 'App\Controllers\Client'], static function ($routes) {

    // ------------------------------
    // Public Auth routes
    // ------------------------------
    $routes->get('register', 'Auth::register');
    $routes->post('register-post', 'Auth::registerPost');
    $routes->get('login', 'Auth::login');
    $routes->post('login-post', 'Auth::loginPost');
    $routes->get('logout', 'Auth::logout');

    // ------------------------------
    // Protected routes (require clientAuth)
    // ------------------------------
    $routes->group('', ['filter' => 'clientAuth'], static function ($routes) {

        // Dashboard
        $routes->get('dashboard', 'Dashboard::index');

        // Packages
        $routes->get('packages', 'Packages::index');
        $routes->get('packages/view/(:num)', 'Packages::view/$1');
        $routes->get('packages/subscribe/(:num)', 'Packages::subscribe/$1');

        // Subscriptions
        $routes->get('subscriptions', 'Subscriptions::index');
        $routes->get('subscriptions/view/(:num)', 'Subscriptions::view/$1');
        $routes->get('subscriptions/cancel/(:num)', 'Subscriptions::cancel/$1');
        $routes->get('subscriptions/reconnect/(:num)', 'Subscriptions::reconnect/$1');

        // Vouchers
        $routes->get('vouchers/redeem', 'Vouchers::redeem');
        $routes->post('vouchers/redeem-post', 'Vouchers::redeemPost');

        // Transactions
        $routes->get('transactions', 'Transactions::index');
        $routes->get('transactions/view/(:num)', 'Transactions::view/$1');

        // Profile
        $routes->get('profile', 'Profile::index');
        $routes->post('profile/update', 'Profile::update');

        // Payments
        $routes->get('payments', 'Payments::index');
        $routes->get('payments/checkout/(:num)', 'Payments::checkout/$1');
        $routes->get('payments/buy/(:num)', 'Payments::buy/$1');
        $routes->get('payments/success/(:any)', 'Payments::success/$1');
        $routes->get('payments/waiting/(:any)', 'Payments::waiting/$1');
        $routes->get('payments/pending', 'Payments::pending');
        $routes->get('payments/initiate/(:num)', 'Payments::initiatePayment/$1');
        $routes->post('payments/process', 'Payments::process');
        $routes->get('payments/status/(:segment)', 'Payments::status/$1');
        $routes->get('payments/pending/(:segment)', 'Payments::pending/$1');
        $routes->get('payments/checkStatus', 'Payments::checkStatus');
    });
});

$routes->post('reconnect-mpesa', 'Home::reconnectMpesa');

// ==============================
// TEST ROUTES
// ==============================
$routes->get('test/envcheck', 'Test::envcheck');
$routes->get('test-sms', 'Mpesa::testSms');
