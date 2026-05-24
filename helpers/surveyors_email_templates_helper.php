<?php
defined('BASEPATH') or exit('No direct script access allowed');

// ─── Hook Registrations ────────────────────────────────────────────────────────

hooks()->add_action('after_email_templates', 'surveyors_email_templates_section');

// ─── Email Templates Section ──────────────────────────────────────────────────

function surveyors_email_templates_section()
{
    $CI = &get_instance();

    $module = $CI->app_modules->get(SURVEYORS_MODULE_NAME);
    if (!$module || (int) $module['activated'] !== 1) {
        return;
    }

    $CI->load->model('emails_model');
    $data['surveyor_email_templates'] = $CI->emails_model->get([
        'type'     => 'surveyors',
        'language' => 'english',
    ]);
    $data['hasPermissionEdit'] = staff_can('edit', 'email_templates');
    $CI->load->view('surveyors/admin/emails/surveyor_email_templates', $data);
}
