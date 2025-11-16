<?php

namespace App\Models;

use CodeIgniter\Model;

class FeatureRequestModel extends Model
{
    protected $table      = 'feature_requests';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'name', 
        'description', 
        'status'
    ];

    protected $useTimestamps = true;
}
