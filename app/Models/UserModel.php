<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'email', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    /**
     * Optional: fetch a user by email
     */
    public function getByEmail(string $email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Optional: search users by name
     */
    public function searchByName(string $name)
    {
        return $this->like('name', $name)->findAll();
    }
}
