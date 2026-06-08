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
