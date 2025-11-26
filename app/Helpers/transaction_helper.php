<?php

use App\Models\MpesaTransactionModel;

function getTransactions($clientId = null, bool $isAdmin = false)
{
    $model = new MpesaTransactionModel();

    $builder = $model
        ->select('
            mpesa_transactions.*,
            packages.name AS package_name,
            packages.type AS package_type,
            packages.account_type AS package_account_type,
            clients.username AS client_username
        ')
        ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
        ->join('clients', 'clients.id = mpesa_transactions.client_id', 'left')
        ->orderBy('mpesa_transactions.created_at', 'DESC');

    if (!$isAdmin && $clientId) {
        $builder->where('mpesa_transactions.client_id', $clientId);
    }

    return $builder->findAll();
}
