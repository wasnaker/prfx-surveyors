<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Generate surveyor PDF
 * @param  object $surveyor surveyor object from database
 * @param  string $tag       tag for bulk pdf exporter
 * @return object
 */
function surveyor_pdf($surveyor, $tag = '')
{
    return app_pdf('surveyor', FCPATH . 'modules/surveyors/libraries/pdf/Surveyor_pdf', $surveyor, $tag);
}

/**
 * Get Surveyor short_url
 * @since  Version 2.7.3
 * @param  object $surveyor
 * @return string Url
 */
function get_surveyor_shortlink($surveyor)
{
    $long_url = site_url("surveyor/{$surveyor->id}/{$surveyor->hash}");
    if (!get_option('bitly_access_token')) {
        return $long_url;
    }

    // Check if surveyor has short link, if yes return short link
    if (!empty($surveyor->short_link)) {
        return $surveyor->short_link;
    }

    // Create short link and return the newly created short link
    $short_link = app_generate_short_link([
        'long_url' => $long_url,
        'title'    => format_surveyor_number($surveyor->id),
    ]);

    if ($short_link) {
        $CI = &get_instance();
        $CI->db->where('id', $surveyor->id);
        $CI->db->update(db_prefix() . 'surveyors', [
            'short_link' => $short_link,
        ]);

        return $short_link;
    }

    return $long_url;
}

/**
 * Check surveyor restrictions - hash, client_id
 * @param  mixed $id   surveyor id
 * @param  string $hash surveyor hash
 */
function check_surveyor_restrictions($id, $hash)
{
    $CI = &get_instance();
    $CI->load->model('surveyors_model');
    if (!$hash || !$id) {
        show_404();
    }
    if (!is_client_logged_in() && !is_staff_logged_in()) {
        if (get_option('view_surveyor_only_logged_in') == 1) {
            redirect_after_login_to_current_url();
            redirect(site_url('authentication/login'));
        }
    }
    $surveyor = $CI->surveyors_model->get($id);
    if (!$surveyor || ($surveyor->hash != $hash)) {
        show_404();
    }
    // Do one more check
    if (!is_staff_logged_in()) {
        if (get_option('view_surveyor_only_logged_in') == 1) {
            if ($surveyor->client_id != get_client_user_id()) {
                show_404();
            }
        }
    }
}

/**
 * Check if surveyor email template for expiry reminders is enabled
 * @return boolean
 */
function is_surveyors_email_expiry_reminder_enabled()
{
    return total_rows(db_prefix() . 'emailtemplates', ['slug' => 'surveyor-expiry-reminder', 'active' => 1]) > 0;
}

/**
 * Check if there are sources for sending surveyor expiry reminders
 * Will be either email or SMS
 * @return boolean
 */
function is_surveyors_expiry_reminders_enabled()
{
    return is_surveyors_email_expiry_reminder_enabled() || is_sms_trigger_active(SMS_TRIGGER_SURVEYOR_EXP_REMINDER);
}

/**
 * Return RGBa surveyor status color for PDF documents
 * @param  mixed $status_id current surveyor status
 * @return string
 */
function surveyor_status_color_pdf($status_id)
{
    if ($status_id === 'active') {
        $statusColor = '0, 191, 54';
    } elseif ($status_id === 'inactive') {
        $statusColor = '252, 45, 66';
    } else {
        // pending
        $statusColor = '255, 111, 0';
    }

    return hooks()->apply_filters('surveyor_status_pdf_color', $statusColor, $status_id);
}

/**
 * Format surveyor status
 * @param  integer  $status
 * @param  string  $classes additional classes
 * @param  boolean $label   To include in html label or not
 * @return mixed
 */
function format_surveyor_status($status, $classes = '', $label = true)
{
    $id          = $status;
    $label_class = surveyor_status_color_class($status);
    $status      = surveyor_status_by_id($status);
    if ($label == true) {
        return '<span class="label label-' . $label_class . ' ' . $classes . ' s-status surveyor-status-' . $id . ' surveyor-status-' . $label_class . '">' . $status . '</span>';
    }

    return $status;
}

/**
 * Return surveyor status translated by passed status id
 * @param  mixed $id surveyor status id
 * @return string
 */
function surveyor_status_by_id($id)
{
    $map = [
        'pending'  => _l('surveyor_status_pending'),
        'active'   => _l('surveyor_status_active'),
        'inactive' => _l('surveyor_status_inactive'),
    ];
    $status = $map[$id] ?? ucfirst((string) $id);

    return hooks()->apply_filters('surveyor_status_label', $status, $id);
}

/**
 * Return surveyor status color class based on twitter bootstrap
 * @param  mixed  $id
 * @param  boolean $replace_default_by_muted
 * @return string
 */
function surveyor_status_color_class($id, $replace_default_by_muted = false)
{
    $map = [
        'pending'  => 'warning',
        'active'   => 'success',
        'inactive' => 'danger',
    ];
    $class = $map[$id] ?? ($replace_default_by_muted ? 'muted' : 'default');

    return hooks()->apply_filters('surveyor_status_color_class', $class, $id);
}

/**
 * Check if the surveyor id is last invoice
 * @param  mixed  $id surveyorid
 * @return boolean
 */
function is_last_surveyor($id)
{
    $CI  = &get_instance();
    $row = $CI->db->select('userid')
        ->where('client_type', 'surveyor')
        ->where('company_id IS NULL', null, false)
        ->order_by('userid', 'DESC')
        ->limit(1)
        ->get(db_prefix() . 'clients')->row();
    if (!$row) { return false; }
    return (int) $row->userid === (int) $id;
}

/**
 * Format surveyor number based on description
 * @param  mixed $id
 * @return string
 */
function format_surveyor_number($id)
{
    if (is_object($id)) {
        $company = $id->company ?? ($id->userid ?? '');
        return hooks()->apply_filters('format_surveyor_number', e($company), ['id' => $id->userid ?? 0, 'surveyor' => $id]);
    }

    $CI       = &get_instance();
    $surveyor = $CI->db->get_where(db_prefix() . 'clients', ['userid' => (int) $id, 'client_type' => 'surveyor'])->row();

    if (!$surveyor) { return ''; }

    return hooks()->apply_filters('format_surveyor_number', e($surveyor->company), ['id' => $id, 'surveyor' => $surveyor]);
}


/**
 * Function that return surveyor item taxes based on passed item id
 * @param  mixed $itemid
 * @return array
 */
function get_surveyor_item_taxes($itemid)
{
    $CI = &get_instance();
    $CI->db->where('itemid', $itemid);
    $CI->db->where('rel_type', 'surveyor');
    $taxes = $CI->db->get(db_prefix() . 'item_tax')->result_array();
    $i     = 0;
    foreach ($taxes as $tax) {
        $taxes[$i]['taxname'] = $tax['taxname'] . '|' . $tax['taxrate'];
        $i++;
    }

    return $taxes;
}

/**
 * Calculate surveyors percent by status
 * @param  mixed $status          surveyor status
 * @return array
 */
function get_surveyors_percent_by_status($status, $project_id = null)
{
    $CI    = get_instance();
    $total = total_rows(db_prefix() . 'clients', ['client_type' => 'surveyor']);

    $active_val      = ($status === 'active') ? 1 : 0;
    $total_by_status = ($status === 'pending')
        ? total_rows(db_prefix() . 'clients', ['client_type' => 'surveyor', 'active' => 0])
        : total_rows(db_prefix() . 'clients', ['client_type' => 'surveyor', 'active' => $active_val]);

    if ($status === 'inactive') {
        $total_by_status = total_rows(db_prefix() . 'clients', ['client_type' => 'surveyor', 'active' => 0]);
    } elseif ($status === 'active') {
        $total_by_status = total_rows(db_prefix() . 'clients', ['client_type' => 'surveyor', 'active' => 1]);
    } else {
        $total_by_status = 0;
    }

    $percent                 = ($total > 0 ? number_format(($total_by_status * 100) / $total, 2) : 0);
    $data['total_by_status'] = $total_by_status;
    $data['percent']         = $percent;
    $data['total']           = $total;

    return $data;
}

function get_surveyors_where_sql_for_staff($staff_id)
{
    $CI                                  = &get_instance();
    $has_permission_view_own             = staff_can('view_own',  'surveyors');
    $allow_staff_view_surveyors_assigned = get_option('allow_staff_view_surveyors_assigned');
    $whereUser                           = '';
    if ($has_permission_view_own) {
        $whereUser = '((' . db_prefix() . 'surveyors.addedfrom=' . $CI->db->escape_str($staff_id) . ' AND ' . db_prefix() . 'surveyors.addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature = "surveyors" AND capability="view_own"))';
        if ($allow_staff_view_surveyors_assigned == 1) {
            $whereUser .= ' OR sale_agent=' . $CI->db->escape_str($staff_id);
        }
        $whereUser .= ')';
    } else {
        $whereUser .= 'sale_agent=' . $CI->db->escape_str($staff_id);
    }

    return $whereUser;
}
/**
 * Check if staff member have assigned surveyors / added as sale agent
 * @param  mixed $staff_id staff id to check
 * @return boolean
 */
function staff_has_assigned_surveyors($staff_id = '')
{
    $CI       = &get_instance();
    $staff_id = is_numeric($staff_id) ? $staff_id : get_staff_user_id();
    $cache    = $CI->app_object_cache->get('staff-total-assigned-surveyors-' . $staff_id);

    if (is_numeric($cache)) {
        $result = $cache;
    } else {
        $result = total_rows(db_prefix() . 'surveyors', ['sale_agent' => $staff_id]);
        $CI->app_object_cache->add('staff-total-assigned-surveyors-' . $staff_id, $result);
    }

    return $result > 0 ? true : false;
}
/**
 * Check if staff member can view surveyor
 * @param  mixed $id surveyor id
 * @param  mixed $staff_id
 * @return boolean
 */
function user_can_view_surveyor($id, $staff_id = false)
{
    $CI       = &get_instance();
    $staff_id = $staff_id ? $staff_id : get_staff_user_id();

    $client = $CI->db->get_where(db_prefix() . 'clients', [
        'userid'      => (int) $id,
        'client_type' => 'surveyor',
    ])->row();

    if (!$client) { return false; }

    if (has_permission('surveyors', $staff_id, 'view')) { return true; }

    $_me = get_staff($staff_id);
    if (!$_me) { return false; }

    // Surveyor entity staff: own company or its branches
    if ($_me->client_type === 'surveyor' && $_me->client_id) {
        $my_id = (int) $_me->client_id;
        $cid   = (int) $client->userid;
        if ($my_id === $cid) { return true; }
        if ((int) $client->company_id === $my_id) { return true; }
        $me_client = $CI->db->get_where(db_prefix() . 'clients', ['userid' => $my_id])->row();
        if ($me_client && (int) $me_client->company_id === $cid) { return true; }
        return false;
    }

    if (has_permission('surveyors', $staff_id, 'view_own')) { return true; }

    return false;
}
