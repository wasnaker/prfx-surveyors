<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Gdpr_surveyors
{
    private $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
    }

    public function export($surveyor_id)
    {
        $valAllowed = get_option('gdpr_contact_data_portability_allowed');
        if (empty($valAllowed)) {
            $valAllowed = [];
        } else {
            $valAllowed = unserialize($valAllowed);
        }

        $this->ci->db->where('clientid', $surveyor_id);
        $surveyors = $this->ci->db->get(db_prefix().'surveyors')->result_array();

        $this->ci->db->where('show_on_client_portal', 1);
        $this->ci->db->where('fieldto', 'surveyor');
        $this->ci->db->order_by('field_order', 'asc');
        $custom_fields = $this->ci->db->get(db_prefix().'customfields')->result_array();

        $this->ci->load->model('currencies_model');
        foreach ($surveyors as $surveyorsKey => $surveyor) {
            unset($surveyors[$surveyorsKey]['adminnote']);
            $surveyors[$surveyorsKey]['shipping_country'] = get_country($surveyor['shipping_country']);
            $surveyors[$surveyorsKey]['billing_country']  = get_country($surveyor['billing_country']);

            $surveyors[$surveyorsKey]['currency'] = $this->ci->currencies_model->get($surveyor['currency']);

            $surveyors[$surveyorsKey]['items'] = _prepare_items_array_for_export(get_items_by_type('surveyor', $surveyor['id']), 'surveyor');

            if (in_array('surveyors_notes', $valAllowed)) {
                // Notes
                $this->ci->db->where('rel_id', $surveyor['id']);
                $this->ci->db->where('rel_type', 'surveyor');

                $surveyors[$surveyorsKey]['notes'] = $this->ci->db->get(db_prefix().'notes')->result_array();
            }
            if (in_array('surveyors_activity_log', $valAllowed)) {
                // Activity
                $this->ci->db->where('rel_id', $surveyor['id']);
                $this->ci->db->where('rel_type', 'surveyor');

                $surveyors[$surveyorsKey]['activity'] = $this->ci->db->get(db_prefix().'sales_activity')->result_array();
            }
            $surveyors[$surveyorsKey]['views'] = get_views_tracking('surveyor', $surveyor['id']);

            $surveyors[$surveyorsKey]['tracked_emails'] = get_tracked_emails($surveyor['id'], 'surveyor');

            $surveyors[$surveyorsKey]['additional_fields'] = [];

            foreach ($custom_fields as $cf) {
                $surveyors[$surveyorsKey]['additional_fields'][] = [
                    'name'  => $cf['name'],
                    'value' => get_custom_field_value($surveyor['id'], $cf['id'], 'surveyor'),
                ];
            }
        }

        return $surveyors;
    }
}
