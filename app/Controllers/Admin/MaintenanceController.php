<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class MaintenanceController extends BaseController
{
    private array $expectedTables = [
        'client_packages' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'client_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'package_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'amount','type'=>'decimal','nullable'=>'YES'],
            ['name'=>'start_date','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'end_date','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'status','type'=>'enum','nullable'=>'NO'],
            ['name'=>'created_at','type'=>'timestamp','nullable'=>'NO'],
            ['name'=>'updated_at','type'=>'timestamp','nullable'=>'YES']
        ],
        'subscriptions' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'payment_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'client_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'package_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'router_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'start_date','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'expires_on','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'status','type'=>'enum','nullable'=>'YES'],
            ['name'=>'created_at','type'=>'datetime','nullable'=>'YES'],
            ['name'=>'updated_at','type'=>'datetime','nullable'=>'YES']
        ],
        'mpesa_transactions' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'client_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'package_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'transaction_id','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'merchant_request_id','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'checkout_request_id','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'amount','type'=>'decimal','nullable'=>'YES'],
            ['name'=>'phone_number','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'mpesa_receipt_number','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'transaction_date','type'=>'datetime','nullable'=>'YES'],
            ['name'=>'status','type'=>'enum','nullable'=>'NO'],
            ['name'=>'created_at','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'updated_at','type'=>'datetime','nullable'=>'YES']
        ],
        'payments' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'mpesa_transaction_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'client_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'package_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'amount','type'=>'decimal','nullable'=>'NO'],
            ['name'=>'payment_method','type'=>'enum','nullable'=>'NO'],
            ['name'=>'status','type'=>'enum','nullable'=>'NO'],
            ['name'=>'mpesa_receipt_number','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'phone','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'transaction_date','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'created_at','type'=>'datetime','nullable'=>'NO'],
            ['name'=>'merchant_request_id','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'checkout_request_id','type'=>'varchar','nullable'=>'YES']
        ],
        'packages' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'name','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'type','type'=>'enum','nullable'=>'NO'],
            ['name'=>'duration_length','type'=>'int','nullable'=>'YES'],
            ['name'=>'duration_unit','type'=>'enum','nullable'=>'YES'],
            ['name'=>'price','type'=>'decimal','nullable'=>'NO'],
            ['name'=>'router_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'duration','type'=>'enum','nullable'=>'NO'],
            ['name'=>'updated_at','type'=>'timestamp','nullable'=>'NO'],
            ['name'=>'created_at','type'=>'timestamp','nullable'=>'NO']
        ],
        'clients' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'full_name','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'username','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'account_type','type'=>'enum','nullable'=>'YES'],
            ['name'=>'email','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'password','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'phone','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'default_package_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'status','type'=>'enum','nullable'=>'YES'],
            ['name'=>'created_at','type'=>'timestamp','nullable'=>'NO'],
            ['name'=>'updated_at','type'=>'timestamp','nullable'=>'NO']
        ],
        'vouchers' => [
            ['name'=>'id','type'=>'int','nullable'=>'NO'],
            ['name'=>'code','type'=>'varchar','nullable'=>'NO'],
            ['name'=>'purpose','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'router_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'package_id','type'=>'int','nullable'=>'NO'],
            ['name'=>'phone','type'=>'varchar','nullable'=>'YES'],
            ['name'=>'status','type'=>'enum','nullable'=>'YES'],
            ['name'=>'expires_on','type'=>'datetime','nullable'=>'YES'],
            ['name'=>'used_by_client_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'used_at','type'=>'datetime','nullable'=>'YES'],
            ['name'=>'created_by','type'=>'int','nullable'=>'YES'],
            ['name'=>'client_id','type'=>'int','nullable'=>'YES'],
            ['name'=>'created_at','type'=>'timestamp','nullable'=>'YES'],
            ['name'=>'updated_at','type'=>'timestamp','nullable'=>'YES']
        ]
    ];

    public function checkSchema()
    {
        $db = \Config\Database::connect();
        $dbName = $db->getDatabase();

        $results = [];

        foreach ($this->expectedTables as $table => $columns) {
            $existingColumns = $db->query("
                SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                FROM information_schema.columns 
                WHERE table_schema = '{$dbName}' 
                AND table_name = '{$table}'
            ")->getResultArray();

            $existingColumnsMap = [];
            foreach ($existingColumns as $col) {
                $existingColumnsMap[$col['COLUMN_NAME']] = $col;
            }

            $tableResult = [];
            foreach ($columns as $col) {
                $status = 'missing';
                $details = [
                    'exists' => false,
                    'type' => $col['type'],
                    'nullable' => $col['nullable'],
                    'matches_type' => false,
                    'matches_nullable' => false,
                    'current_type' => null,
                    'current_nullable' => null,
                    'current_default' => null
                ];

                if (isset($existingColumnsMap[$col['name']])) {
                    $details['exists'] = true;
                    $details['matches_type'] = stripos($existingColumnsMap[$col['name']]['COLUMN_TYPE'], $col['type']) !== false;
                    $details['matches_nullable'] = strtoupper($existingColumnsMap[$col['name']]['IS_NULLABLE']) === strtoupper($col['nullable']);
                    $details['current_type'] = $existingColumnsMap[$col['name']]['COLUMN_TYPE'];
                    $details['current_nullable'] = $existingColumnsMap[$col['name']]['IS_NULLABLE'];
                    $details['current_default'] = $existingColumnsMap[$col['name']]['COLUMN_DEFAULT'];
                    $status = ($details['matches_type'] && $details['matches_nullable']) ? 'ok' : 'mismatch';
                }

                $tableResult[$col['name']] = $details;
            }

            $results[$table] = $tableResult;
        }

        return view('maintenance/check_schema_detailed', ['results' => $results]);
    }
}
