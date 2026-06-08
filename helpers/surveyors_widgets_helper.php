<?php

defined('BASEPATH') or exit('No direct script access allowed');

function surveyors_register_dashboard_widgets($widgets)
{
    $widgets[] = [
        'path'      => 'surveyors/admin/dashboard/widgets/total_surveyors',
        'container' => 'mini-1',
        'id'        => 'total-surveyors',
        'name'      => 'Total Surveyors',
    ];

    return $widgets;
}

function get_surveyors_widget_data()
{
    if (function_exists('get_dashboard_widget_data')) {
        $data = get_dashboard_widget_data();
    } else {
        $CI              = &get_instance();
        $data            = new stdClass();
        $data->CI        = $CI;
        $data->theme     = get_option('active_admin_theme');
        $data->widget_id = create_widget_id();
    }

    $data->total = $data->CI->db
        ->where('client_type', 'surveyor')
        ->count_all_results('tblclients');

    return $data;
}
