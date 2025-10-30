<?php

namespace App\Models;

use CodeIgniter\Model;

class PackageModel extends Model
{
    protected $table = 'packages';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name', 'type', 'account_type', 'price',
        'duration_length', 'duration_unit', 'router_id',
        'bandwidth_value', 'bandwidth_unit',
        'burst_enabled',
        'upload_burst_rate_value', 'upload_burst_rate_unit',
        'download_burst_rate_value', 'download_burst_rate_unit',
        'upload_burst_threshold_value', 'upload_burst_threshold_unit',
        'download_burst_threshold_value', 'download_burst_threshold_unit',
        'upload_burst_time', 'download_burst_time',
        'hotspot_plan_type', 'hotspot_device_limit', 'hotspot_devices'
    ];

    protected $useTimestamps = true;
    protected $validationRules = [
        'name'                        => 'required|min_length[3]',
        'type'                        => 'required|in_list[hotspot,pppoe]',
        'account_type'                => 'required|in_list[personal,business]',
        'price'                       => 'required|decimal',
        'duration_length'             => 'required|integer',
        'duration_unit'               => 'required|in_list[minutes,hours,days,months]',
        'router_id'                   => 'required|integer',

        // Bandwidth
        'bandwidth_value'             => 'permit_empty|decimal',
        'bandwidth_unit'              => 'permit_empty|in_list[Kbps,Mbps]',

        // Burst settings
        'burst_enabled'               => 'permit_empty|in_list[0,1]',
        'upload_burst_rate_value'     => 'permit_empty|decimal',
        'upload_burst_rate_unit'      => 'permit_empty|in_list[Kbps,Mbps]',
        'download_burst_rate_value'   => 'permit_empty|decimal',
        'download_burst_rate_unit'    => 'permit_empty|in_list[Kbps,Mbps]',
        'upload_burst_threshold_value'=> 'permit_empty|decimal',
        'upload_burst_threshold_unit' => 'permit_empty|in_list[Kbps,Mbps]',
        'download_burst_threshold_value'=> 'permit_empty|decimal',
        'download_burst_threshold_unit' => 'permit_empty|in_list[Kbps,Mbps]',
        'upload_burst_time'           => 'permit_empty|decimal',
        'download_burst_time'         => 'permit_empty|decimal',

        // Hotspot settings
        'hotspot_plan_type'           => 'permit_empty|in_list[Unlimited,Data Plans]',
        'hotspot_devices'        => 'permit_empty|integer',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Package name is required.'
        ],
        'router_id' => [
            'required' => 'Please select a router.'
        ]
    ];
}
