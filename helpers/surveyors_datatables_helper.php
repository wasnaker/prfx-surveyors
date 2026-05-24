<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ─── Hook Registrations ────────────────────────────────────────────────────────

hooks()->add_filter('customers_table_sql_where',          'surveyors_filter_customers_by_connection');
hooks()->add_filter('can_view_customer_profile',          'surveyors_can_view_customer_profile', 10, 2);
hooks()->add_filter('personnels_permits_datatable_where', 'surveyors_filter_permits_by_connection', 10, 2);
hooks()->add_filter('can_view_personnel_permit',          'surveyors_can_view_personnel_permit', 10, 2);
hooks()->add_filter('customers_permits_datatable_where',  'surveyors_filter_customer_permits_by_connection', 10, 2);
hooks()->add_filter('can_view_customer_permit',           'surveyors_can_view_customer_permit', 10, 2);

// ─── Helper Functions ──────────────────────────────────────────────────────────

function _surveyors_is_surveyor_entity_user()
{
    $me = get_staff(get_staff_user_id());
    return $me && $me->client_type === 'surveyor' && !empty($me->client_id);
}

function _surveyors_get_connected_customer_ids(int $surveyor_id): array
{
    $CI  = &get_instance();
    $pfx = db_prefix();
    $rows = $CI->db->query("
        SELECT IF(cc.client_id_a = {$surveyor_id}, cc.client_id_b, cc.client_id_a) AS customer_id
        FROM {$pfx}client_connections cc
        WHERE (cc.client_id_a = {$surveyor_id} OR cc.client_id_b = {$surveyor_id})
          AND cc.status = 'active'
    ")->result_array();
    return array_column($rows, 'customer_id');
}

// ─── Customer Datatable Hooks ──────────────────────────────────────────────────

function surveyors_filter_customers_by_connection($where)
{
    if (!_surveyors_is_surveyor_entity_user()) { return $where; }

    $me          = get_staff(get_staff_user_id());
    $surveyor_id = (int) $me->client_id;
    $pfx         = db_prefix();

    $where[] = 'AND ' . $pfx . 'clients.userid IN (
        SELECT IF(cc.client_id_a = ' . $surveyor_id . ', cc.client_id_b, cc.client_id_a)
        FROM ' . $pfx . 'client_connections cc
        WHERE (cc.client_id_a = ' . $surveyor_id . ' OR cc.client_id_b = ' . $surveyor_id . ')
          AND cc.status = \'active\'
    )';

    return $where;
}

function surveyors_can_view_customer_profile($can_view, $customer_id)
{
    if (!_surveyors_is_surveyor_entity_user()) { return $can_view; }

    $me          = get_staff(get_staff_user_id());
    $surveyor_id = (int) $me->client_id;
    $connected   = _surveyors_get_connected_customer_ids($surveyor_id);

    return in_array((int) $customer_id, $connected);
}

// ─── Personnel Permits Hooks ───────────────────────────────────────────────────

function surveyors_filter_permits_by_connection($where, $me)
{
    if (!_surveyors_is_surveyor_entity_user()) { return $where; }

    $surveyor_id = (int) $me->client_id;
    $pfx         = db_prefix();

    $where[] = 'AND s.client_type = "surveyor"';
    $where[] = 'AND s.client_id IN (
        SELECT IF(cc.client_id_a = ' . $surveyor_id . ', cc.client_id_b, cc.client_id_a)
        FROM ' . $pfx . 'client_connections cc
        WHERE (cc.client_id_a = ' . $surveyor_id . ' OR cc.client_id_b = ' . $surveyor_id . ')
          AND cc.status = \'active\'
    )';
    $where[] = 'AND p.status = "active"';

    return $where;
}

function surveyors_can_view_personnel_permit($can_view, $permit)
{
    if (!_surveyors_is_surveyor_entity_user()) { return $can_view; }

    $CI          = &get_instance();
    $me          = get_staff(get_staff_user_id());
    $surveyor_id = (int) $me->client_id;

    $staff = $CI->db->get_where(db_prefix() . 'staff', ['staffid' => (int) $permit->staff_id])->row();
    if (!$staff || $staff->client_type !== 'surveyor') { return 'not_connected'; }

    if (!entity_in_scope($me, (int) $staff->client_id, 'surveyor', 'personnels', 'view')) {
        return 'not_connected';
    }

    if ($permit->status !== 'active') { return 'permit_not_active'; }

    return $can_view;
}

// ─── Customer Permits Hooks ────────────────────────────────────────────────────

function surveyors_filter_customer_permits_by_connection($where, $me)
{
    if (!_surveyors_is_surveyor_entity_user()) { return $where; }

    $surveyor_id = (int) $me->client_id;
    $pfx         = db_prefix();

    $where[] = 'AND p.customer_id IN (
        SELECT IF(cc.client_id_a = ' . $surveyor_id . ', cc.client_id_b, cc.client_id_a)
        FROM ' . $pfx . 'client_connections cc
        WHERE (cc.client_id_a = ' . $surveyor_id . ' OR cc.client_id_b = ' . $surveyor_id . ')
          AND cc.status = \'active\'
    )';
    $where[] = 'AND p.status = "active"';

    return $where;
}

function surveyors_can_view_customer_permit($can_view, $permit)
{
    if (!_surveyors_is_surveyor_entity_user()) { return $can_view; }

    $me          = get_staff(get_staff_user_id());
    $surveyor_id = (int) $me->client_id;

    $connected = _surveyors_get_connected_customer_ids($surveyor_id);
    if (!in_array((int) $permit->customer_id, $connected)) { return 'not_connected'; }

    if ($permit->status !== 'active') { return 'permit_not_active'; }

    return $can_view;
}
