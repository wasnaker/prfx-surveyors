<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ─── Relation Data Hooks ─────────────────────────────────────────────────────
// Custom relation types: surveyor_surveyor, surveyor_equipment

hooks()->add_filter('get_relation_data',    'surveyors_get_relation_data');
hooks()->add_filter('relation_values',      'surveyors_relation_values');
hooks()->add_filter('init_relation_options', 'surveyors_init_relation_options');

/**
 * Get the client ID associated with the logged-in staff member
 * @return int Client ID, or 0 if not associated with a surveyor entity
 */
function surveyors_get_logged_staff_client_id()
{
    $CI    = &get_instance();
    $staff = $CI->db->get_where(db_prefix() . 'staff', ['staffid' => get_staff_user_id()])->row();
    return ($staff && !empty($staff->client_id)) ? (int) $staff->client_id : 0;
}

/**
 * Hook filter: get_relation_data
 * Extends relation system to support custom relation types:
 * - surveyor_surveyor: Get surveyors connected to the logged-in surveyor
 * - surveyor_equipment: Get equipment registered to the logged-in surveyor
 *
 * @param array $data Existing relation data (pass-through)
 * @return array Filtered relation data with custom types populated
 */
function surveyors_get_relation_data($data)
{
    $CI   = &get_instance();
    $type = $CI->input->post('type');
    $q    = trim((string) $CI->input->post('q'));

    if ($type === 'surveyor_surveyor') {
        $client_id = surveyors_get_logged_staff_client_id();

        $CI->db->select('c.userid as id, c.company as name')
               ->from(db_prefix() . 'clients c');

        if ($client_id) {
            $CI->db->join(
                db_prefix() . 'client_connections cc',
                '(cc.client_id_a = ' . $client_id . ' AND cc.client_id_b = c.userid)
                 OR (cc.client_id_b = ' . $client_id . ' AND cc.client_id_a = c.userid)',
                'inner'
            )->where('cc.status', 'active');
        }

        $CI->db->where('c.client_type', 'surveyor')->where('c.active', 1);
        if ($q) { $CI->db->like('c.company', $q); }

        return $CI->db->get()->result_array();
    }

    if ($type === 'surveyor_equipment') {
        $client_id = surveyors_get_logged_staff_client_id();

        $CI->db->select('ce.id, i.description as name')
               ->from(db_prefix() . 'surveyor_equipment ce')
               ->join(db_prefix() . 'items i', 'i.id = ce.item_id');

        if ($client_id) {
            $CI->db->where('ce.client_id', $client_id);
        }

        if ($q) { $CI->db->like('i.description', $q); }

        return $CI->db->get()->result_array();
    }

    return $data;
}

/**
 * Hook filter: relation_values
 * Format custom relation types for display in UI:
 * - surveyor_surveyor: Display surveyor company name with link to profile
 * - surveyor_equipment: Display equipment description (no link)
 *
 * @param array $values Relation values array with 'type', 'relation', etc.
 * @return array Filtered values with name, id, link formatted for UI
 */
function surveyors_relation_values($values)
{
    $type = isset($values['type']) ? $values['type'] : '';

    if ($type === 'surveyor_surveyor') {
        $relation       = $values['relation'];
        $id             = is_array($relation) ? $relation['id']   : $relation->id;
        $name           = is_array($relation) ? $relation['name'] : $relation->name;
        $values['id']   = $id;
        $values['name'] = $name;
        $values['link'] = admin_url('clients/client/' . $id);
        return $values;
    }

    if ($type === 'surveyor_equipment') {
        $relation       = $values['relation'];
        $id             = is_array($relation) ? $relation['id']   : $relation->id;
        $name           = is_array($relation) ? $relation['name'] : $relation->name;
        $values['id']   = $id;
        $values['name'] = $name;
        $values['link'] = '';
        return $values;
    }

    return $values;
}

/**
 * Hook filter: init_relation_options
 * Apply permission checks and filtering for custom relation types
 * Only staff with 'surveyors' view permission can access relation options
 *
 * @param array $data Relation options to render (pass-through)
 * @return array Filtered options, or empty array if permission denied
 */
function surveyors_init_relation_options($data)
{
    $CI   = &get_instance();
    $type = $CI->input->post('type');

    if ($type === 'surveyor_surveyor' || $type === 'surveyor_equipment') {
        if (!has_permission('surveyors', '', 'view')) {
            return [];
        }
        return $data;
    }

    return $data;
}
