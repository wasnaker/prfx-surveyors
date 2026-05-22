<?php

use app\services\surveyors\SurveyorsPipeline;

defined('BASEPATH') or exit('No direct script access allowed');

class Surveyors extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('surveyors_model');
    }

    /* Get all surveyors in case user go on index page */
    public function index($id = '')
    {
        $this->list_surveyors($id);
    }

    /* List all surveyors datatables */
    public function list_surveyors($id = '')
    {
        if (staff_cant('view', 'surveyors') && staff_cant('view_own', 'surveyors') && get_option('allow_staff_view_surveyors_assigned') == '0') {
            access_denied('surveyors');
        }

        $isPipeline = $this->session->userdata('surveyor_pipeline') == 'true';

        $data['surveyor_statuses'] = $this->surveyors_model->get_statuses();
        $data['surveyors_table'] = App_table::find('surveyors');
        
        if ($isPipeline && !$this->input->get('status') && !$this->input->get('filter')) {
            $data['title']           = _l('surveyors_pipeline');
            $data['bodyclass']       = 'surveyors-pipeline surveyors-total-manual';
            $data['switch_pipeline'] = false;

            if (is_numeric($id)) {
                $data['surveyorid'] = $id;
            } else {
                $data['surveyorid'] = $this->session->flashdata('surveyorid');
            }

            $this->load->view('admin/surveyors/pipeline/manage', $data);
        } else {

            // Pipeline was initiated but user click from home page and need to show table only to filter
            if ($this->input->get('status') || $this->input->get('filter') && $isPipeline) {
                $this->pipeline(0, true);
            }

            $data['surveyorid']            = $id;
            $data['switch_pipeline']       = true;
            $data['title']                 = _l('surveyors');
            $data['bodyclass']             = 'surveyors-total-manual';
            $data['surveyors_years'] = $this->surveyors_model->get_surveyors_years();
        
            $this->load->view('admin/surveyors/manage', $data);
        }
    }

    public function table()
    {
        if (!$this->input->is_ajax_request()) { ajax_access_denied(); }

        if (staff_cant('view', 'surveyors') && staff_cant('view_own', 'surveyors')) {
            ajax_access_denied();
        }

        App_table::find('surveyors')->output([]);
    }

    public function search_surveyors()
    {
        if (!$this->input->is_ajax_request()) { ajax_access_denied(); }

        $client_id = (int) $this->input->get_post('client_id');
        $q         = $this->input->get_post('q');

        $this->db
            ->select('c.userid as id, c.company as name')
            ->from(db_prefix() . 'clients c');

        if ($client_id) {
            $this->db
                ->join(db_prefix() . 'client_connections cc',
                    '(cc.client_id_a = ' . $client_id . ' AND cc.client_id_b = c.userid)
                    OR (cc.client_id_b = ' . $client_id . ' AND cc.client_id_a = c.userid)')
                ->where('cc.status', 'active');
        }

        $this->db->where('c.client_type', 'surveyor');

        if ($q) {
            $this->db->like('c.company', $q);
        }

        echo json_encode($this->db->get()->result_array());
    }

    public function get_equipment_data($id = '')
    {
        if (!$this->input->is_ajax_request()) { ajax_access_denied(); }
        if (staff_cant('view', 'surveyors')) { ajax_access_denied(); }

        $id = (int) $id;
        $row = $this->db
            ->select('ce.id, ce.unit_code, ce.serial_number, ce.location, ce.cert_expired_date, i.description as item_name')
            ->from(db_prefix() . 'surveyor_equipment ce')
            ->join(db_prefix() . 'items i', 'i.id = ce.item_id')
            ->where('ce.id', $id)
            ->get()->row_array();

        echo json_encode($row ?: []);
    }

    public function add_edit_surveyor()
    {
        if (!$this->input->post()) {
            redirect(admin_url('surveyors'));
        }

        $postData = $this->input->post();

        $map_lat  = $this->input->post('map_latitude');
        $map_lng  = $this->input->post('map_longitude');
        $map_addr = $this->input->post('map_address') ?? '';

        foreach (['isedit', 'save_and_send_later', 'userid', 'legal_docs',
                  'map_latitude', 'map_longitude', 'map_address'] as $_strip) {
            unset($postData[$_strip]);
        }

        if (!empty($this->input->post('userid'))) {
            $id  = (int) $this->input->post('userid');
            if (!can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
                access_denied('surveyors');
            }

            $vat = isset($postData['vat']) ? $this->surveyors_model->normalize_tax_number($postData['vat']) : '';
            if ($vat !== '' && !$this->surveyors_model->is_vat_available($vat, $id)) {
                set_alert('danger', _l('vat_already_exists'));
                redirect(admin_url('surveyors/surveyor/' . $id));
                return;
            }

            // Snapshot old values before update
            $old = $this->db->get_where(db_prefix() . 'clients', ['userid' => $id])->row_array();

            $success = $this->surveyors_model->update($id, $postData);
            if ($success) {
                $diff = $this->surveyors_model->build_diff($old, $postData, [
                    'company'     => _l('client_company'),
                    'vat'         => _l('client_vat_number'),
                    'phonenumber' => _l('client_phonenumber'),
                    'website'     => _l('client_website'),
                    'address'     => _l('client_address'),
                    'state'       => _l('client_state'),
                    'city'        => _l('client_city'),
                    'zip'         => _l('client_postal_code'),
                ]);
                $this->surveyors_model->log_activity($id, 'surveyor_activity_updated', $diff);
                set_alert('success', _l('surveyor_saved'));
            }
            if ($map_lat !== false && $map_lat !== '' && $map_lng !== false && $map_lng !== '') {
                save_entity_coordinates((int) $id, 'surveyor', (float) $map_lat, (float) $map_lng, (string) $map_addr);
            }
            $this->_handle_entity_files($id);
            $this->_handle_legal_docs($id);
            redirect(admin_url('surveyors/list_surveyors/' . $id));

        } else {
            if (staff_cant('create', 'surveyors')) {
                access_denied('surveyors');
            }

            $vat = isset($postData['vat']) ? $this->surveyors_model->normalize_tax_number($postData['vat']) : '';
            if ($vat !== '' && !$this->surveyors_model->is_vat_available($vat)) {
                set_alert('danger', _l('vat_already_exists'));
                redirect(admin_url('surveyors/surveyor'));
                return;
            }

            $postData['client_type'] = 'surveyor';
            $id = $this->surveyors_model->add($postData);
            if ($id) {
                $this->_handle_entity_files((int) $id);
                $this->surveyors_model->log_activity((int) $id, 'surveyor_activity_created', $postData['company'] ?? '');
                if ($map_lat !== false && $map_lat !== '' && $map_lng !== false && $map_lng !== '') {
                    save_entity_coordinates((int) $id, 'surveyor', (float) $map_lat, (float) $map_lng, (string) $map_addr);
                }
                set_alert('success', _l('surveyor_saved'));
                redirect(admin_url('surveyors/list_surveyors/' . $id));
            } else {
                redirect(admin_url('surveyors/surveyor'));
            }
        }
    }

    /* Add new surveyor or update existing */
    public function surveyor($id = '')
    {
        if ($this->input->post()) {
            $surveyor_data = $this->input->post();

            // Strip fields not in tblclients
            foreach (['isedit', 'save_and_send_later', 'userid'] as $_strip) {
                unset($surveyor_data[$_strip]);
            }

            $save_and_send_later = false;

            if ($id == '') {
                $me = get_staff(get_staff_user_id());
                if (staff_cant('create', 'surveyors')) {
                    access_denied('surveyors');
                }
                $id = $this->surveyors_model->add($surveyor_data);

                if ($id) {
                    set_alert('success', _l('added_successfully', _l('surveyor')));

                    $redUrl = admin_url('surveyors/list_surveyors/' . $id);

                    if ($save_and_send_later) {
                        $this->session->set_userdata('send_later', true);
                        // die(redirect($redUrl));
                    }

                    redirect(
                        !$this->set_surveyor_pipeline_autoload($id) ? $redUrl : admin_url('surveyors/list_surveyors/')
                    );
                }
            } else {
                if (!can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
                    access_denied('surveyors');
                }
                $success = $this->surveyors_model->update($id, $surveyor_data);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('surveyor')));
                }
                if ($this->set_surveyor_pipeline_autoload($id)) {
                    redirect(admin_url('surveyors/list_surveyors/'));
                } else {
                    redirect(admin_url('surveyors/list_surveyors/' . $id));
                }
            }
        }
        if ($id == '') {
            if (staff_cant('create', 'surveyors')) {
                access_denied('surveyors');
            }
            $title = _l('create_new_surveyor');
        } else {
            if (!can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
                access_denied('surveyors');
            }

            $surveyor = $this->surveyors_model->get($id);

            if (!$surveyor || !user_can_view_surveyor($id)) {
                log_message('error', '[surveyor()] id=' . $id
                    . ' surveyor=' . ($surveyor ? 'found' : 'null')
                    . ' can_view=' . (user_can_view_surveyor($id) ? 'true' : 'false')
                    . ' uri=' . $this->uri->uri_string());
                blank_page(_l('surveyor_not_found'));
            }

            $data['surveyor'] = $surveyor;
            $data['client']   = $surveyor;
            $data['edit']     = true;
            $title            = _l('edit', _l('surveyor'));
        }

        if ($this->input->get('surveyor_id')) {
            $data['surveyor_id'] = $this->input->get('surveyor_id');
        } elseif (!isset($data['surveyor'])) {
            // Auto-fill client_id from logged-in surveyor staff
            $logged = $this->db->get_where(db_prefix() . 'staff', ['staffid' => get_staff_user_id()])->row();
            if ($logged && !empty($logged->client_id)) {
                $data['surveyor_id'] = (int) $logged->client_id;
            }
        }

        // Pre-load equipment units from bulk select (copy-estimate pattern)
        $preset_equipment = [];
        $ceids_raw = $this->input->get('surveyor_equipment_ids');
        if (!empty($ceids_raw)) {
            $ceids = array_filter(array_map('intval', explode(',', $ceids_raw)));
            if (!empty($ceids)) {
                $preset_equipment = $this->db
                    ->select('ce.id, ce.unit_code, ce.serial_number, ce.location, ce.cert_expired_date, i.description as item_name')
                    ->from(db_prefix() . 'surveyor_equipment ce')
                    ->join(db_prefix() . 'items i', 'i.id = ce.item_id')
                    ->where_in('ce.id', $ceids)
                    ->get()->result_array();
            }
        }
        $data['preset_equipment'] = $preset_equipment;

        $this->load->model('currencies_model');
        $data['base_currency'] = $this->currencies_model->get_base_currency();

        // Load requestor options: same company staff if surveyor, all active staff if admin
        $logged_staff = $this->db->get_where(db_prefix() . 'staff', ['staffid' => get_staff_user_id()])->row();
        $client_id    = ($logged_staff && !empty($logged_staff->client_id)) ? (int) $logged_staff->client_id : 0;
        if ($client_id) {
            $data['requestor_staff'] = $this->db
                ->where('client_id', $client_id)
                ->where('active', 1)
                ->get(db_prefix() . 'staff')->result_array();
        } else {
            $data['requestor_staff'] = $this->staff_model->get('', ['active' => 1]);
        }

        $this->load->model('invoice_items_model');

        $data['ajaxItems'] = false;
        if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
            $data['items'] = $this->invoice_items_model->get_grouped();
        } else {
            $data['items']     = [];
            $data['ajaxItems'] = true;
        }
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $data['staff']             = $this->staff_model->get('', ['active' => 1]);
        $data['surveyor_statuses'] = $this->surveyors_model->get_statuses();
        $data['title']             = $title;

        // Load legal docs keyed by doc_type
        $data['legal_docs'] = [];
        if (isset($data['client'])) {
            $rows = $this->db
                ->where('client_id', (int) $data['client']->userid)
                ->where('client_type', 'surveyor')
                ->get(db_prefix() . 'client_legal_docs')->result();
            foreach ($rows as $row) {
                $data['legal_docs'][$row->doc_type] = $row;
            }
        }

        $this->load->view('admin/surveyors/surveyor', $data);
    }

    // ── File uploads: logo + legal docs ──────────────────────────────────────

    private function _sanitize_filename(string $name): string
    {
        $name = sanitize_file_name($name); // Perfex: strip dangerous chars
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = strtolower(pathinfo($name, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9_\-]/', '_', $base); // spaces → underscore
        $base = trim($base, '_');
        return ($base ?: 'file') . '.' . $ext;
    }

    private function _handle_entity_files(int $id)
    {
        $logo_path = FCPATH . 'uploads/client_logos/' . $id . '/';
        _maybe_create_upload_path($logo_path);

        foreach (['logo_light', 'logo_dark'] as $field) {
            if (empty($_FILES[$field]['name'])) { continue; }
            if (_perfex_upload_error($_FILES[$field]['error'])) { continue; }
            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) { continue; }
            $old = $this->db->select($field)->get_where(db_prefix() . 'clients', ['userid' => $id])->row();
            if ($old && !empty($old->$field) && file_exists($logo_path . $old->$field)) {
                unlink($logo_path . $old->$field);
            }
            $filename = unique_filename($logo_path, $this->_sanitize_filename($_FILES[$field]['name']));
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $logo_path . $filename)) {
                $this->db->where('userid', $id)->update(db_prefix() . 'clients', [$field => $filename]);
            }
        }

        // npwp_file moved to tblclient_legal_docs — handled by _handle_legal_docs()
    }

    private function _handle_legal_docs(int $id)
    {
        $allowed_types = [
            'nib', 'npwp',
            'akte_pendirian', 'akte_pendirian_sk',
            'akte_perubahan', 'akte_perubahan_sk',
            'bpjs_tk', 'bpjs_kes',
        ];
        _maybe_create_upload_path(CLIENT_ATTACHMENTS_FOLDER . $id . '/');
        $doc_path = CLIENT_ATTACHMENTS_FOLDER . $id . '/legal_docs/';
        _maybe_create_upload_path($doc_path);

        $submitted = $this->input->post('legal_docs') ?: [];

        // only notary_name stays in meta — sk moved to own doc_type
        $meta_types = ['akte_pendirian', 'akte_perubahan'];

        foreach ($allowed_types as $doc_type) {
            $number   = isset($submitted[$doc_type]['number'])
                ? trim($submitted[$doc_type]['number'])
                : null;
            $file_key = 'legal_doc_file_' . $doc_type;

            $meta = null;
            if (in_array($doc_type, $meta_types)) {
                $notary_name = trim($submitted[$doc_type]['notary_name'] ?? '');
                if ($notary_name !== '') {
                    $meta = json_encode(['notary_name' => $notary_name]);
                }
            }

            $existing = $this->db
                ->where('client_id',   $id)
                ->where('client_type', 'surveyor')
                ->where('doc_type',    $doc_type)
                ->get(db_prefix() . 'client_legal_docs')->row();

            $new_file = null;
            if (!empty($_FILES[$file_key]['name']) && !_perfex_upload_error($_FILES[$file_key]['error'])) {
                $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','pdf'])) {
                    if ($existing && !empty($existing->file) && file_exists($doc_path . $existing->file)) {
                        unlink($doc_path . $existing->file);
                    }
                    $filename = unique_filename($doc_path, $this->_sanitize_filename($_FILES[$file_key]['name']));
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $doc_path . $filename)) {
                        $new_file = $filename;
                    }
                }
            }

            if ($existing) {
                $update = ['doc_number' => $number ?: null, 'updated_at' => date('Y-m-d H:i:s')];
                if ($new_file !== null) { $update['file'] = $new_file; }
                if ($meta !== null)     { $update['meta'] = $meta; }
                $this->db->where('id', $existing->id)->update(db_prefix() . 'client_legal_docs', $update);
            } else {
                if ($number !== '' || $new_file !== null || $meta !== null) {
                    $this->db->insert(db_prefix() . 'client_legal_docs', [
                        'client_id'   => $id,
                        'client_type' => 'surveyor',
                        'doc_type'    => $doc_type,
                        'doc_number'  => $number ?: null,
                        'file'        => $new_file,
                        'meta'        => $meta,
                        'addedfrom'   => get_staff_user_id(),
                        'datecreated' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function serve_legal_doc($id, $doc_type)
    {
        $id = (int) $id;
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }
        $row  = $this->db
            ->where('client_id', $id)->where('client_type', 'surveyor')->where('doc_type', $doc_type)
            ->get(db_prefix() . 'client_legal_docs')->row();
        $path = CLIENT_ATTACHMENTS_FOLDER . $id . '/legal_docs/' . ($row->file ?? '');
        if (!$row || empty($row->file) || !file_exists($path)) { show_404(); }
        $mime = mime_content_type($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function download_legal_doc($id, $doc_type)
    {
        $id = (int) $id;
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }
        $row  = $this->db
            ->where('client_id', $id)->where('client_type', 'surveyor')->where('doc_type', $doc_type)
            ->get(db_prefix() . 'client_legal_docs')->row();
        $path = CLIENT_ATTACHMENTS_FOLDER . $id . '/legal_docs/' . ($row->file ?? '');
        if (!$row || empty($row->file) || !file_exists($path)) { show_404(); }
        force_download($path, null, true);
    }

    public function delete_legal_doc($id, $doc_type)
    {
        $id = (int) $id;
        if (!can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }
        $row  = $this->db
            ->where('client_id', $id)->where('client_type', 'surveyor')->where('doc_type', $doc_type)
            ->get(db_prefix() . 'client_legal_docs')->row();
        if ($row && !empty($row->file)) {
            $path = CLIENT_ATTACHMENTS_FOLDER . $id . '/legal_docs/' . $row->file;
            if (file_exists($path)) { unlink($path); }
        }
        if ($row) {
            $this->db->where('id', $row->id)->update(db_prefix() . 'client_legal_docs', ['file' => null]);
        }
        redirect(admin_url('surveyors/surveyor/' . $id . '?tab=legal-docs'));
    }

    public function serve_npwp($id)
    {
        redirect(admin_url('surveyors/serve_legal_doc/' . (int)$id . '/npwp'));
    }

    public function download_npwp($id)
    {
        redirect(admin_url('surveyors/download_legal_doc/' . (int)$id . '/npwp'));
    }

    public function delete_npwp($id)
    {
        redirect(admin_url('surveyors/delete_legal_doc/' . (int)$id . '/npwp'));
    }

    public function upload_logo($id, $type = 'light')
    {
        header('Content-Type: application/json');
        if (!can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); die();
        }
        $id  = (int) $id;
        $col = 'logo_' . (in_array($type, ['light', 'dark']) ? $type : 'light');

        if (empty($_FILES['file']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file received']); die();
        }
        if (_perfex_upload_error($_FILES['file']['error'])) {
            echo json_encode(['success' => false, 'message' => _perfex_upload_error($_FILES['file']['error'])]); die();
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo json_encode(['success' => false, 'message' => 'Extension not allowed.']); die();
        }
        $path = FCPATH . 'uploads/client_logos/' . $id . '/';
        _maybe_create_upload_path($path);
        $old = $this->db->select($col)->get_where(db_prefix() . 'clients', ['userid' => $id])->row();
        if ($old && !empty($old->$col) && file_exists($path . $old->$col)) {
            unlink($path . $old->$col);
        }
        $filename = unique_filename($path, $this->_sanitize_filename($_FILES['file']['name']));
        if (move_uploaded_file($_FILES['file']['tmp_name'], $path . $filename)) {
            $this->db->where('userid', $id)->update(db_prefix() . 'clients', [$col => $filename]);
            echo json_encode(['success' => true, 'url' => base_url('uploads/client_logos/' . $id . '/' . $filename)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
        }
        die();
    }

    public function delete_logo($id, $type = 'light')
    {
        if (!can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }
        $id  = (int) $id;
        $col = 'logo_' . (in_array($type, ['light', 'dark']) ? $type : 'light');
        $row  = $this->db->select($col)->get_where(db_prefix() . 'clients', ['userid' => $id])->row();
        $path = FCPATH . 'uploads/client_logos/' . $id . '/';
        if ($row && !empty($row->$col) && file_exists($path . $row->$col)) {
            unlink($path . $row->$col);
        }
        $this->db->where('userid', $id)->update(db_prefix() . 'clients', [$col => null]);
        redirect(admin_url('surveyors/surveyor/' . $id . '?tab=general'));
    }

    public function clear_signature($id)
    {
        if (staff_can('delete',  'surveyors')) {
            $this->surveyors_model->clear_signature($id);
        }

        redirect(admin_url('surveyors/list_surveyors/' . $id));
    }

    public function update_number_settings($id)
    {
        $response = [
            'success' => false,
            'message' => '',
        ];
        
        if (staff_can('edit',  'surveyors')) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'surveyors', [
                'prefix' => $this->input->post('prefix'),
            ]);

            if ($this->db->affected_rows() > 0) {
                $this->surveyors_model->save_formatted_number($id);

                $response['success'] = true;
                $response['message'] = _l('updated_successfully', _l('surveyor'));
            }
        }

        echo json_encode($response);
        die;
    }

    public function validate_surveyor_number()
    {
        $isedit          = $this->input->post('isedit');
        $number          = $this->input->post('number');
        $date            = $this->input->post('date');
        $original_number = $this->input->post('original_number');
        $number          = trim($number);
        $number          = ltrim($number, '0');

        if ($isedit == 'true') {
            if ($number == $original_number) {
                echo json_encode(true);
                die;
            }
        }

        if (total_rows(db_prefix() . 'surveyors', [
            'YEAR(date)' => date('Y', strtotime(to_sql_date($date))),
            'number' => $number,
        ]) > 0) {
            echo 'false';
        } else {
            echo 'true';
        }
    }

    public function delete_attachment($id)
    {
        $file = $this->misc_model->get_file($id);
        if ($file->staffid == get_staff_user_id() || is_admin()) {
            echo $this->surveyors_model->delete_attachment($id);
        } else {
            header('HTTP/1.0 400 Bad error');
            echo _l('access_denied');
            die;
        }
    }

    /* Get all surveyor data used when user click on surveyor number in a datatable left side*/
    public function get_surveyor_data_ajax($id)
    {
        if (!$this->input->is_ajax_request()) {
            redirect(admin_url('surveyors'));
        }

        $is_own = $this->_is_own_entity((int) $id, 'surveyor');
        if (staff_cant('view', 'surveyors') && !$is_own) {
            ajax_access_denied();
        }

        $can_view = hooks()->apply_filters('can_view_surveyor_profile', true, (int) $id);
        if (!$can_view) {
            $this->load->view('surveyors/admin/surveyors/not_connected');
            return;
        }

        if (!$id || !is_numeric($id)) {
            show_404();
        }

        $data['surveyor'] = $this->db->get_where(db_prefix() . 'clients', ['userid' => $id])->row();
        if (!$data['surveyor']) {
            show_404();
        }

        $country = null;
        if (!empty($data['surveyor']->country)) {
            $country = $this->db->get_where(db_prefix() . 'countries', ['country_id' => $data['surveyor']->country])->row();
        }
        $data['country_name'] = $country ? $country->short_name : '';
        $data['branches']     = $this->surveyors_model->get_branches($id);
        $data['is_own']       = $is_own && staff_cant('view', 'surveyors');
        $data['totalNotes']   = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'surveyor']);
        $data['members']      = $this->staff_model->get('', ['active' => 1]);
        $data['activity']     = $this->surveyors_model->get_activity($id);

        $rows = $this->db
            ->where('client_id', (int)$id)
            ->where('client_type', 'surveyor')
            ->get(db_prefix() . 'client_legal_docs')->result();
        $data['legal_docs'] = [];
        foreach ($rows as $row) {
            $data['legal_docs'][$row->doc_type] = $row;
        }

        $data['coordinates'] = get_entity_coordinates((int) $id, 'surveyor');

        $this->load->view('admin/surveyors/surveyor_preview_template', $data);
    }

    public function get_surveyors_total()
    {
        if ($this->input->post()) {
            $data['totals'] = $this->surveyors_model->get_surveyors_total($this->input->post());

            $this->load->model('currencies_model');

            if (!$this->input->post('surveyor_id')) {
                $multiple_currencies = call_user_func('is_using_multiple_currencies', db_prefix() . 'surveyors');
            } else {
                $multiple_currencies = call_user_func('is_client_using_multiple_currencies', $this->input->post('surveyor_id'), db_prefix() . 'surveyors');
            }

            if ($multiple_currencies) {
                $data['currencies'] = $this->currencies_model->get();
            }

            $data['surveyors_years'] = $this->surveyors_model->get_surveyors_years();

            if (
                count($data['surveyors_years']) >= 1
                && !\app\services\utilities\Arr::inMultidimensional($data['surveyors_years'], 'year', date('Y'))
            ) {
                array_unshift($data['surveyors_years'], ['year' => date('Y')]);
            }

            $data['_currency'] = $data['totals']['currencyid'];
            unset($data['totals']['currencyid']);
            $this->load->view('admin/surveyors/surveyors_total_template', $data);
        }
    }

    public function add_note($rel_id)
    {
        if ($this->input->post() && user_can_view_surveyor($rel_id)) {
            $this->misc_model->add_note($this->input->post(), 'surveyor', $rel_id);
            echo $rel_id;
        }
    }

    public function get_notes($id)
    {
        if (user_can_view_surveyor($id)) {
            $data['notes'] = $this->misc_model->get_notes($id, 'surveyor');
            $this->load->view('admin/includes/sales_notes_template', $data);
        }
    }

    public function mark_action_status($status, $id)
    {
        if (staff_cant('mark_as', 'surveyors')) {
            access_denied('surveyors');
        }
        $success = $this->surveyors_model->mark_action_status($status, $id);

        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'success'     => (bool) $success,
                'message'     => $success ? _l('surveyor_status_changed_success') : _l('surveyor_status_changed_fail'),
                'status_html' => $success ? format_surveyor_status($status, 'mtop5 inline-block') : '',
            ]);
            return;
        }

        if ($success) {
            set_alert('success', _l('surveyor_status_changed_success'));
        } else {
            set_alert('danger', _l('surveyor_status_changed_fail'));
        }
        if ($this->set_surveyor_pipeline_autoload($id)) {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }
    }

    public function send_expiry_reminder($id)
    {
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }

        $success = $this->surveyors_model->send_expiry_reminder($id);
        if ($success) {
            set_alert('success', _l('sent_expiry_reminder_success'));
        } else {
            set_alert('danger', _l('sent_expiry_reminder_fail'));
        }
        if ($this->set_surveyor_pipeline_autoload($id)) {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }
    }

    /* Send surveyor to email */
    public function send_to_email($id)
    {
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }

        try {
            $success = $this->surveyors_model->send_surveyor_to_client($id, '', $this->input->post('attach_pdf'), $this->input->post('cc'));
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        // In case client use another language
        load_admin_language();
        if ($success) {
            set_alert('success', _l('surveyor_sent_to_client_success'));
        } else {
            set_alert('danger', _l('surveyor_sent_to_client_fail'));
        }
        if ($this->set_surveyor_pipeline_autoload($id)) {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }
    }

    /* Convert SURVEYOR to Quotation (surveyor adds prices per equipment item) */
    public function convert_to_quotation($id)
    {
        if (staff_cant('convert_to_quotation', 'surveyors')) {
            access_denied('surveyors');
        }
        // Only allowed when SURVEYOR is Sent
        $_surveyor_cq = $this->surveyors_model->get((int)$id);
        if (!$_surveyor_cq || (int)$_surveyor_cq->status !== SURVEYOR_STATUS_SENT) {
            set_alert('danger', _l('surveyor_convert_to_quotation_invalid_status'));
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }
        if (!$id) {
            redirect(admin_url('surveyors'));
        }
        $quotation_id = $this->surveyors_model->convert_to_quotation($id);
        if ($quotation_id) {
            set_alert('success', _l('surveyor_converted_to_quotation_successfully'));
            redirect(admin_url('quotations/quotation/' . $quotation_id));
        } else {
            set_alert('danger', _l('surveyor_converted_to_quotation_failed'));
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }
    }

    public function copy($id)
    {
        if (staff_cant('create', 'surveyors')) {
            access_denied('surveyors');
        }
        if (!$id) {
            die('No surveyor found');
        }
        $new_id = $this->surveyors_model->copy($id);
        if ($new_id) {
            set_alert('success', _l('surveyor_copied_successfully'));
            if ($this->set_surveyor_pipeline_autoload($new_id)) {
                redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
            } else {
                redirect(admin_url('surveyors/surveyor/' . $new_id));
            }
        }
        set_alert('danger', _l('surveyor_copied_fail'));
        if ($this->set_surveyor_pipeline_autoload($id)) {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('surveyors/surveyor/' . $id));
        }
    }

    /* Delete surveyor */
    public function delete($id)
    {
        if (staff_cant('delete', 'surveyors')) {
            access_denied('surveyors');
        }
        if (!$id) {
            redirect(admin_url('surveyors/list_surveyors'));
        }
        $success = $this->surveyors_model->delete($id);
        if (is_array($success)) {
            set_alert('warning', _l('is_quoted_surveyor_delete_error'));
        } elseif ($success == true) {
            set_alert('success', _l('deleted', _l('surveyor')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('surveyor_lowercase')));
        }
        redirect(admin_url('surveyors/list_surveyors'));
    }

    public function clear_acceptance_info($id)
    {
        if (is_admin()) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'surveyors', get_acceptance_info_array(true));
        }

        redirect(admin_url('surveyors/list_surveyors/' . $id));
    }

    /* Generates surveyor PDF and senting to email  */
    public function pdf($id)
    {
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }
        if (!$id) {
            redirect(admin_url('surveyors/list_surveyors'));
        }
        $surveyor        = $this->surveyors_model->get($id);
        $surveyor_number = e($surveyor->company);

        try {
            $pdf = surveyor_pdf($surveyor);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $fileNameHookData = hooks()->apply_filters('surveyor_file_name_admin_area', [
                            'file_name' => mb_strtoupper(slug_it($surveyor_number)) . '.pdf',
                            'surveyor'  => $surveyor,
                        ]);

        $pdf->Output($fileNameHookData['file_name'], $type);
    }

    // Pipeline
    public function get_pipeline()
    {
        if (staff_can('view',  'surveyors') || staff_can('view_own',  'surveyors') || get_option('allow_staff_view_surveyors_assigned') == '1') {
            $data['surveyor_statuses'] = $this->surveyors_model->get_statuses();
            $this->load->view('admin/surveyors/pipeline/pipeline', $data);
        }
    }

    public function pipeline_open($id)
    {
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            access_denied('surveyors');
        }

        $data['id']       = $id;
        $data['surveyor'] = $this->get_surveyor_data_ajax($id, true);
        $this->load->view('admin/surveyors/pipeline/surveyor', $data);
    }

    public function update_pipeline()
    {
        if (staff_can('edit',  'surveyors')) {
            $this->surveyors_model->update_pipeline($this->input->post());
        }
    }

    public function pipeline($set = 0, $manual = false)
    {
        if ($set == 1) {
            $set = 'true';
        } else {
            $set = 'false';
        }
        $this->session->set_userdata([
            'surveyor_pipeline' => $set,
        ]);
        if ($manual == false) {
            redirect(admin_url('surveyors/list_surveyors'));
        }
    }

    public function pipeline_load_more()
    {
        $status = $this->input->get('status');
        $page   = $this->input->get('page');

        $surveyors = (new SurveyorsPipeline($status))
            ->search($this->input->get('search'))
            ->sortBy(
                $this->input->get('sort_by'),
                $this->input->get('sort')
            )
            ->page($page)->get();

        foreach ($surveyors as $surveyor) {
            $this->load->view('admin/surveyors/pipeline/_kanban_card', [
                'surveyor' => $surveyor,
                'status'   => $status,
            ]);
        }
    }

    public function set_surveyor_pipeline_autoload($id)
    {
        if ($id == '') {
            return false;
        }

        if ($this->session->has_userdata('surveyor_pipeline')
                && $this->session->userdata('surveyor_pipeline') == 'true') {
            $this->session->set_flashdata('surveyorid', $id);

            return true;
        }

        return false;
    }


    public function get_due_date()
    {
        if ($this->input->post()) {
            $date    = $this->input->post('date');
            $duedate = '';
            if (get_option('surveyor_due_after') != 0) {
                $date    = to_sql_date($date);
                $d       = date('Y-m-d', strtotime('+' . get_option('surveyor_due_after') . ' DAY', strtotime($date)));
                $duedate = _d($d);
                echo $duedate;
            }
        }
    }

    private function _can_view_own(int $client_id): bool
    {
        if (!$this->_is_own_entity($client_id, 'surveyor')) { return false; }
        return staff_can('view_own', 'surveyors');
    }

    private function _can_edit_own(int $client_id): bool
    {
        if (!$this->_is_own_entity($client_id, 'surveyor')) { return false; }
        return staff_can('edit_own', 'surveyors');
    }

    private function _is_own_entity($client_id, $client_type)
    {
        if (!get_staff_user_id()) { return false; }
        $staff = $this->db->get_where(db_prefix() . 'staff', [
            'staffid'     => get_staff_user_id(),
            'client_type' => $client_type,
        ])->row();
        if (!$staff || !$staff->client_id) { return false; }
        $my_client_id = (int) $staff->client_id;
        $client_id    = (int) $client_id;
        if ($my_client_id === $client_id) { return true; }
        $branch = $this->db->get_where(db_prefix() . 'clients', [
            'userid'      => $client_id,
            'company_id'  => $my_client_id,
            'client_type' => $client_type,
        ])->row();
        if ($branch) { return true; }
        $me_client = $this->db->get_where(db_prefix() . 'clients', ['userid' => $my_client_id])->row();
        if ($me_client && (int) $me_client->company_id === $client_id) { return true; }
        return false;
    }

    // ─── Registration Approval ────────────────────────────────────────────────

    public function pending_approvals()
    {
        if (!is_admin()) { access_denied('surveyors'); }

        // Stage 1: newly registered, user not yet activated
        $data['stage1'] = $this->db
            ->select('s.staffid, s.firstname, s.lastname, s.email, s.datecreated, s.client_id, c.company, c.vat')
            ->from(db_prefix() . 'staff s')
            ->join(db_prefix() . 'clients c', 'c.userid = s.client_id', 'left')
            ->where('s.client_type', 'surveyor')
            ->where('s.registration_status', 'pending')
            ->order_by('s.datecreated', 'DESC')
            ->get()->result_array();

        // Stage 2: user active but company still inactive — regardless of how user was created
        $rows = $this->db
            ->select('s.staffid, s.firstname, s.lastname, s.email, s.datecreated, s.client_id,
                      c.company, c.vat, c.phonenumber, c.state, c.city, c.address,
                      c.billing_street, c.billing_city, c.billing_state,
                      c.logo_light, c.logo_dark')
            ->from(db_prefix() . 'staff s')
            ->join(db_prefix() . 'clients c', 'c.userid = s.client_id', 'left')
            ->where('s.client_type', 'surveyor')
            ->where('s.active', 1)
            ->where('s.is_entity_owner', 1)
            ->where('c.active', 0)
            ->where('s.registration_status !=', 'rejected')
            ->where('s.registration_status !=', 'pending')
            ->order_by('s.datecreated', 'DESC')
            ->get()->result_array();

        foreach ($rows as &$row) {
            $row['_checks'] = $this->_completeness_checks($row);
            $row['_ready']  = !in_array(false, array_column($row['_checks'], 'ok'));
        }
        unset($row);
        $data['stage2'] = $rows;

        $data['title'] = _l('pending_registrations');
        $this->load->view('surveyors/admin/surveyors/pending_approvals', $data);
    }

    // Stage 1: activate user account, company stays inactive
    public function activate_user($staff_id)
    {
        if (!is_admin()) { access_denied('surveyors'); }

        $staff_id = (int) $staff_id;
        $staff = $this->db->get_where(db_prefix() . 'staff', [
            'staffid'             => $staff_id,
            'client_type'         => 'surveyor',
            'registration_status' => 'pending',
        ])->row();

        if (!$staff) { show_404(); }

        $this->db->where('staffid', $staff_id)->update(db_prefix() . 'staff', [
            'registration_status' => 'user_activated',
            'active'              => 1,
            'is_not_staff'        => 1,
        ]);

        $this->_send_registration_email($staff, 'approved');

        set_alert('success', _l('registration_user_activated'));
        redirect(admin_url('surveyors/pending_approvals'));
    }

    // Stage 2: approve company — activate client record + grant permissions
    public function approve_registration($staff_id)
    {
        if (!is_admin()) { access_denied('surveyors'); }

        $staff_id = (int) $staff_id;
        $staff = $this->db->get_where(db_prefix() . 'staff', [
            'staffid'             => $staff_id,
            'client_type'         => 'surveyor',
            'registration_status' => 'user_activated',
        ])->row();

        if (!$staff) { show_404(); }

        $this->db->where('staffid', $staff_id)->update(db_prefix() . 'staff', [
            'registration_status' => 'approved',
        ]);

        $this->db->where('userid', $staff->client_id)->update(db_prefix() . 'clients', [
            'active' => 1,
        ]);

        $already = $this->db->get_where(db_prefix() . 'staff_permissions', [
            'staff_id'   => $staff_id,
            'feature'    => 'personnels',
            'capability' => 'create',
        ])->row();
        if (!$already) {
            $this->db->insert(db_prefix() . 'staff_permissions', [
                'staff_id'   => $staff_id,
                'feature'    => 'personnels',
                'capability' => 'create',
            ]);
        }

        $this->_send_registration_email($staff, 'approved');

        set_alert('success', _l('registration_approved_success'));
        redirect(admin_url('surveyors/pending_approvals'));
    }

    public function reject_registration($staff_id)
    {
        if (!is_admin()) { access_denied('surveyors'); }

        $staff_id = (int) $staff_id;
        $staff = $this->db->get_where(db_prefix() . 'staff', [
            'staffid'     => $staff_id,
            'client_type' => 'surveyor',
        ])->row();

        if (!$staff) { show_404(); }

        $this->db->where('staffid', $staff_id)->update(db_prefix() . 'staff', [
            'registration_status' => 'rejected',
            'active'              => 0,
        ]);

        $this->_send_registration_email($staff, 'rejected');

        set_alert('success', _l('registration_rejected_success'));
        redirect(admin_url('surveyors/pending_approvals'));
    }

    private function _completeness_checks($row)
    {
        $client_id  = (int) ($row['client_id'] ?? 0);
        $legal_docs = [];
        if ($client_id) {
            $docs = $this->db
                ->where('client_id',   $client_id)
                ->where('client_type', 'surveyor')
                ->get(db_prefix() . 'client_legal_docs')->result();
            foreach ($docs as $doc) {
                $legal_docs[$doc->doc_type] = $doc;
            }
        }

        return [
            ['label' => _l('surveyor_vat'),        'ok' => !empty($row['vat'])],
            ['label' => _l('client_phonenumber'),  'ok' => !empty($row['phonenumber'])],
            ['label' => _l('client_address'),      'ok' => !empty($row['address'])],
            ['label' => _l('client_state'),        'ok' => !empty($row['state'])],
            ['label' => _l('client_city'),         'ok' => !empty($row['city'])],
            ['label' => _l('billing_address'),     'ok' => !empty($row['billing_street']) && !empty($row['billing_city']) && !empty($row['billing_state'])],
            ['label' => _l('surveyor_logo_light'), 'ok' => !empty($row['logo_light']) || !empty($row['logo_dark'])],
            ['label' => _l('doc_type_npwp'),       'ok' => !empty($legal_docs['npwp']->doc_number)],
        ];
    }

    private function _send_registration_email($staff, $status)
    {

        $this->load->model('clients_model');
        $entity = $this->clients_model->get($staff->client_id);

        if (!$entity) {
            return;
        }


        if ($status === 'approved') {
            $result = send_mail_template('Entity_staff_registration_confirmed', 'surveyors', $staff->staffid);
        } else {
            $result = send_mail_template('Entity_staff_registration_rejected', 'surveyors', $staff->staffid);
        }
    }
}
