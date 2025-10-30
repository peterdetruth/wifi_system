<?php namespace App\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table = 'vouchers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'code', 'purpose', 'router_id', 'package_id', 'phone',
        'status', 'expires_on', 'used_by_client_id', 'used_at',
        'created_by', 'created_at', 'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'purpose' => 'required|in_list[compensation,promotion,new_customer,loyalty_reward,trial,gift,general]',
        'router_id' => 'required|integer',
        'package_id' => 'required|integer',
        'phone' => 'required|min_length[10]|max_length[15]'
    ];

    // Generate single voucher code
    public function generateCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4))); // 8 chars
        } while ($this->where('code', $code)->countAllResults() > 0);
        return $code;
    }

    public function getAllVouchers()
    {
        return $this->select('vouchers.*, routers.name AS router_name, packages.type AS package_type, packages.price AS package_price')
                    ->join('routers', 'routers.id = vouchers.router_id', 'left')
                    ->join('packages', 'packages.id = vouchers.package_id', 'left')
                    ->orderBy('vouchers.created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Fetch all vouchers with router & package info
     */
    public function getAllWithPackage()
    {
        return $this->select('
                vouchers.*,
                packages.name AS package_name,
                packages.price AS package_price,
                routers.name AS router_name
            ')
            ->join('packages', 'packages.id = vouchers.package_id', 'left')
            ->join('routers', 'routers.id = vouchers.router_id', 'left')
            ->orderBy('vouchers.created_at', 'DESC')
            ->findAll();
    }

    public function isValidVoucher(string $code): ?array
    {
        $voucher = $this->where('code', $code)
                        ->where('status', 'unused')
                        ->first();

        if (!$voucher) {
            return null;
        }

        // Make sure not expired
        if (strtotime($voucher['expires_on']) < time()) {
            return null;
        }

        return $voucher;
    }

    public function markAsUsed(string $code, int $clientId = null): bool
    {
        return $this->where('code', $code)
                    ->set([
                        'status' => 'used',
                        'used_by_client_id' => $clientId,
                        'used_at' => date('Y-m-d H:i:s')
                    ])
                    ->update();
    }
}
