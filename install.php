<?php

defined('BASEPATH') or exit('No direct script access allowed');

// tblsurveyors removed — surveyors use tblclients (client_type='surveyor')
require_once(__DIR__ . '/install/api_tokens.php');
require_once(__DIR__ . '/install/surveyor_doc_equipment.php');
require_once(__DIR__ . '/install/surveyor_items.php');
require_once(__DIR__ . '/install/surveyor_activity.php');
require_once(__DIR__ . '/install/surveyor_permits.php');
require_once(__DIR__ . '/install/surveyor_permit_assessors.php');
require_once(__DIR__ . '/install/surveyor_equipment.php');

require_once __DIR__ . '/helpers/email_templates_helper.php';
surveyors_register_email_templates();

// Add module options
add_option('surveyor_registration_min_seconds', 8);
add_option('surveyor_prefix', 'SURVEYOR-');
add_option('next_surveyor_number', 1);
add_option('default_surveyor_assigned', 0);
add_option('surveyor_number_format', 1);
add_option('surveyor_year', date('Y'));
add_option('delete_only_on_last_surveyor', 1);
add_option('surveyor_number_decrement_on_delete', 0);
add_option('exclude_surveyor_from_client_area_with_draft_status', 1);
add_option('surveyor_due_after', 7);
add_option('allow_staff_view_surveyors_assigned', 1);
add_option('show_assigned_on_surveyor', 1);
add_option('require_client_logged_in_to_view_surveyor', 0);
add_option('show_project_on_surveyor', 1);
add_option('surveyors_pipeline_limit', 1);
add_option('default_surveyors_pipeline_sort', 1);
add_option('default_surveyors_pipeline_sort_type', 'asc');
add_option('surveyor_accept_identity_confirmation', 1);
add_option('surveyor_qrcode_size', '160');
add_option('surveyor_send_telegram_message', 0);
add_option('surveyor_auto_convert_to_quotation_on_client_accept', 0);
add_option('show_pdf_signature_surveyor', 0);

// Role-based capabilities — checked by surveyors_staff_can_filter via get_option().
// Key format: surveyor_{capability}_role_{role_id}  (lookup by role name, not hardcoded ID)
$_surveyor_role_caps = [
    'Surveyor' => ['view', 'view_own', 'create', 'edit', 'edit_own', 'mark_as'],
    'Surveyor' => ['view', 'view_own', 'mark_as', 'convert_to_quotation'],
];
foreach ($_surveyor_role_caps as $_surveyor_role_name => $_surveyor_caps) {
    $_surveyor_role = $CI->db->get_where(db_prefix() . 'roles', ['name' => $_surveyor_role_name])->row();
    if (!$_surveyor_role) { continue; }
    $_surveyor_rid = (int) $_surveyor_role->roleid;
    foreach ($_surveyor_caps as $_surveyor_cap) {
        add_option('surveyor_' . $_surveyor_cap . '_role_' . $_surveyor_rid, '1');
    }
}
unset($_surveyor_role_caps, $_surveyor_role_name, $_surveyor_caps, $_surveyor_role, $_surveyor_rid, $_surveyor_cap);

// Add surveyor_emails column to contacts table if not exists
if (!$CI->db->field_exists('surveyor_emails', db_prefix() . 'contacts')) {
    $CI->db->query('ALTER TABLE ' . db_prefix() . 'contacts ADD COLUMN `surveyor_emails` tinyint(1) NOT NULL DEFAULT 1 AFTER `estimate_emails`');
}


// ---------------------------------------------------------------------------
// start from previouse installation:
// ---------------------------------------------------------------------------

$CI = &get_instance();

// ---------------------------------------------------------------------------
// Add client_type column to tblclients
// Distinguishes record ownership: 'surveyor', 'association', etc.
// Default 'surveyor' so all existing records remain as surveyors.
// ---------------------------------------------------------------------------
if (!$CI->db->field_exists('client_type', db_prefix() . 'clients')) {
    $CI->db->query(
        'ALTER TABLE `' . db_prefix() . 'clients`
         ADD COLUMN `client_type` VARCHAR(30) NOT NULL DEFAULT \'surveyor\'
         AFTER `active`'
    );
}

// Add company_id for branch relationship (branch.company_id → parent.userid)
if (!$CI->db->field_exists('company_id', db_prefix() . 'clients')) {
    $CI->db->query(
        'ALTER TABLE `' . db_prefix() . 'clients`
         ADD COLUMN `company_id` INT NULL DEFAULT NULL
         AFTER `client_type`'
    );
}

// Unique branch name per parent surveyor
$branch_name_unique = $CI->db->query(
    'SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
     WHERE table_schema = DATABASE() AND table_name = \'' . db_prefix() . 'clients\'
     AND index_name = \'uk_branch_name\''
)->row();
if (!$branch_name_unique || (int)$branch_name_unique->cnt === 0) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD UNIQUE KEY `uk_branch_name` (`company_id`, `company`)');
}

// use_vat: branch uses parent company's NPWP instead of its own NITKU
if (!$CI->db->field_exists('use_vat', db_prefix() . 'clients')) {
    $CI->db->query(
        'ALTER TABLE `' . db_prefix() . 'clients`
         ADD COLUMN `use_vat` TINYINT(1) NOT NULL DEFAULT 0
         AFTER `nitku`'
    );
}

// Add nitku (tax ID for branch offices) with unique constraint
if (!$CI->db->field_exists('nitku', db_prefix() . 'clients')) {
    $CI->db->query(
        'ALTER TABLE `' . db_prefix() . 'clients`
         ADD COLUMN `nitku` VARCHAR(30) NULL DEFAULT NULL
         AFTER `company_id`'
    );
}
// Unique constraint on vat (convert empty string to NULL first)
$vat_unique = $CI->db->query(
    'SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
     WHERE table_schema = DATABASE() AND table_name = \'' . db_prefix() . 'clients\'
     AND index_name = \'uk_vat\''
)->row();
if (!$vat_unique || (int)$vat_unique->cnt === 0) {
    $CI->db->query('UPDATE `' . db_prefix() . 'clients` SET vat = NULL WHERE vat = \'\'');
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD UNIQUE KEY `uk_vat` (`vat`)');
}

$nitku_unique = $CI->db->query(
    'SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
     WHERE table_schema = DATABASE() AND table_name = \'' . db_prefix() . 'clients\'
     AND index_name = \'uk_nitku\''
)->row();
if (!$nitku_unique || (int)$nitku_unique->cnt === 0) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD UNIQUE KEY `uk_nitku` (`nitku`)');
}

// ---------------------------------------------------------------------------
// Surveyor self-registration support
// ---------------------------------------------------------------------------

// Add registration_status to tblstaff
if (!$CI->db->field_exists('registration_status', db_prefix() . 'staff')) {
    $CI->db->query("ALTER TABLE `" . db_prefix() . "staff` ADD COLUMN `registration_status` ENUM('pending','user_activated','approved','rejected') NOT NULL DEFAULT 'approved' AFTER `is_entity_owner`");
} else {
    $CI->db->query("ALTER TABLE `" . db_prefix() . "staff` MODIFY `registration_status` ENUM('pending','user_activated','approved','rejected') NOT NULL DEFAULT 'approved'");
}

// Create Surveyor role
$existing_surveyor_role = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor'])->row();
if (!$existing_surveyor_role) {
    $CI->db->insert(db_prefix() . 'roles', ['name' => 'Surveyor', 'permissions' => '']);
}

// Assign edit_own permission to Surveyor role
$surveyor_role = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor'])->row();
if ($surveyor_role) {
    $perms = ($surveyor_role->permissions && $surveyor_role->permissions !== '')
        ? unserialize($surveyor_role->permissions) ?: []
        : [];
    $changed = false;
    if (!isset($perms['surveyors']['view_own'])) { $perms['surveyors']['view_own'] = '1'; $changed = true; }
    if (!isset($perms['surveyors']['edit_own'])) { $perms['surveyors']['edit_own'] = '1'; $changed = true; }
    if ($changed) {
        $CI->db->where('roleid', $surveyor_role->roleid)
               ->update(db_prefix() . 'roles', ['permissions' => serialize($perms)]);
    }
}

// Create Surveyor Admin role
$existing = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Admin'])->row();
if (!$existing) {
    $CI->db->insert(db_prefix() . 'roles', ['name' => 'Surveyor Admin', 'permissions' => '']);
}
$r = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Admin'])->row();
if ($r) {
    $perms   = ($r->permissions && $r->permissions !== '') ? unserialize($r->permissions) ?: [] : [];
    $changed = false;
    foreach (['view_own', 'edit_own'] as $cap) {
        if (!isset($perms['surveyors'][$cap])) { $perms['surveyors'][$cap] = '1'; $changed = true; }
    }
    if (!isset($perms['equipments']['view']))     { $perms['equipments']['view']     = '1'; $changed = true; }
    if (!isset($perms['equipments']['edit_own'])) { $perms['equipments']['edit_own'] = '1'; $changed = true; }
    if ($changed) {
        $CI->db->where('roleid', $r->roleid)
               ->update(db_prefix() . 'roles', ['permissions' => serialize($perms)]);
    }
}

// Create Assessor role
$existing = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Assessor'])->row();
if (!$existing) {
    $CI->db->insert(db_prefix() . 'roles', ['name' => 'Assessor', 'permissions' => '']);
}

// Create functional surveyor roles
foreach (['Surveyor Sales', 'Surveyor Finance', 'Surveyor Operation'] as $_role_name) {
    if (!$CI->db->get_where(db_prefix() . 'roles', ['name' => $_role_name])->row()) {
        $CI->db->insert(db_prefix() . 'roles', ['name' => $_role_name, 'permissions' => '']);
    }
}
unset($_role_name);

// Create Surveyor Branch Admin role
$existing = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Branch Admin'])->row();
if (!$existing) {
    $CI->db->insert(db_prefix() . 'roles', ['name' => 'Surveyor Branch Admin', 'permissions' => '']);
}
$r = $CI->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Branch Admin'])->row();
if ($r) {
    $perms   = ($r->permissions && $r->permissions !== '') ? unserialize($r->permissions) ?: [] : [];
    $changed = false;
    foreach (['view_own', 'edit_own'] as $cap) {
        if (!isset($perms['surveyors'][$cap])) { $perms['surveyors'][$cap] = '1'; $changed = true; }
    }
    if (!isset($perms['equipments']['view']))     { $perms['equipments']['view']     = '1'; $changed = true; }
    if (!isset($perms['equipments']['edit_own'])) { $perms['equipments']['edit_own'] = '1'; $changed = true; }
    if ($changed) {
        $CI->db->where('roleid', $r->roleid)
               ->update(db_prefix() . 'roles', ['permissions' => serialize($perms)]);
    }
}

// Default settings
if (!$CI->db->field_exists('logo_light', db_prefix() . 'clients')) {
    $CI->db->query('ALTER TABLE ' . db_prefix() . 'clients ADD COLUMN `logo_light` varchar(100) NULL DEFAULT NULL');
}
if (!$CI->db->field_exists('logo_dark', db_prefix() . 'clients')) {
    $CI->db->query('ALTER TABLE ' . db_prefix() . 'clients ADD COLUMN `logo_dark` varchar(100) NULL DEFAULT NULL');
}
if (!$CI->db->field_exists('npwp_file', db_prefix() . 'clients')) {
    $CI->db->query('ALTER TABLE ' . db_prefix() . 'clients ADD COLUMN `npwp_file` varchar(200) NULL DEFAULT NULL');
}


// ---------------------------------------------------------------------------
// end previouse installation:
// ---------------------------------------------------------------------------
