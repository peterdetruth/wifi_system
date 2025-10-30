<?php namespace App\Models;

use CodeIgniter\Model;

class RouterModel extends Model
{
    protected $table = 'routers';
    protected $primaryKey = 'id';

    protected $allowedFields = ['name', 'ip_address', 'status', 'last_seen'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name'       => 'required|min_length[3]|is_unique[routers.name,id,{id}]',
        'ip_address' => 'required|valid_ip',
        'status'     => 'in_list[active,inactive]'
    ];

    protected $validationMessages = [
        'name'       => ['is_unique' => 'That router name already exists.'],
        'ip_address' => ['valid_ip' => 'Please enter a valid IP address.']
    ];
}
