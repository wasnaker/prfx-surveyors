<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Surveyors
Description: Full-featured Surveyors module for Perfex CRM — independent of core Estimates
Version: 1.0.0
Requires at least: 2.3.*
*/

define('SURVEYORS_MODULE_NAME', 'surveyors');
define('SURVEYORS_ATTACHMENTS_FOLDER', FCPATH . 'uploads/surveyors/');

// Pre-load surveyor mail template classes from module path.
// App_mail_template::createReflectionMailClass() resolves paths without a module hint,
// falling back to APPPATH/libraries/mails/ and emitting a warning for missing files.
// Pre-loading ensures the class is already defined before that include_once runs,
// so ReflectionClass succeeds and no exception is thrown.
// The spl_autoload_register is a secondary safety net for any late-bound calls.
$_surveyor_mail_classes = [
    'Surveyor_send_to_surveyor',
    'Surveyor_send_to_surveyor_already_sent',
    'Surveyor_accepted_to_surveyor',
    'Surveyor_accepted_to_staff',
    'Surveyor_declined_to_staff',
    'Surveyor_expiration_reminder',
];
foreach ($_surveyor_mail_classes as $_qmc) {
    $_qmc_path = FCPATH . 'modules/' . SURVEYORS_MODULE_NAME . '/libraries/mails/' . $_qmc . '.php';
    if (file_exists($_qmc_path) && !class_exists(strtolower($_qmc), false)) {
        include_once($_qmc_path);
    }
}
unset($_surveyor_mail_classes, $_qmc, $_qmc_path);

spl_autoload_register(function ($class) {
    $mail_path = FCPATH . 'modules/' . SURVEYORS_MODULE_NAME . '/libraries/mails/' . ucfirst($class) . '.php';
    if (file_exists($mail_path)) {
        include_once($mail_path);
    }
});

// SMS trigger constants
define('SMS_TRIGGER_SURVEYOR_EXP_REMINDER', 'surveyor_expiration_reminder');

// Status constants
define('SURVEYOR_STATUS_DRAFT',    1);
define('SURVEYOR_STATUS_SENT',     2);
define('SURVEYOR_STATUS_DECLINED', 3);
define('SURVEYOR_STATUS_ACCEPTED', 4);
define('SURVEYOR_STATUS_EXPIRED',  5);

// ─── Hooks ───────────────────────────────────────────────────────────────────

hooks()->add_action('after_email_templates',         'surveyors_email_templates_section');
hooks()->add_action('admin_init',                    'surveyors_settings_tab');
hooks()->add_action('admin_init',                    'surveyors_register_app_table');
hooks()->add_action('after_cron_run',                'surveyors_notification');
hooks()->add_action('staff_member_deleted',          'surveyors_staff_member_deleted');

hooks()->add_filter('migration_tables_to_replace_old_links',    'surveyors_migration_tables_to_replace_old_links');
hooks()->add_filter('other_merge_fields_available_for',          'surveyors_other_merge_fields_available_for');
hooks()->add_filter('global_search_result_query',  'surveyors_global_search_result_query', 10, 3);
hooks()->add_filter('global_search_result_output', 'surveyors_global_search_result_output', 10, 2);
hooks()->add_filter('get_dashboard_widgets',        'surveyors_add_dashboard_widget');
hooks()->add_filter('module_surveyors_action_links', 'module_surveyors_action_links');
hooks()->add_action('app_admin_footer', 'surveyors_inactive_company_modal');

// ─── Activation / Deactivation ───────────────────────────────────────────────

register_activation_hook(SURVEYORS_MODULE_NAME, 'surveyors_module_activation_hook');

function surveyors_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
    // Create uploads directory
    if (!file_exists(SURVEYORS_ATTACHMENTS_FOLDER)) {
        mkdir(SURVEYORS_ATTACHMENTS_FOLDER, 0755, true);
    }
    log_activity('Surveyors module activated');
}

register_deactivation_hook(SURVEYORS_MODULE_NAME, 'surveyors_module_deactivation_hook');

function surveyors_module_deactivation_hook()
{
    $CI = &get_instance();
    $CI->db->query('DROP TABLE IF EXISTS ' . db_prefix() . 'surveyor_activity');
    $CI->db->query('DROP TABLE IF EXISTS ' . db_prefix() . 'surveyor_items');
    $CI->db->query('DROP TABLE IF EXISTS ' . db_prefix() . 'surveyor_doc_equipment');
    $CI->db->query('DROP TABLE IF EXISTS ' . db_prefix() . 'surveyor_permits');
    $CI->db->query('DROP TABLE IF EXISTS ' . db_prefix() . 'surveyor_equipment');
    $CI->db->query('DROP TABLE IF EXISTS ' . db_prefix() . 'surveyors');

    // Remove options
    $CI->db->where('name LIKE', 'surveyor%')->delete(db_prefix() . 'options');
    $CI->db->where('type', 'surveyors')->delete(db_prefix() . 'emailtemplates');

    log_activity('Surveyors module deactivated - tables dropped');
}

// ─── Language ─────────────────────────────────────────────────────────────────

register_language_files(SURVEYORS_MODULE_NAME, [SURVEYORS_MODULE_NAME]);

// ─── Relation Helpers ─────────────────────────────────────────────────────────

require_once(__DIR__ . '/helpers/surveyor_relation_helpers.php');

// ─── Capability Helpers ───────────────────────────────────────────────────────

require_once(__DIR__ . '/helpers/surveyors_capability_helpers.php');

// ─── DataTable Helpers ─────────────────────────────────────────────────────────

require_once(__DIR__ . '/helpers/surveyors_datatables_helper.php');

hooks()->add_action('admin_init',                    'surveyors_permissions');
hooks()->add_action('admin_init',                    'surveyors_ensure_role_permissions');
hooks()->add_filter('staff_can',                     'surveyors_staff_can_filter', 10, 4);
hooks()->add_filter('staff_permissions',             'surveyors_add_staff_permissions', 10, 2);
hooks()->add_filter('get_contact_permissions',       'surveyors_add_contact_permission');

// ─── Menu Helpers ──────────────────────────────────────────────────────────────

require_once(__DIR__ . '/helpers/surveyors_menu_helper.php');

// ─── Permissions ──────────────────────────────────────────────────────────────

// ─── Settings Tab ─────────────────────────────────────────────────────────────

function surveyors_settings_tab()
{
    $CI = &get_instance();
    $CI->app->add_settings_section_child('finance', 'surveyors', [
        'name'     => _l('surveyors'),
        'view'     => 'surveyors/admin/settings/includes/surveyors',
        'position' => 52,
        'icon'     => 'fa-solid fa-file-invoice',
    ]);
}

// ─── App_table Registration ───────────────────────────────────────────────────

function surveyors_register_app_table()
{
    $tablePath = FCPATH . 'modules/' . SURVEYORS_MODULE_NAME . '/views/admin/tables/surveyors';

    $surveyorsTable = App_table::new('surveyors', $tablePath)->customfieldable('surveyor');
    App_table::register($surveyorsTable);

    App_table::register(
        App_table::new('project_surveyors', $tablePath)
            ->relatedTo($surveyorsTable->id())
            ->setRules($surveyorsTable->rules())
    );
}

// ─── Dashboard Widget ─────────────────────────────────────────────────────────

function surveyors_add_dashboard_widget($widgets)
{
    return $widgets;
}

// ─── Staff Member Deleted ─────────────────────────────────────────────────────

function surveyors_staff_member_deleted($data)
{
    // sale_agent not applicable — surveyors are entities in tblclients
}

// ─── Global Search ────────────────────────────────────────────────────────────

function surveyors_global_search_result_output($output, $data)
{
    if ($data['type'] == 'surveyors') {
        $output = '<a href="' . admin_url('surveyors/list_surveyors/' . $data['result']['id']) . '">'
            . format_surveyor_number($data['result']['id']) . '</a>';
    }
    return $output;
}

function surveyors_global_search_result_query($result, $q, $limit)
{
    $CI = &get_instance();
    if (has_permission('surveyors', '', 'view')) {
        $CI->db->select('userid as id, company, formatted_number')
            ->from(db_prefix() . 'clients')
            ->where('client_type', 'surveyor')
            ->where('company_id IS NULL', null, false)
            ->like('company', $q)
            ->limit($limit);

        $result[] = [
            'result'         => $CI->db->get()->result_array(),
            'type'           => 'surveyors',
            'search_heading' => _l('surveyors'),
        ];
    }
    return $result;
}

// ─── Migration ────────────────────────────────────────────────────────────────

function surveyors_migration_tables_to_replace_old_links($tables)
{
    return $tables;
}

// ─── Action Links ─────────────────────────────────────────────────────────────

function module_surveyors_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('settings?group=surveyors') . '">' . _l('settings') . '</a>';
    return $actions;
}

// ─── Merge Fields ─────────────────────────────────────────────────────────────

function surveyors_other_merge_fields_available_for($available_for)
{
    $available_for[] = 'surveyors';
    return $available_for;
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

// ─── Cron Notification ────────────────────────────────────────────────────────

function surveyors_notification()
{
    $CI = &get_instance();
    $CI->load->model('surveyors/Surveyors_model', 'surveyors_model');

    // Send expiry reminders
    if (method_exists($CI->surveyors_model, 'send_expiry_reminder')) {
        $CI->surveyors_model->send_expiry_reminder();
    }

    // Auto-expire not applicable — surveyors are entities in tblclients
}

// ─── Load Helper & Assets ─────────────────────────────────────────────────────

$CI = &get_instance();
$CI->load->helper(SURVEYORS_MODULE_NAME . '/surveyors');

register_merge_fields(['surveyors/merge_fields/surveyor_merge_fields']);

$current_url = $CI->uri->segment(1) . '/' . $CI->uri->segment(2);
if (
    ($CI->uri->segment(1) == 'admin' && $CI->uri->segment(2) == 'surveyors') ||
    $CI->uri->segment(1) == 'surveyors'
) {
    $CI->app_css->add(
        SURVEYORS_MODULE_NAME . '-css',
        base_url('modules/' . SURVEYORS_MODULE_NAME . '/assets/css/surveyors.css')
    );
    $CI->app_scripts->add(
        SURVEYORS_MODULE_NAME . '-js',
        base_url('modules/' . SURVEYORS_MODULE_NAME . '/assets/js/surveyors.js') . '?v=' . filemtime(FCPATH . 'modules/' . SURVEYORS_MODULE_NAME . '/assets/js/surveyors.js')
    );
    $CI->app_scripts->add(
        SURVEYORS_MODULE_NAME . '-branch-js',
        base_url('modules/' . SURVEYORS_MODULE_NAME . '/assets/js/surveyor_branch.js') . '?v=' . filemtime(FCPATH . 'modules/' . SURVEYORS_MODULE_NAME . '/assets/js/surveyor_branch.js')
    );
}

function surveyors_inactive_company_modal()
{
    $CI  = &get_instance();
    $me  = get_staff(get_staff_user_id());

    // Only for surveyor entity staff
    if (!$me || $me->client_type !== 'surveyor' || !$me->client_id) { return; }

    // Load company record
    $company = $CI->db->get_where(db_prefix() . 'clients', [
        'userid'      => (int) $me->client_id,
        'client_type' => 'surveyor',
    ])->row();

    if (!$company || $company->active == 1) { return; }

    // Build completeness checks
    $checks = [
        ['label' => _l('surveyor_vat'),        'ok' => !empty($company->vat)],
        ['label' => _l('client_phonenumber'),  'ok' => !empty($company->phonenumber)],
        ['label' => _l('client_address'),      'ok' => !empty($company->address)],
        ['label' => _l('client_state'),        'ok' => !empty($company->state)],
        ['label' => _l('client_city'),         'ok' => !empty($company->city)],
        ['label' => _l('billing_address'),     'ok' => !empty($company->billing_street) && !empty($company->billing_city) && !empty($company->billing_state)],
        ['label' => _l('surveyor_logo_light'), 'ok' => !empty($company->logo_light) || !empty($company->logo_dark)],
        ['label' => _l('client_vat_number'),   'ok' => !empty($company->vat)],
    ];

    $total   = count($checks);
    $filled  = count(array_filter(array_column($checks, 'ok')));
    $percent = (int) round(($filled / $total) * 100);

    $restricted = ['rfqs', 'quotations', 'orders', 'programs', 'jobs',
                   'surveyors/equipment', 'schedules', 'billings'];

    $edit_url  = admin_url('surveyors/surveyor/' . (int) $me->client_id);
    $back_url  = admin_url('surveyors');
    $comp_name = e($company->company);

    $checks_js = json_encode(array_map(fn($c) => [
        'label' => $c['label'],
        'ok'    => (bool) $c['ok'],
    ], $checks));

    $restricted_js = json_encode($restricted);
    ?>
<!-- Inactive Company Modal -->
<div class="modal fade" id="inactive-company-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">

            <!-- Header gradient -->
            <div style="background:linear-gradient(135deg,#f59e0b 0%,#ef4444 100%);padding:28px 28px 20px;">
                <div class="tw-flex tw-items-center tw-gap-3">
                    <div style="background:rgba(255,255,255,0.2);border-radius:50%;width:48px;height:48px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa fa-building" style="font-size:22px;color:#fff;"></i>
                    </div>
                    <div>
                        <h4 style="color:#fff;margin:0;font-weight:700;font-size:18px;">
                            <?= _l('inactive_company_modal_title'); ?>
                        </h4>
                        <p style="color:rgba(255,255,255,0.85);margin:0;font-size:13px;">
                            <?= e($comp_name); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="modal-body" style="padding:24px 28px;">

                <!-- Progress bar -->
                <div class="tw-mb-4">
                    <div class="tw-flex tw-justify-between tw-mb-2">
                        <span class="tw-text-sm tw-font-medium tw-text-neutral-600"><?= _l('profile_completeness'); ?></span>
                        <span class="tw-text-sm tw-font-bold" id="icm-percent-label"
                            style="color:<?= $percent === 100 ? '#16a34a' : ($percent >= 60 ? '#d97706' : '#dc2626'); ?>">
                            <?= $filled; ?>/<?= $total; ?> &mdash; <?= $percent; ?>%
                        </span>
                    </div>
                    <div style="background:#f1f5f9;border-radius:999px;height:10px;overflow:hidden;">
                        <div style="height:100%;border-radius:999px;width:<?= $percent; ?>%;
                            background:<?= $percent === 100 ? '#16a34a' : ($percent >= 60 ? '#f59e0b' : '#ef4444'); ?>;
                            transition:width .4s ease;"></div>
                    </div>
                </div>

                <!-- Missing fields only -->
                <?php $missing = array_filter($checks, fn($c) => !$c['ok']); ?>
                <?php if (!empty($missing)) { ?>
                <div class="tw-mb-4">
                    <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-neutral-400 tw-mb-2">
                        <?= _l('profile_missing'); ?>
                    </p>
                    <?php foreach ($missing as $check) { ?>
                    <div class="tw-flex tw-items-center tw-gap-2 tw-py-1.5 tw-border-b tw-border-neutral-100">
                        <i class="fa fa-times-circle" style="color:#ef4444;font-size:16px;flex-shrink:0;"></i>
                        <span class="tw-text-sm tw-font-medium tw-text-neutral-800"><?= e($check['label']); ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <div class="tw-mb-4 tw-p-3 tw-rounded-lg" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-check-circle" style="color:#16a34a;font-size:18px;"></i>
                        <span class="tw-text-sm tw-font-medium" style="color:#15803d;">
                            <?= _l('profile_complete_ready'); ?>
                        </span>
                    </div>
                </div>
                <?php } ?>

                <p class="tw-text-sm tw-text-neutral-500 tw-mb-0">
                    <?= _l('inactive_company_modal_desc'); ?>
                </p>
            </div>

            <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:16px 28px;background:#fafafa;">
                <a href="<?= $back_url; ?>" class="btn btn-default">
                    <i class="fa fa-arrow-left tw-mr-1"></i><?= _l('inactive_company_modal_later'); ?>
                </a>
                <?php if ($percent < 100) { ?>
                <a href="<?= $edit_url; ?>" class="btn btn-primary" style="background:linear-gradient(135deg,#f59e0b,#ef4444);border:none;">
                    <i class="fa fa-edit tw-mr-1"></i><?= _l('inactive_company_modal_complete'); ?>
                </a>
                <?php } ?>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    var _restricted = <?= $restricted_js; ?>;
    var _path       = window.location.pathname + window.location.hash;

    function _isRestricted() {
        return _restricted.some(function(seg) {
            return _path.indexOf('/' + seg) !== -1;
        });
    }

    $(function() {
        if (_isRestricted()) {
            $('#inactive-company-modal').modal({ show: true, backdrop: 'static', keyboard: false });
        }
    });
})();
</script>
<?php
}
