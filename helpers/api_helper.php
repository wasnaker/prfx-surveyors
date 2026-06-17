<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Register Surveyors API permissions
 * Agar muncul di dropdown permission saat membuat/mengedit API user
 */
hooks()->add_filter('api_permissions', function ($permissions) {
    $permissions['surveyors'] = [
        'name'         => _l('surveyors'),
        'capabilities' => [
            'get'        => _l('permission_get'),
            'search_get' => _l('permission_search'),
            'post'       => _l('permission_create'),
            'put'        => _l('permission_update'),
            'delete'     => _l('permission_delete'),
        ],
    ];

    $permissions['surveyor_reports'] = [
        'name'         => 'Surveyor Reports',
        'capabilities' => [
            'get'  => _l('permission_get'),
        ],
    ];

    return $permissions;
});
