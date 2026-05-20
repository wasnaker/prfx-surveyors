<?php

use app\services\AbstractKanban;
use app\services\surveyors\SurveyorsPipeline;

defined('BASEPATH') or exit('No direct script access allowed');

class Surveyors_model extends App_Model
{
    private $statuses;
    private $shipping_fields = ['shipping_street', 'shipping_city', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'];

    public function __construct()
    {
        parent::__construct();

        $this->statuses = hooks()->apply_filters('before_set_surveyor_statuses', [
            1,
            2,
            5,
            3,
            4,
        ]);
    }

    /**
     * Get unique sale agent for surveyors / Used for filters
     *
     * @return array
     */
    public function get_sale_agents()
    {
        return $this->db->query("SELECT DISTINCT(sale_agent) as sale_agent, CONCAT(firstname, ' ', lastname) as full_name FROM " . db_prefix() . 'surveyors JOIN ' . db_prefix() . 'staff on ' . db_prefix() . 'staff.staffid=' . db_prefix() . 'surveyors.sale_agent WHERE sale_agent != 0')->result_array();
    }

    /**
     * Get surveyor/s
     *
     * @param mixed $id    surveyor id
     * @param array $where perform where
     *
     * @return mixed
     */
    public function get($id = '', $where = [])
    {
        $this->db->select('*, ' . db_prefix() . 'currencies.id as currencyid, ' . db_prefix() . 'surveyors.id as id, ' . db_prefix() . 'currencies.name as currency_name');
        $this->db->from(db_prefix() . 'surveyors');
        $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'surveyors.currency', 'left');
        $this->db->where($where);
        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'surveyors.id', $id);
            $surveyor = $this->db->get()->row();
            if ($surveyor) {
                $surveyor->attachments                           = $this->get_attachments($id);
                $surveyor->visible_attachments_to_surveyor_found = false;

                foreach ($surveyor->attachments as $attachment) {
                    if ($attachment['visible_to_surveyor'] == 1) {
                        $surveyor->visible_attachments_to_surveyor_found = true;

                        break;
                    }
                }

                $surveyor->items     = get_items_by_type('surveyor', $id);
                $surveyor->equipment = $this->get_surveyor_equipment($id);

                if ($surveyor->project_id) {
                    $this->load->model('projects_model');
                    $surveyor->project_data = $this->projects_model->get($surveyor->project_id);
                }

                $surveyor->client = $this->clients_model->get($surveyor->clientid);

                if (! $surveyor->client) {
                    $surveyor->client          = new stdClass();
                    $surveyor->client->company = $surveyor->deleted_surveyor_name;
                }

                $this->load->model('email_schedule_model');
                $surveyor->scheduled_email = $this->email_schedule_model->get($id, 'surveyor');
            }

            return $surveyor;
        }
        $this->db->order_by('number,YEAR(date)', 'desc');

        return $this->db->get()->result_array();
    }

    /**
     * Get surveyor statuses
     *
     * @return array
     */
    public function get_statuses()
    {
        return $this->statuses;
    }

    public function clear_signature($id)
    {
        $this->db->select('signature');
        $this->db->where('id', $id);
        $surveyor = $this->db->get(db_prefix() . 'surveyors')->row();

        if ($surveyor) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'surveyors', ['signature' => null]);

            if (! empty($surveyor->signature)) {
                unlink(get_upload_path_by_type('surveyor') . $id . '/' . $surveyor->signature);
            }

            return true;
        }

        return false;
    }

    /**
     * Convert surveyor to invoice
     *
     * @param mixed $id            surveyor id
     * @param mixed $client
     * @param mixed $draft_invoice
     *
     * @return mixed New invoice ID
     */
    public function convert_to_invoice($id, $client = false, $draft_invoice = false)
    {
        // Recurring invoice date is okey lets convert it to new invoice
        $_surveyor = $this->get($id);

        $new_invoice_data = [];
        if ($draft_invoice == true) {
            $new_invoice_data['save_as_draft'] = true;
        }
        $new_invoice_data['clientid']   = $_surveyor->clientid;
        $new_invoice_data['project_id'] = $_surveyor->project_id;
        $new_invoice_data['number']     = get_option('next_invoice_number');
        $new_invoice_data['date']       = _d(date('Y-m-d'));
        $new_invoice_data['duedate']    = _d(date('Y-m-d'));
        if (get_option('invoice_due_after') != 0) {
            $new_invoice_data['duedate'] = _d(date('Y-m-d', strtotime('+' . get_option('invoice_due_after') . ' DAY', strtotime(date('Y-m-d')))));
        }
        $new_invoice_data['show_quantity_as'] = $_surveyor->show_quantity_as;
        $new_invoice_data['currency']         = $_surveyor->currency;
        $new_invoice_data['subtotal']         = $_surveyor->subtotal;
        $new_invoice_data['total']            = $_surveyor->total;
        $new_invoice_data['adjustment']       = $_surveyor->adjustment;
        $new_invoice_data['discount_percent'] = $_surveyor->discount_percent;
        $new_invoice_data['discount_total']   = $_surveyor->discount_total;
        $new_invoice_data['discount_type']    = $_surveyor->discount_type;
        $new_invoice_data['sale_agent']       = $_surveyor->sale_agent;
        // Since version 1.0.6
        $new_invoice_data['billing_street']   = clear_textarea_breaks($_surveyor->billing_street);
        $new_invoice_data['billing_city']     = $_surveyor->billing_city;
        $new_invoice_data['billing_state']    = $_surveyor->billing_state;
        $new_invoice_data['billing_zip']      = $_surveyor->billing_zip;
        $new_invoice_data['billing_country']  = $_surveyor->billing_country;
        $new_invoice_data['shipping_street']  = clear_textarea_breaks($_surveyor->shipping_street);
        $new_invoice_data['shipping_city']    = $_surveyor->shipping_city;
        $new_invoice_data['shipping_state']   = $_surveyor->shipping_state;
        $new_invoice_data['shipping_zip']     = $_surveyor->shipping_zip;
        $new_invoice_data['shipping_country'] = $_surveyor->shipping_country;

        if ($_surveyor->include_shipping == 1) {
            $new_invoice_data['include_shipping'] = 1;
        }

        $new_invoice_data['show_shipping_on_invoice'] = $_surveyor->show_shipping_on_surveyor;
        $new_invoice_data['terms']                    = get_option('predefined_terms_invoice');
        $new_invoice_data['clientnote']               = get_option('predefined_clientnote_invoice');
        // Set to unpaid status automatically
        $new_invoice_data['status']    = 1;
        $new_invoice_data['adminnote'] = '';

        $this->load->model('payment_modes_model');
        $modes = $this->payment_modes_model->get('', [
            'expenses_only !=' => 1,
        ]);
        $temp_modes = [];

        foreach ($modes as $mode) {
            if ($mode['selected_by_default'] == 0) {
                continue;
            }
            $temp_modes[] = $mode['id'];
        }
        $new_invoice_data['allowed_payment_modes'] = $temp_modes;
        $new_invoice_data['newitems']              = [];
        $custom_fields_items                       = get_custom_fields('items');
        $key                                       = 1;

        foreach ($_surveyor->items as $item) {
            $new_invoice_data['newitems'][$key]['description']      = $item['description'];
            $new_invoice_data['newitems'][$key]['long_description'] = clear_textarea_breaks($item['long_description']);
            $new_invoice_data['newitems'][$key]['qty']              = $item['qty'];
            $new_invoice_data['newitems'][$key]['unit']             = $item['unit'];
            $new_invoice_data['newitems'][$key]['taxname']          = [];
            $taxes                                                  = get_surveyor_item_taxes($item['id']);

            foreach ($taxes as $tax) {
                // tax name is in format TAX1|10.00
                array_push($new_invoice_data['newitems'][$key]['taxname'], $tax['taxname']);
            }
            $new_invoice_data['newitems'][$key]['rate']  = $item['rate'];
            $new_invoice_data['newitems'][$key]['order'] = $item['item_order'];

            foreach ($custom_fields_items as $cf) {
                $new_invoice_data['newitems'][$key]['custom_fields']['items'][$cf['id']] = get_custom_field_value($item['id'], $cf['id'], 'items', false);

                if (! defined('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST')) {
                    define('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST', true);
                }
            }
            $key++;
        }
        $this->load->model('invoices_model');
        $id = $this->invoices_model->add($new_invoice_data);
        if ($id) {
            // Surveyor accepted the surveyor and is auto converted to invoice
            if (! is_staff_logged_in()) {
                $this->db->where('rel_type', 'invoice');
                $this->db->where('rel_id', $id);
                $this->db->delete(db_prefix() . 'sales_activity');
                $this->invoices_model->log_invoice_activity($id, 'invoice_activity_auto_converted_from_surveyor', true, serialize([
                    '<a href="' . admin_url('surveyors/list_surveyors/' . $_surveyor->id) . '">' . format_surveyor_number($_surveyor->id) . '</a>',
                ]));
            }
            // For all cases update addefrom and sale agent from the invoice
            // May happen staff is not logged in and these values to be 0
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'invoices', [
                'addedfrom'  => $_surveyor->addedfrom,
                'sale_agent' => $_surveyor->sale_agent,
            ]);

            // Update surveyor with the new invoice data and set to status accepted
            $this->db->where('id', $_surveyor->id);
            $this->db->update(db_prefix() . 'surveyors', [
                'quoted_date' => date('Y-m-d H:i:s'),
                'quotationid'     => $id,
                'status'        => 4,
            ]);

            if (is_custom_fields_smart_transfer_enabled()) {
                $this->db->where('fieldto', 'surveyor');
                $this->db->where('active', 1);
                $cfSurveyors = $this->db->get(db_prefix() . 'customfields')->result_array();

                foreach ($cfSurveyors as $field) {
                    $tmpSlug = explode('_', $field['slug'], 2);
                    if (isset($tmpSlug[1])) {
                        $this->db->where('fieldto', 'invoice');

                        $this->db->group_start();
                        $this->db->like('slug', 'invoice_' . $tmpSlug[1], 'after');
                        $this->db->where('type', $field['type']);
                        $this->db->where('options', $field['options']);
                        $this->db->where('active', 1);
                        $this->db->group_end();

                        // $this->db->where('slug LIKE "invoice_' . $tmpSlug[1] . '%" AND type="' . $field['type'] . '" AND options="' . $field['options'] . '" AND active=1');
                        $cfTransfer = $this->db->get(db_prefix() . 'customfields')->result_array();

                        // Don't make mistakes
                        // Only valid if 1 result returned
                        // + if field names similarity is equal or more then CUSTOM_FIELD_TRANSFER_SIMILARITY%
                        if (count($cfTransfer) == 1 && ((similarity($field['name'], $cfTransfer[0]['name']) * 100) >= CUSTOM_FIELD_TRANSFER_SIMILARITY)) {
                            $value = get_custom_field_value($_surveyor->id, $field['id'], 'surveyor', false);

                            if ($value == '') {
                                continue;
                            }

                            $this->db->insert(db_prefix() . 'customfieldsvalues', [
                                'relid'   => $id,
                                'fieldid' => $cfTransfer[0]['id'],
                                'fieldto' => 'invoice',
                                'value'   => $value,
                            ]);
                        }
                    }
                }
            }

            if ($client == false) {
                $this->log_surveyor_activity($_surveyor->id, 'surveyor_activity_converted', false, serialize([
                    '<a href="' . admin_url('invoices/list_invoices/' . $id) . '">' . format_invoice_number($id) . '</a>',
                ]));
            }

            hooks()->do_action('surveyor_converted_to_invoice', ['invoice_id' => $id, 'surveyor_id' => $_surveyor->id]);
        }

        return $id;
    }

    /**
     * Convert SURVEYOR to a draft Quotation.
     * Equipment units become line items at rate=0 so the surveyor can fill prices.
     */
    public function convert_to_quotation($surveyor_id)
    {
        $_surveyor = $this->get($surveyor_id);
        $this->load->model('quotations/Quotations_model', 'quotations_model');

        $data = [
            'clientid'         => $_surveyor->clientid,
            'project_id'       => $_surveyor->project_id,
            'number'           => get_option('next_quotation_number'),
            'date'             => _d(date('Y-m-d')),
            'expirydate'       => !empty($_surveyor->expirydate) ? _d($_surveyor->expirydate) : null,
            'show_quantity_as' => $_surveyor->show_quantity_as,
            'currency'         => $_surveyor->currency,
            'subtotal'         => 0,
            'total'            => 0,
            'total_tax'        => 0,
            'adjustment'       => 0,
            'discount_percent' => 0,
            'discount_total'   => 0,
            'discount_type'    => $_surveyor->discount_type,
            'billing_street'   => clear_textarea_breaks($_surveyor->billing_street),
            'billing_city'     => $_surveyor->billing_city,
            'billing_state'    => $_surveyor->billing_state,
            'billing_zip'      => $_surveyor->billing_zip,
            'billing_country'  => $_surveyor->billing_country,
            'shipping_street'  => clear_textarea_breaks($_surveyor->shipping_street),
            'shipping_city'    => $_surveyor->shipping_city,
            'shipping_state'   => $_surveyor->shipping_state,
            'shipping_zip'     => $_surveyor->shipping_zip,
            'shipping_country' => $_surveyor->shipping_country,
            'include_shipping' => $_surveyor->include_shipping,
            'terms'            => get_option('predefined_terms_quotation'),
            'clientnote'       => get_option('predefined_clientnote_quotation'),
            'status'           => 1, // draft — surveyor adds rates before sending
            'adminnote'        => '',
            'reference_no'     => $_surveyor->reference_no,
            'sale_agent'       => get_staff_user_id(),
            'newitems'         => [],
        ];

        $key = 1;

        // Convert each equipment unit to a line item (rate=0, surveyor fills in)
        foreach (($_surveyor->equipment ?? []) as $eq) {
            $details = array_filter([
                !empty($eq['unit_code'])        ? 'Unit: ' . $eq['unit_code'] : null,
                !empty($eq['serial_number'])    ? 'S/N: ' . $eq['serial_number'] : null,
                !empty($eq['location'])         ? 'Location: ' . $eq['location'] : null,
                !empty($eq['cert_expired_date'])? 'Cert Exp: ' . $eq['cert_expired_date'] : null,
            ]);
            $data['newitems'][$key] = [
                'description'           => $eq['item_name'],
                'long_description'      => implode("\n", $details),
                'qty'                   => 1,
                'rate'                  => 0,
                'unit'                  => '',
                'taxname'               => [],
                'order'                 => $key,
                'surveyor_equipment_id' => $eq['surveyor_equipment_id'],
            ];
            $key++;
        }

        // Copy existing SURVEYOR line items (with their rates and taxes)
        $custom_fields_items = get_custom_fields('items');
        foreach (($_surveyor->items ?? []) as $item) {
            $taxes = get_surveyor_item_taxes($item['id']);
            $taxnames = array_column($taxes, 'taxname');
            $data['newitems'][$key] = [
                'description'      => $item['description'],
                'long_description' => clear_textarea_breaks($item['long_description']),
                'qty'              => $item['qty'],
                'rate'             => $item['rate'],
                'unit'             => $item['unit'],
                'taxname'          => $taxnames,
                'order'            => $item['item_order'],
            ];
            foreach ($custom_fields_items as $cf) {
                $data['newitems'][$key]['custom_fields']['items'][$cf['id']] = get_custom_field_value($item['id'], $cf['id'], 'items', false);
                if (!defined('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST')) {
                    define('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST', true);
                }
            }
            $key++;
        }

        $quotation_id = $this->quotations_model->add($data);

        if ($quotation_id) {
            $this->db->where('id', $surveyor_id);
            $this->db->update(db_prefix() . 'surveyors', [
                'quotationid' => $quotation_id,
            ]);

            $this->db->where('id', $quotation_id);
            $this->db->update(db_prefix() . 'quotations', [
                'surveyor_id' => $surveyor_id,
            ]);

            $this->log_surveyor_activity($surveyor_id, 'surveyor_activity_converted_to_quotation', false, serialize([
                '<a href="' . admin_url('quotations/list_quotations/' . $quotation_id) . '">' . format_quotation_number($quotation_id) . '</a>',
            ]));
        }

        return $quotation_id;
    }

    /**
     * Copy surveyor
     *
     * @param mixed $id surveyor id to copy
     *
     * @return mixed
     */
    public function copy($id)
    {
        $_surveyor                       = $this->get($id);
        $new_surveyor_data               = [];
        $new_surveyor_data['clientid']   = $_surveyor->clientid;
        $new_surveyor_data['project_id'] = $_surveyor->project_id;
        $new_surveyor_data['number']     = get_option('next_surveyor_number');
        $new_surveyor_data['date']       = _d(date('Y-m-d'));
        $new_surveyor_data['expirydate'] = null;

        if ($_surveyor->expirydate && get_option('surveyor_due_after') != 0) {
            $new_surveyor_data['expirydate'] = _d(date('Y-m-d', strtotime('+' . get_option('surveyor_due_after') . ' DAY', strtotime(date('Y-m-d')))));
        }

        $new_surveyor_data['show_quantity_as'] = $_surveyor->show_quantity_as;
        $new_surveyor_data['currency']         = $_surveyor->currency;
        $new_surveyor_data['subtotal']         = $_surveyor->subtotal;
        $new_surveyor_data['total']            = $_surveyor->total;
        $new_surveyor_data['adminnote']        = $_surveyor->adminnote;
        $new_surveyor_data['adjustment']       = $_surveyor->adjustment;
        $new_surveyor_data['discount_percent'] = $_surveyor->discount_percent;
        $new_surveyor_data['discount_total']   = $_surveyor->discount_total;
        $new_surveyor_data['discount_type']    = $_surveyor->discount_type;
        $new_surveyor_data['terms']            = $_surveyor->terms;
        $new_surveyor_data['sale_agent']       = $_surveyor->sale_agent;
        $new_surveyor_data['reference_no']     = $_surveyor->reference_no;
        // Since version 1.0.6
        $new_surveyor_data['billing_street']   = clear_textarea_breaks($_surveyor->billing_street);
        $new_surveyor_data['billing_city']     = $_surveyor->billing_city;
        $new_surveyor_data['billing_state']    = $_surveyor->billing_state;
        $new_surveyor_data['billing_zip']      = $_surveyor->billing_zip;
        $new_surveyor_data['billing_country']  = $_surveyor->billing_country;
        $new_surveyor_data['shipping_street']  = clear_textarea_breaks($_surveyor->shipping_street);
        $new_surveyor_data['shipping_city']    = $_surveyor->shipping_city;
        $new_surveyor_data['shipping_state']   = $_surveyor->shipping_state;
        $new_surveyor_data['shipping_zip']     = $_surveyor->shipping_zip;
        $new_surveyor_data['shipping_country'] = $_surveyor->shipping_country;
        if ($_surveyor->include_shipping == 1) {
            $new_surveyor_data['include_shipping'] = $_surveyor->include_shipping;
        }
        $new_surveyor_data['show_shipping_on_surveyor'] = $_surveyor->show_shipping_on_surveyor;
        // Set to unpaid status automatically
        $new_surveyor_data['status']     = 1;
        $new_surveyor_data['clientnote'] = $_surveyor->clientnote;
        $new_surveyor_data['adminnote']  = '';
        $new_surveyor_data['newitems']   = [];
        $custom_fields_items             = get_custom_fields('items');
        $key                             = 1;

        foreach ($_surveyor->items as $item) {
            $new_surveyor_data['newitems'][$key]['description']      = $item['description'];
            $new_surveyor_data['newitems'][$key]['long_description'] = clear_textarea_breaks($item['long_description']);
            $new_surveyor_data['newitems'][$key]['qty']              = $item['qty'];
            $new_surveyor_data['newitems'][$key]['unit']             = $item['unit'];
            $new_surveyor_data['newitems'][$key]['taxname']          = [];
            $taxes                                                   = get_surveyor_item_taxes($item['id']);

            foreach ($taxes as $tax) {
                // tax name is in format TAX1|10.00
                array_push($new_surveyor_data['newitems'][$key]['taxname'], $tax['taxname']);
            }
            $new_surveyor_data['newitems'][$key]['rate']  = $item['rate'];
            $new_surveyor_data['newitems'][$key]['order'] = $item['item_order'];

            foreach ($custom_fields_items as $cf) {
                $new_surveyor_data['newitems'][$key]['custom_fields']['items'][$cf['id']] = get_custom_field_value($item['id'], $cf['id'], 'items', false);

                if (! defined('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST')) {
                    define('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST', true);
                }
            }
            $key++;
        }
        $id = $this->add($new_surveyor_data);
        if ($id) {
            $custom_fields = get_custom_fields('surveyor');

            foreach ($custom_fields as $field) {
                $value = get_custom_field_value($_surveyor->id, $field['id'], 'surveyor', false);
                if ($value == '') {
                    continue;
                }

                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $id,
                    'fieldid' => $field['id'],
                    'fieldto' => 'surveyor',
                    'value'   => $value,
                ]);
            }

            $tags = get_tags_in($_surveyor->id, 'surveyor');
            handle_tags_save($tags, $id, 'surveyor');

            log_activity('Copied Surveyor ' . format_surveyor_number($_surveyor->id));

            return $id;
        }

        return false;
    }

    /**
     * Performs surveyors totals status
     *
     * @param array $data
     *
     * @return array
     */
    public function get_surveyors_total($data)
    {
        $statuses            = $this->get_statuses();
        $has_permission_view = staff_can('view', 'surveyors');
        $this->load->model('currencies_model');
        if (isset($data['currency'])) {
            $currencyid = $data['currency'];
        } elseif (isset($data['surveyor_id']) && $data['surveyor_id'] != '') {
            $currencyid = $this->clients_model->get_surveyor_default_currency($data['surveyor_id']);
            if ($currencyid == 0) {
                $currencyid = $this->currencies_model->get_base_currency()->id;
            }
        } elseif (isset($data['project_id']) && $data['project_id'] != '') {
            $this->load->model('projects_model');
            $currencyid = $this->projects_model->get_currency($data['project_id'])->id;
        } else {
            $currencyid = $this->currencies_model->get_base_currency()->id;
        }

        $currency = get_currency($currencyid);
        $where    = '';
        if (isset($data['surveyor_id']) && $data['surveyor_id'] != '') {
            $where = ' AND clientid=' . $data['surveyor_id'];
        }

        if (isset($data['project_id']) && $data['project_id'] != '') {
            $where .= ' AND project_id=' . $data['project_id'];
        }

        if (! $has_permission_view) {
            $where .= ' AND ' . get_surveyors_where_sql_for_staff(get_staff_user_id());
        }

        $sql = 'SELECT';

        foreach ($statuses as $surveyor_status) {
            $sql .= '(SELECT SUM(total) FROM ' . db_prefix() . 'surveyors WHERE status=' . $surveyor_status;
            $sql .= ' AND currency =' . $this->db->escape_str($currencyid);
            if (isset($data['years']) && count($data['years']) > 0) {
                $sql .= ' AND YEAR(date) IN (' . implode(', ', array_map(function ($year) {
                    return get_instance()->db->escape_str($year);
                }, $data['years'])) . ')';
            } else {
                $sql .= ' AND YEAR(date) = ' . date('Y');
            }
            $sql .= $where;
            $sql .= ') as "' . $surveyor_status . '",';
        }

        $sql     = substr($sql, 0, -1);
        $result  = $this->db->query($sql)->result_array();
        $_result = [];
        $i       = 1;

        foreach ($result as $key => $val) {
            foreach ($val as $status => $total) {
                $_result[$i]['total']         = $total;
                $_result[$i]['symbol']        = $currency->symbol;
                $_result[$i]['currency_name'] = $currency->name;
                $_result[$i]['status']        = $status;
                $i++;
            }
        }
        $_result['currencyid'] = $currencyid;

        return $_result;
    }

    /**
     * Insert new surveyor to database
     *
     * @param array $data invoiec data
     *
     * @return mixed - false if not insert, surveyor ID if succes
     */
    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');

        $data['addedfrom'] = get_staff_user_id();

        $data['prefix'] = get_option('surveyor_prefix');

        $data['number_format'] = get_option('surveyor_number_format');

        $save_and_send = isset($data['save_and_send']);

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }

        $data['hash'] = app_generate_hash();
        $tags         = $data['tags'] ?? '';
        unset($data['tags']);

        // Default requestor to logged-in staff if not set
        if (empty($data['requestor_id'])) {
            $data['requestor_id'] = get_staff_user_id();
        }

        // Always use base currency — currency field not shown in form
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency()->id;

        // Unset removed/unused form fields
        unset($data['discount_type'], $data['discount_percent'], $data['discount_total']);

        foreach (_get_sales_feature_unused_names() as $name) {
            unset($data[$name]);
        }

        $equipment = [];
        if (isset($data['equipment'])) {
            $equipment = $data['equipment'];
            unset($data['equipment']);
        }

        $items = [];
        if (isset($data['newitems'])) {
            $items = $data['newitems'];
            unset($data['newitems']);
        }

        $data = $this->map_shipping_columns($data);

        $data['billing_street'] = trim($data['billing_street']);
        $data['billing_street'] = nl2br($data['billing_street']);

        if (isset($data['shipping_street'])) {
            $data['shipping_street'] = trim($data['shipping_street']);
            $data['shipping_street'] = nl2br($data['shipping_street']);
        }

        $hook = hooks()->apply_filters('before_surveyor_added', [
            'data'  => $data,
            'items' => $items,
        ]);

        $data  = $hook['data'];
        $items = $hook['items'];

        $this->db->insert(db_prefix() . 'surveyors', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            $this->save_formatted_number($insert_id);

            // Update next surveyor number in settings
            $this->db->where('name', 'next_surveyor_number');
            $this->db->set('value', 'value+1', false);
            $this->db->update(db_prefix() . 'options');



            if (isset($custom_fields)) {
                handle_custom_fields_post($insert_id, $custom_fields);
            }

            handle_tags_save($tags, $insert_id, 'surveyor');

            foreach ($items as $key => $item) {
                if ($itemid = add_new_sales_item_post($item, $insert_id, 'surveyor')) {
                    _maybe_insert_post_item_tax($itemid, $item, $insert_id, 'surveyor');
                }
            }

            $this->save_surveyor_equipment($insert_id, $equipment);
            $this->log_surveyor_activity($insert_id, 'surveyor_activity_created');

            hooks()->do_action('after_surveyor_added', $insert_id);

            if ($save_and_send === true) {
                $this->send_surveyor_to_client($insert_id, '', true, '', true);
            }

            return $insert_id;
        }

        return false;
    }

    public function get_surveyor_equipment($surveyor_id)
    {
        return $this->db
            ->select('re.surveyor_equipment_id, ce.unit_code, ce.serial_number, ce.location, ce.cert_expired_date, i.description as item_name')
            ->from(db_prefix() . 'surveyor_doc_equipment re')
            ->join(db_prefix() . 'surveyor_equipment ce', 'ce.id = re.surveyor_equipment_id')
            ->join(db_prefix() . 'items i', 'i.id = ce.item_id')
            ->where('re.surveyor_id', $surveyor_id)
            ->get()->result_array();
    }

    public function save_surveyor_equipment($surveyor_id, $equipment)
    {
        $this->db->where('surveyor_id', $surveyor_id)->delete(db_prefix() . 'surveyor_doc_equipment');
        if (empty($equipment)) { return; }
        foreach ($equipment as $row) {
            $eq_id = (int) ($row['surveyor_equipment_id'] ?? 0);
            if ($eq_id > 0) {
                $this->db->insert(db_prefix() . 'surveyor_doc_equipment', [
                    'surveyor_id'          => $surveyor_id,
                    'surveyor_equipment_id' => $eq_id,
                ]);
            }
        }
    }

    /**
     * Get item by id
     *
     * @param mixed $id item id
     *
     * @return object
     */
    public function get_surveyor_item($id)
    {
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'itemable')->row();
    }

    /**
     * Update surveyor data
     *
     * @param array $data surveyor data
     * @param mixed $id   surveyorid
     *
     * @return bool
     */
    public function update($data, $id)
    {
        $affectedRows = 0;

        $data['number'] = trim($data['number']);

        $original_surveyor = $this->get($id);

        $original_status = $original_surveyor->status;

        $original_number = $original_surveyor->number;

        $original_number_formatted = format_surveyor_number($id);

        $save_and_send = isset($data['save_and_send']);

        $equipment = [];
        if (isset($data['equipment'])) {
            $equipment = $data['equipment'];
            unset($data['equipment']);
        }

        $items = [];
        if (isset($data['items'])) {
            $items = $data['items'];
            unset($data['items']);
        }

        $newitems = [];
        if (isset($data['newitems'])) {
            $newitems = $data['newitems'];
            unset($data['newitems']);
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }

        if (isset($data['tags'])) {
            if (handle_tags_save($data['tags'], $id, 'surveyor')) {
                $affectedRows++;
            }
            unset($data['tags']);
        }

        // Removed columns — unset to prevent update errors
        unset($data['currency'], $data['discount_type'], $data['discount_percent'], $data['discount_total']);

        foreach (_get_sales_feature_unused_names() as $name) {
            unset($data[$name]);
        }

        $data['billing_street'] = trim($data['billing_street']);
        $data['billing_street'] = nl2br($data['billing_street']);

        $data['shipping_street'] = trim($data['shipping_street']);
        $data['shipping_street'] = nl2br($data['shipping_street']);

        $data = $this->map_shipping_columns($data);

        $hook = hooks()->apply_filters('before_surveyor_updated', [
            'data'          => $data,
            'items'         => $items,
            'newitems'      => $newitems,
            'removed_items' => $data['removed_items'] ?? [],
        ], $id);

        $data                  = $hook['data'];
        $items                 = $hook['items'];
        $newitems              = $hook['newitems'];
        $data['removed_items'] = $hook['removed_items'];

        // Delete items checked to be removed from database
        foreach ($data['removed_items'] as $remove_item_id) {
            $original_item = $this->get_surveyor_item($remove_item_id);
            if (handle_removed_sales_item_post($remove_item_id, 'surveyor')) {
                $affectedRows++;
                $this->log_surveyor_activity($id, 'surveyor_activity_removed_item', false, serialize([
                    $original_item->description,
                ]));
            }
        }

        unset($data['removed_items']);

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'surveyors', $data);

        $this->save_formatted_number($id);

        if ($this->db->affected_rows() > 0) {
            // Check for status change
            if ($original_status != $data['status']) {
                $this->log_surveyor_activity($original_surveyor->id, 'not_surveyor_status_updated', false, serialize([
                    '<original_status>' . $original_status . '</original_status>',
                    '<new_status>' . $data['status'] . '</new_status>',
                ]));
                if ($data['status'] == 2) {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'surveyors', ['sent' => 1, 'datesend' => date('Y-m-d H:i:s')]);
                }
            }
            if ($original_number != $data['number']) {
                $this->log_surveyor_activity($original_surveyor->id, 'surveyor_activity_number_changed', false, serialize([
                    $original_number_formatted,
                    format_surveyor_number($original_surveyor->id),
                ]));
            }
            $affectedRows++;
        }

        foreach ($items as $key => $item) {
            $item = array_merge($item, [
                'is_optional' => $isOptional = isset($item['is_optional']) ? 1 : 0,
                'is_selected' => ! $isOptional ? 1 : (isset($item['is_selected']) ? 1 : 0),
            ]);
            $original_item = $this->get_surveyor_item($item['itemid']);

            if (update_sales_item_post($item['itemid'], $item, 'item_order')) {
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'unit')) {
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'rate')) {
                $this->log_surveyor_activity($id, 'surveyor_activity_updated_item_rate', false, serialize([
                    $original_item->rate,
                    $item['rate'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'qty')) {
                $this->log_surveyor_activity($id, 'surveyor_activity_updated_qty_item', false, serialize([
                    $item['description'],
                    $original_item->qty,
                    $item['qty'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'description')) {
                $this->log_surveyor_activity($id, 'surveyor_activity_updated_item_short_description', false, serialize([
                    $original_item->description,
                    $item['description'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'long_description')) {
                $this->log_surveyor_activity($id, 'surveyor_activity_updated_item_long_description', false, serialize([
                    $original_item->long_description,
                    $item['long_description'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'is_optional')) {
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'is_selected')) {
                $affectedRows++;
            }

            if (isset($item['custom_fields'])) {
                if (handle_custom_fields_post($item['itemid'], $item['custom_fields'])) {
                    $affectedRows++;
                }
            }

            if (! isset($item['taxname']) || (isset($item['taxname']) && count($item['taxname']) == 0)) {
                if (delete_taxes_from_item($item['itemid'], 'surveyor')) {
                    $affectedRows++;
                }
            } else {
                $item_taxes        = get_surveyor_item_taxes($item['itemid']);
                $_item_taxes_names = [];

                foreach ($item_taxes as $_item_tax) {
                    array_push($_item_taxes_names, $_item_tax['taxname']);
                }

                $i = 0;

                foreach ($_item_taxes_names as $_item_tax) {
                    if (! in_array($_item_tax, $item['taxname'])) {
                        $this->db->where('id', $item_taxes[$i]['id'])
                            ->delete(db_prefix() . 'item_tax');
                        if ($this->db->affected_rows() > 0) {
                            $affectedRows++;
                        }
                    }
                    $i++;
                }
                if (_maybe_insert_post_item_tax($item['itemid'], $item, $id, 'surveyor')) {
                    $affectedRows++;
                }
            }
        }

        foreach ($newitems as $key => $item) {
            if ($new_item_added = add_new_sales_item_post($item, $id, 'surveyor')) {
                _maybe_insert_post_item_tax($new_item_added, $item, $id, 'surveyor');
                $this->log_surveyor_activity($id, 'surveyor_activity_added_item', false, serialize([
                    $item['description'],
                ]));
                $affectedRows++;
            }
        }

        $this->save_surveyor_equipment($id, $equipment);

        if ($save_and_send === true) {
            $this->send_surveyor_to_client($id, '', true, '', true);
        }

        if ($affectedRows > 0) {
            hooks()->do_action('after_surveyor_updated', $id);

            return true;
        }

        return false;
    }

    public function mark_action_status($action, $id, $client = false)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'surveyors', [
            'status' => $action,
        ]);

        $notifiedUsers = [];

        if ($this->db->affected_rows() > 0) {
            $surveyor = $this->get($id);
            if ($client == true) {
                $this->db->where('staffid', $surveyor->addedfrom);
                $this->db->or_where('staffid', $surveyor->sale_agent);
                $staff_surveyor = $this->db->get(db_prefix() . 'staff')->result_array();

                $quotationid = false;
                $quoted    = false;

                $contact_id = ! is_client_logged_in()
                    ? get_primary_contact_user_id($surveyor->clientid)
                    : get_contact_user_id();

                if ($action == 4) {
                    if (get_option('surveyor_auto_convert_to_quotation_on_client_accept') == 1) {
                        $quotationid = $this->convert_to_quotation($id);
                        if ($quotationid) {
                            $quoted = true;
                            $this->log_surveyor_activity($id, 'surveyor_activity_surveyor_accepted_and_converted', true, serialize([
                                '<a href="' . admin_url('quotations/list_quotations/' . $quotationid) . '">' . format_quotation_number($quotationid) . '</a>',
                            ]));
                        }
                    } else {
                        $this->log_surveyor_activity($id, 'surveyor_activity_surveyor_accepted', true);
                    }

                    // Send thank you email to all contacts with permission surveyors
                    $contacts = $this->clients_model->get_contacts($surveyor->clientid, ['active' => 1, 'surveyor_emails' => 1]);

                    foreach ($contacts as $contact) {
                        send_mail_template('surveyor_accepted_to_surveyor', 'surveyors', $surveyor, $contact);
                    }

                    foreach ($staff_surveyor as $member) {
                        $notified = add_notification([
                            'fromcompany'     => true,
                            'touserid'        => $member['staffid'],
                            'description'     => 'not_surveyor_surveyor_accepted',
                            'link'            => 'surveyors/list_surveyors/' . $id,
                            'additional_data' => serialize([
                                format_surveyor_number($surveyor->id),
                            ]),
                        ]);

                        if ($notified) {
                            array_push($notifiedUsers, $member['staffid']);
                        }

                        send_mail_template('surveyor_accepted_to_staff', 'surveyors', $surveyor, $member['email'], $contact_id);
                    }

                    pusher_trigger_notification($notifiedUsers);
                    hooks()->do_action('surveyor_accepted', $id);

                    return [
                        'quoted'      => $quoted,
                        'quotationid' => $quotationid,
                    ];
                }
                if ($action == 3) {
                    foreach ($staff_surveyor as $member) {
                        $notified = add_notification([
                            'fromcompany'     => true,
                            'touserid'        => $member['staffid'],
                            'description'     => 'not_surveyor_surveyor_declined',
                            'link'            => 'surveyors/list_surveyors/' . $id,
                            'additional_data' => serialize([
                                format_surveyor_number($surveyor->id),
                            ]),
                        ]);

                        if ($notified) {
                            array_push($notifiedUsers, $member['staffid']);
                        }
                        // Send staff email notification that surveyor declined surveyor
                        send_mail_template('surveyor_declined_to_staff', 'surveyors', $surveyor, $member['email'], $contact_id);
                    }

                    pusher_trigger_notification($notifiedUsers);
                    $this->log_surveyor_activity($id, 'surveyor_activity_surveyor_declined', true);
                    hooks()->do_action('surveyor_declined', $id);

                    return [
                        'quoted'      => $quoted,
                        'quotationid' => $quotationid,
                    ];
                }
            } else {
                if ($action == 2) {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'surveyors', ['sent' => 1, 'datesend' => date('Y-m-d H:i:s')]);
                }
                // Admin marked surveyor
                $this->log_surveyor_activity($id, 'surveyor_activity_marked', false, serialize([
                    '<status>' . $action . '</status>',
                ]));

                return true;
            }
        }

        return false;
    }

    /**
     * Get surveyor attachments
     *
     * @param mixed  $surveyor_id
     * @param string $id          attachment id
     *
     * @return mixed
     */
    public function get_attachments($surveyor_id, $id = '')
    {
        // If is passed id get return only 1 attachment
        if (is_numeric($id)) {
            $this->db->where('id', $id);
        } else {
            $this->db->where('rel_id', $surveyor_id);
        }
        $this->db->where('rel_type', 'surveyor');
        $result = $this->db->get(db_prefix() . 'files');
        if (is_numeric($id)) {
            return $result->row();
        }

        return $result->result_array();
    }

    /**
     *  Delete surveyor attachment
     *
     * @param mixed $id attachmentid
     *
     * @return bool
     */
    public function delete_attachment($id)
    {
        $attachment = $this->get_attachments('', $id);
        $deleted    = false;
        if ($attachment) {
            if (empty($attachment->external)) {
                unlink(get_upload_path_by_type('surveyor') . $attachment->rel_id . '/' . $attachment->file_name);
            }
            $this->db->where('id', $attachment->id);
            $this->db->delete(db_prefix() . 'files');
            if ($this->db->affected_rows() > 0) {
                $deleted = true;
                log_activity('Surveyor Attachment Deleted [SurveyorID: ' . $attachment->rel_id . ']');
            }

            if (is_dir(get_upload_path_by_type('surveyor') . $attachment->rel_id)) {
                // Check if no attachments left, so we can delete the folder also
                $other_attachments = list_files(get_upload_path_by_type('surveyor') . $attachment->rel_id);
                if (count($other_attachments) == 0) {
                    // okey only index.html so we can delete the folder also
                    delete_dir(get_upload_path_by_type('surveyor') . $attachment->rel_id);
                }
            }
        }

        return $deleted;
    }

    /**
     * Delete surveyor items and all connections
     *
     * @param mixed $id           surveyorid
     * @param mixed $simpleDelete
     *
     * @return bool
     */
    public function delete($id, $simpleDelete = false)
    {
        if (get_option('delete_only_on_last_surveyor') == 1 && $simpleDelete == false) {
            if (! is_last_surveyor($id)) {
                return false;
            }
        }
        $surveyor = $this->get($id);
        if (! is_null($surveyor->quotationid) && $simpleDelete == false) {
            return [
                'is_quoted_surveyor_delete_error' => true,
            ];
        }
        hooks()->do_action('before_surveyor_deleted', $id);

        $number = format_surveyor_number($id);

        $this->clear_signature($id);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'surveyors');

        if ($this->db->affected_rows() > 0) {
            if (! is_null($surveyor->short_link)) {
                app_archive_short_link($surveyor->short_link);
            }

            if (get_option('surveyor_number_decrement_on_delete') == 1 && $simpleDelete == false) {
                $current_next_surveyor_number = get_option('next_surveyor_number');
                if ($current_next_surveyor_number > 1) {
                    // Decrement next surveyor number to
                    $this->db->where('name', 'next_surveyor_number');
                    $this->db->set('value', 'value-1', false);
                    $this->db->update(db_prefix() . 'options');
                }
            }

            if (total_rows(db_prefix() . 'proposals', [
                'surveyor_id' => $id,
            ]) > 0) {
                $this->db->where('surveyor_id', $id);
                $surveyor = $this->db->get(db_prefix() . 'proposals')->row();
                $this->db->where('id', $surveyor->id);
                $this->db->update(db_prefix() . 'proposals', [
                    'surveyor_id'    => null,
                    'date_converted' => null,
                ]);
            }

            delete_tracked_emails($id, 'surveyor');

            $this->db->where('relid IN (SELECT id from ' . db_prefix() . 'itemable WHERE rel_type="surveyor" AND rel_id="' . $this->db->escape_str($id) . '")');
            $this->db->where('fieldto', 'items');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'surveyor');
            $this->db->delete(db_prefix() . 'notes');

            $this->db->where('rel_type', 'surveyor');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'views_tracking');

            $this->db->where('rel_type', 'surveyor');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'taggables');

            $this->db->where('rel_type', 'surveyor');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'reminders');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'surveyor');
            $this->db->delete(db_prefix() . 'itemable');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'surveyor');
            $this->db->delete(db_prefix() . 'item_tax');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'surveyor');
            $this->db->delete(db_prefix() . 'sales_activity');

            // Delete the custom field values
            $this->db->where('relid', $id);
            $this->db->where('fieldto', 'surveyor');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $attachments = $this->get_attachments($id);

            foreach ($attachments as $attachment) {
                $this->delete_attachment($attachment['id']);
            }

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'surveyor');
            $this->db->delete('scheduled_emails');


            if ($simpleDelete == false) {
                log_activity('Surveyors Deleted [Number: ' . $number . ']');
            }

            hooks()->do_action('after_surveyor_deleted', $id);

            return true;
        }

        return false;
    }

    public function save_formatted_number($id)
    {
        $formattedNumber = format_surveyor_number($id);

        $this->db->where('id', $id);
        $this->db->update('surveyors', ['formatted_number' => $formattedNumber]);
    }

    /**
     * Set surveyor to sent when email is successfuly sended to client
     *
     * @param mixed $id          surveyorid
     * @param mixed $emails_sent
     */
    public function set_surveyor_sent($id, $emails_sent = [])
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'surveyors', [
            'sent'     => 1,
            'datesend' => date('Y-m-d H:i:s'),
        ]);

        $this->log_surveyor_activity($id, 'surveyor_activity_sent_to_client', false, serialize([
            '<custom_data>' . implode(', ', $emails_sent) . '</custom_data>',
        ]));

        // Update surveyor status to sent
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'surveyors', [
            'status' => 2,
        ]);

        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'surveyor');
        $this->db->delete('scheduled_emails');
    }

    /**
     * Send expiration reminder to surveyor
     *
     * @param mixed $id surveyor id
     *
     * @return bool
     */
    public function send_expiry_reminder($id)
    {
        $surveyor        = $this->get($id);
        $surveyor_number = format_surveyor_number($surveyor->id);
        set_mailing_constant();
        $pdf              = surveyor_pdf($surveyor);
        $attach           = $pdf->Output($surveyor_number . '.pdf', 'S');
        $emails_sent      = [];
        $sms_sent         = false;
        $sms_reminder_log = [];

        // For all cases update this to prevent sending multiple reminders eq on fail
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'surveyors', [
            'is_expiry_notified' => 1,
        ]);

        $contacts = $this->clients_model->get_contacts($surveyor->clientid, ['active' => 1, 'surveyor_emails' => 1]);

        foreach ($contacts as $contact) {
            $template = mail_template('surveyor_expiration_reminder', 'surveyors', $surveyor, $contact);

            $merge_fields = $template->get_merge_fields();

            $template->add_attachment([
                'attachment' => $attach,
                'filename'   => str_replace('/', '-', $surveyor_number . '.pdf'),
                'type'       => 'application/pdf',
            ]);

            if ($template->send()) {
                array_push($emails_sent, $contact['email']);
            }

            if (can_send_sms_based_on_creation_date($surveyor->datecreated)
                && $this->app_sms->trigger(SMS_TRIGGER_SURVEYOR_EXP_REMINDER, $contact['phonenumber'], $merge_fields)) {
                $sms_sent = true;
                array_push($sms_reminder_log, $contact['firstname'] . ' (' . $contact['phonenumber'] . ')');
            }
        }

        if (count($emails_sent) > 0 || $sms_sent) {
            if (count($emails_sent) > 0) {
                $this->log_surveyor_activity($id, 'not_expiry_reminder_sent', false, serialize([
                    '<custom_data>' . implode(', ', $emails_sent) . '</custom_data>',
                ]));
            }

            if ($sms_sent) {
                $this->log_surveyor_activity($id, 'sms_reminder_sent_to', false, serialize([
                    implode(', ', $sms_reminder_log),
                ]));
            }

            return true;
        }

        return false;
    }

    /**
     * Send surveyor to client
     *
     * @param mixed  $id            surveyorid
     * @param string $template      email template to sent
     * @param bool   $attachpdf     attach surveyor pdf or not
     * @param mixed  $template_name
     * @param mixed  $cc
     * @param mixed  $manually
     *
     * @return bool
     */
    public function send_surveyor_to_client($id, $template_name = '', $attachpdf = true, $cc = '', $manually = false)
    {
        $surveyor = $this->get($id);

        if ($template_name == '') {
            $template_name = $surveyor->sent == 0 ?
                'surveyor_send_to_surveyor' :
                'surveyor_send_to_surveyor_already_sent';
        }

        $surveyor_number = format_surveyor_number($surveyor->id);

        $emails_sent = [];
        $send_to     = [];

        // Manually is used when sending the surveyor via add/edit area button Save & Send
        if (! defined('CRON') && $manually === false) {
            $send_to = $this->input->post('sent_to');
        } elseif (isset($GLOBALS['scheduled_email_contacts'])) {
            $send_to = $GLOBALS['scheduled_email_contacts'];
        } else {
            $contacts = $this->clients_model->get_contacts(
                $surveyor->clientid,
                ['active' => 1, 'surveyor_emails' => 1]
            );

            foreach ($contacts as $contact) {
                array_push($send_to, $contact['id']);
            }
        }

        $status_auto_updated = false;
        $status_now          = $surveyor->status;

        if (is_array($send_to) && count($send_to) > 0) {
            $i = 0;

            // Auto update status to sent in case when user sends the surveyor is with status draft
            if ($status_now == 1) {
                $this->db->where('id', $surveyor->id);
                $this->db->update(db_prefix() . 'surveyors', [
                    'status' => 2,
                ]);
                $status_auto_updated = true;
            }

            if ($attachpdf) {
                $_pdf_surveyor = $this->get($surveyor->id);
                set_mailing_constant();
                $pdf = surveyor_pdf($_pdf_surveyor);

                $attach = $pdf->Output($surveyor_number . '.pdf', 'S');
            }

            foreach ($send_to as $contact_id) {
                if ($contact_id != '') {
                    // Send cc only for the first contact
                    if (! empty($cc) && $i > 0) {
                        $cc = '';
                    }

                    $contact = $this->clients_model->get_contact($contact_id);

                    if (! $contact) {
                        continue;
                    }

                    $template = mail_template($template_name, 'surveyors', $surveyor, $contact, $cc);

                    if ($attachpdf) {
                        $hook = hooks()->apply_filters('send_surveyor_to_surveyor_file_name', [
                            'file_name' => str_replace('/', '-', $surveyor_number . '.pdf'),
                            'surveyor'  => $_pdf_surveyor,
                        ]);

                        $template->add_attachment([
                            'attachment' => $attach,
                            'filename'   => $hook['file_name'],
                            'type'       => 'application/pdf',
                        ]);
                    }

                    if ($template->send()) {
                        array_push($emails_sent, $contact->email);
                    }
                }
                $i++;
            }
        } else {
            return false;
        }

        if (count($emails_sent) > 0) {
            $this->set_surveyor_sent($id, $emails_sent);
            hooks()->do_action('surveyor_sent', $id);

            return true;
        }

        if ($status_auto_updated) {
            // Surveyor not send to surveyor but the status was previously updated to sent now we need to revert back to draft
            $this->db->where('id', $surveyor->id);
            $this->db->update(db_prefix() . 'surveyors', [
                'status' => 1,
            ]);
        }

        return false;
    }

    /**
     * All surveyor activity
     *
     * @param mixed $id surveyorid
     *
     * @return array
     */
    public function get_surveyor_activity($id)
    {
        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'surveyor');
        $this->db->order_by('date', 'asc');

        return $this->db->get(db_prefix() . 'sales_activity')->result_array();
    }

    /**
     * Log surveyor activity to database
     *
     * @param mixed  $id              surveyorid
     * @param string $description     activity description
     * @param mixed  $client
     * @param mixed  $additional_data
     */
    public function log_surveyor_activity($id, $description = '', $client = false, $additional_data = '')
    {
        $staffid   = get_staff_user_id();
        $full_name = get_staff_full_name(get_staff_user_id());
        if (defined('CRON')) {
            $staffid   = '[CRON]';
            $full_name = '[CRON]';
        } elseif ($client == true) {
            $staffid   = null;
            $full_name = '';
        }

        $this->db->insert(db_prefix() . 'sales_activity', [
            'description'     => $description,
            'date'            => date('Y-m-d H:i:s'),
            'rel_id'          => $id,
            'rel_type'        => 'surveyor',
            'staffid'         => $staffid,
            'full_name'       => $full_name,
            'additional_data' => $additional_data,
        ]);
    }

    /**
     * Updates pipeline order when drag and drop
     *
     * @param mixe $data $_POST data
     *
     * @return void
     */
    public function update_pipeline($data)
    {
        $this->mark_action_status($data['status'], $data['surveyorid']);
        AbstractKanban::updateOrder($data['order'], 'pipeline_order', 'surveyors', $data['status']);
    }

    /**
     * Get surveyor unique year for filtering
     *
     * @return array
     */
    public function get_surveyors_years()
    {
        return $this->db->query('SELECT DISTINCT(YEAR(date)) as year FROM ' . db_prefix() . 'surveyors ORDER BY year DESC')->result_array();
    }

    private function map_shipping_columns($data)
    {
        if (! isset($data['include_shipping'])) {
            foreach ($this->shipping_fields as $_s_field) {
                if (isset($data[$_s_field])) {
                    $data[$_s_field] = null;
                }
            }
            $data['show_shipping_on_surveyor'] = 1;
            $data['include_shipping']          = 0;
        } else {
            $data['include_shipping'] = 1;
            // set by default for the next time to be checked
            if (isset($data['show_shipping_on_surveyor']) && ($data['show_shipping_on_surveyor'] == 1 || $data['show_shipping_on_surveyor'] == 'on')) {
                $data['show_shipping_on_surveyor'] = 1;
            } else {
                $data['show_shipping_on_surveyor'] = 0;
            }
        }

        return $data;
    }

    public function do_kanban_query($status, $search = '', $page = 1, $sort = [], $count = false)
    {
        _deprecated_function('Surveyors_model::do_kanban_query', '2.9.2', 'SurveyorsPipeline class');

        $kanBan = (new SurveyorsPipeline($status))
            ->search($search)
            ->page($page)
            ->sortBy($sort['sort'] ?? null, $sort['sort_by'] ?? null);

        if ($count) {
            return $kanBan->countAll();
        }

        return $kanBan->get();
    }
}
