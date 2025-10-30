<?php namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table = 'admins';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'username',
        'email',
        'password',
        'role'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Example validation (optional but best practice)
    protected $validationRules = [
        'username' => 'required|min_length[3]|is_unique[admins.username,id,{id}]',
        'email'    => 'required|valid_email|is_unique[admins.email,id,{id}]',
        'password' => 'permit_empty|min_length[6]',
        'role'     => 'in_list[superadmin,admin]'
    ];

    protected $validationMessages = [
        'email' => [
            'is_unique' => 'That email address is already taken.'
        ],
        'username' => [
            'is_unique' => 'That username is already taken.'
        ]
    ];
}
