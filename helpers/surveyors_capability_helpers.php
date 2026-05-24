<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ─── Hook Registrations ───────────────────────────────────────────────────────

hooks()->add_action('admin_init',                    'surveyors_permissions');
hooks()->add_action('admin_init',                    'surveyors_ensure_role_permissions');
hooks()->add_filter('staff_can',                     'surveyors_staff_can_filter', 10, 4);
hooks()->add_filter('staff_permissions',             'surveyors_add_staff_permissions', 10, 2);
hooks()->add_filter('get_contact_permissions',       'surveyors_add_contact_permission');
hooks()->add_filter('role_capabilities_features',    'surveyors_role_capabilities_features', 10);

// ─── Layer 1: Baseline Capabilities ──────────────────────────────────────────

function surveyors_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'                 => _l('permission_view') . ' (' . _l('permission_global') . ')',
        'view_own'             => _l('permission_view_own'),
        'create'               => _l('permission_create'),
        'edit'                 => _l('permission_edit'),
        'edit_own'             => _l('permission_edit_own'),
        'delete'               => _l('permission_delete'),
        'convert_to_quotation' => _l('surveyor_permission_convert_to_quotation'),
        'mark_as'              => _l('permission_mark_as'),
        'sign_report'          => _l('surveyor_permission_sign_report'),
    ];
    register_staff_capabilities('surveyors', $capabilities, _l('surveyors'));
}

// ─── Layer 2: Staff Permissions Form ─────────────────────────────────────────

function surveyors_add_staff_permissions($permissions, $data)
{
    $permissions['surveyors'] = [
        'name'         => _l('surveyors'),
        'capabilities' => [
            'view'                 => _l('permission_view') . ' (' . _l('permission_global') . ')',
            'view_own'             => _l('permission_view_own'),
            'create'               => _l('permission_create'),
            'edit'                 => _l('permission_edit'),
            'edit_own'             => _l('permission_edit') . ' (Own)',
            'mark_as'              => _l('permission_mark_as'),
            'convert_to_quotation' => _l('surveyor_permission_convert_to_quotation'),
        ],
    ];
    return $permissions;
}

// ─── Layer 3: Role Default Seeds ─────────────────────────────────────────────

function surveyors_ensure_role_permissions()
{
    $CI = &get_instance();

    $allowed = [
        'Surveyor'              => ['view_own'],
        'Assessor'              => ['view_own', 'sign_report'],
        'Surveyor Sales'        => ['view_own'],
        'Surveyor Finance'      => ['view_own'],
        'Surveyor Operation'    => ['view_own'],
        'Surveyor Admin'        => ['view', 'edit', 'create'],
        'Surveyor Branch Admin' => ['view_own', 'edit_own'],
        'Customer'              => ['view'],
        'Customer Admin'        => ['view'],
        'Customer Branch Admin' => ['view'],
        'Association'           => ['view'],
        'Association Admin'     => ['view'],
        'Institution'           => ['view'],
        'Institution Admin'     => ['view'],
        'Institution Unit Admin' => ['view'],
        'Finance'               => ['view'],
        'Customer Service'      => ['view'],
        'IT Support'            => ['view'],
    ];

    $denied = [
        'Surveyor'              => ['view', 'edit', 'edit_own'],
        'Assessor'              => ['view', 'edit', 'edit_own', 'create'],
        'Surveyor Sales'        => ['view', 'edit', 'edit_own', 'create'],
        'Surveyor Finance'      => ['view', 'edit', 'edit_own', 'create'],
        'Surveyor Operation'    => ['view', 'edit', 'edit_own', 'create'],
        'Surveyor Admin'        => ['view_own', 'edit_own'],
        'Surveyor Branch Admin' => ['view', 'edit', 'create'],
    ];

    foreach ($allowed as $role_name => $caps) {
        $role = $CI->db->get_where(db_prefix() . 'roles', ['name' => $role_name])->row();
        if (!$role) { continue; }
        $rid = (int) $role->roleid;
        foreach ($caps as $cap) {
            $key = 'surveyor_' . $cap . '_role_' . $rid;
            if (get_option($key) === '') { add_option($key, '1'); }
        }
    }

    foreach ($denied as $role_name => $caps) {
        $role = $CI->db->get_where(db_prefix() . 'roles', ['name' => $role_name])->row();
        if (!$role) { continue; }
        $rid = (int) $role->roleid;
        foreach ($caps as $cap) {
            $key = 'surveyor_' . $cap . '_role_' . $rid;
            if (get_option($key) === '') { add_option($key, '0'); }
        }
    }
}

// ─── Layer 4: Role Capabilities Matrix ───────────────────────────────────────

function surveyors_role_capabilities_features($features)
{
    $features['surveyors'] = [
        'module'        => 'surveyors',
        'label'         => _l('surveyors'),
        'prefix'        => 'surveyor',
        'capabilities'  => ['view', 'view_own', 'create', 'edit', 'edit_own', 'mark_as', 'convert_to_quotation', 'sign_report'],
        'resource_type' => 'surveyor',
    ];

    return $features;
}

// ─── staff_can Hook Filter ────────────────────────────────────────────────────

function surveyors_staff_can_filter($result, $capability, $feature, $staff_id)
{
    if ($feature !== 'surveyors') { return $result; }
    if ($result === true) { return true; }

    $CI   = &get_instance();
    $role = $CI->db->select('role')
        ->get_where(db_prefix() . 'staff', ['staffid' => $staff_id])
        ->row();

    if (!$role || empty($role->role)) { return $result; }

    $opt = get_option('surveyor_' . $capability . '_role_' . (int) $role->role);
    if ($opt !== '') { return $opt == '1'; }

    return $result;
}

// ─── Contact Permissions ──────────────────────────────────────────────────────

function surveyors_add_contact_permission($permissions)
{
    $permissions[] = [
        'id'         => 7,
        'name'       => _l('surveyor_permission_surveyor'),
        'short_name' => 'surveyors',
    ];
    return $permissions;
}
