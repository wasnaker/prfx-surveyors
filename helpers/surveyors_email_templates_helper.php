<?php
defined('BASEPATH') or exit('No direct script access allowed');

// ─── Hook Registrations ────────────────────────────────────────────────────────

hooks()->add_action('after_email_templates', 'surveyors_email_templates_section');

// ─── Register Email Templates on Install ──────────────────────────────────────

function surveyors_register_email_templates()
{
    $CI = &get_instance();
    $CI->load->model('emails_model');

    $templates = [
        [
            'slug'     => 'surveyor-registered',
            'name'     => 'Surveyor Registered',
            'subject'  => 'New Surveyor Registration',
            'message'  => '<p>Hi {staff_firstname},</p><p>A new surveyor has registered.</p><p>Name: {surveyor_name}</p>',
            'type'     => 'surveyors',
            'language' => 'english',
        ],
    ];

    foreach ($templates as $template) {
        $exists = $CI->db->where('slug', $template['slug'])
                         ->where('language', $template['language'])
                         ->get(db_prefix() . 'emailtemplates')
                         ->num_rows();
        if ($exists === 0) {
            $CI->db->insert(db_prefix() . 'emailtemplates', $template);
        }
    }
}

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
