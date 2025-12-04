<?php namespace App\Models;

use CodeIgniter\Model;

class ClientModel extends Model
{
    protected $table = 'clients';
    protected $primaryKey = 'id';

    // ✅ Include all fields that can be inserted/updated
    protected $allowedFields = [
        'full_name',
        'username',
        'phone',
        'email',
        'password',
        'status',
        'account_type',
        'default_package_id'
    ];

    // ✅ Use timestamps for created_at & updated_at
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // ✅ Validation rules
    protected $validationRules = [
        'username'   => 'required|alpha_numeric|min_length[3]|is_unique[clients.username,id,{id}]',
        'full_name'  => 'required|min_length[3]',
        'email'      => 'permit_empty|valid_email|is_unique[clients.email,id,{id}]',
        'password'   => 'required|min_length[6]',
        'status'     => 'in_list[active,inactive]',
        'account_type' => 'in_list[personal,business]'
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'Username is required',
            'is_unique'=> 'Username already exists',
        ],
        'email' => [
            'valid_email' => 'Please enter a valid email address',
            'is_unique' => 'Email already exists',
        ],
        'password' => [
            'required' => 'Password is required',
            'min_length' => 'Password must be at least 6 characters',
        ]
    ];

    // ✅ Hash password automatically before insert/update
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        }
        return $data;
    }
}
