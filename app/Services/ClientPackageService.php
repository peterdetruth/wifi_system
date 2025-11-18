<?php

namespace App\Services;

use Config\Database;

class ClientPackageService
{
    protected MpesaLogger $logger;
    protected $db;

    public function __construct(MpesaLogger $logger)
    {
        $this->logger = $logger;
        $this->db = Database::connect();
    }

    public function updateClientPackageStatus(int $clientId, int $packageId, string $status): bool
    {
        $builder = $this->db->table('client_packages')
            ->where('client_id', $clientId)
            ->where('package_id', $packageId)
            ->where('status', 'pending');

        $builder->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $err = $this->db->error();
        if ($err['code'] != 0) {
            $this->logger->error("DB ERROR updating client_packages", $err);
            return false;
        }

        $this->logger->debug("Client package updated", ['client_id' => $clientId, 'package_id' => $packageId, 'status' => $status]);
        return true;
    }
}
