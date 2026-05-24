<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ─── Hook Registrations ────────────────────────────────────────────────────────

hooks()->add_action('admin_init', 'surveyors_init_menu_items');

// ─── Menu Item Registration ───────────────────────────────────────────────────

function surveyors_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
        'name'       => _l('surveyors'),
        'url'        => 'surveyors',
        'permission' => 'surveyors',
        'icon'       => 'fa-solid fa-file-invoice',
        'position'   => 11,
    ]);

    if (staff_can('view', 'surveyors') || staff_can('view_own', 'surveyors')) {
        $CI->app_menu->add_sidebar_children_item('wasnaker-member', [
            'slug'     => 'surveyors-tracking',
            'name'     => _l('surveyors'),
            'href'     => admin_url('surveyors'),
            'position' => 5,
        ]);
    }

    if (has_permission('surveyors', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('reports', [
            'slug'     => 'surveyors-report',
            'name'     => _l('surveyors_report'),
            'href'     => admin_url('surveyors/surveyors_report'),
            'position' => 35,
        ]);
    }

    if (is_admin() || is_staff_member()) {
        $pending_count = $CI->db
            ->where('client_type', 'surveyor')
            ->where_in('registration_status', ['pending', 'user_activated'])
            ->count_all_results(db_prefix() . 'staff');

        $CI->app_menu->add_sidebar_children_item('wasnaker-registration', [
            'slug'     => 'surveyors-pending-approvals',
            'name'     => _l('surveyors'),
            'href'     => admin_url('surveyors/pending_approvals'),
            'position' => 1,
            'badge'    => $pending_count > 0 ? ['count' => $pending_count, 'bg' => 'danger'] : [],
        ]);
    }
}
