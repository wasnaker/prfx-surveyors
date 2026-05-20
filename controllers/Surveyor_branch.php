<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Surveyor_branch extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('surveyors/Surveyors_model', 'surveyors_model');
    }

public function get_branch_data($id)
    {
        if (!$this->input->is_ajax_request()) {
            redirect(admin_url('surveyors'));
        }
        if (!can_do_on_entity('view', 'surveyors', (int) $id, 'surveyor')) {
            ajax_access_denied();
        }
        $branch = $this->db->where('userid', (int) $id)
            ->where('company_id IS NOT NULL')
            ->get(db_prefix() . 'clients')->row();
        echo json_encode($branch ?: (object)[]);
    }

    public function save_branch()
    {
        if (!$this->input->is_ajax_request()) {
            redirect(admin_url('surveyors'));
        }

        $data      = $this->input->post();
        $parent_id = (int) ($data['parent_id'] ?? 0);
        $branch_id = (int) ($data['branch_id'] ?? 0);

        if (!$parent_id) {
            echo json_encode(['success' => false, 'message' => _l('something_went_wrong')]);
            return;
        }

        if (!$this->surveyors_model->is_branch_name_available($parent_id, $data['company'] ?? '', $branch_id)) {
            echo json_encode(['success' => false, 'message' => _l('branch_name_already_exists')]);
            return;
        }

        $nitku = $this->surveyors_model->normalize_tax_number($data['nitku'] ?? '');
        if ($nitku !== '' && !$this->surveyors_model->is_nitku_available($nitku, $branch_id)) {
            echo json_encode(['success' => false, 'message' => _l('nitku_already_exists')]);
            return;
        }

        if ($branch_id) {
            if (!can_do_on_entity('edit', 'surveyors', $branch_id, 'surveyor')) {
                echo json_encode(['success' => false, 'message' => _l('access_denied')]);
                return;
            }
            $result = $this->surveyors_model->update_branch($branch_id, $data);
        } else {
            if (staff_cant('create', 'surveyors') && !can_do_on_entity('edit', 'surveyors', $parent_id, 'surveyor')) {
                echo json_encode(['success' => false, 'message' => _l('access_denied')]);
                return;
            }

            $parent = $this->db->get_where(db_prefix() . 'clients', ['userid' => $parent_id])->row();
            if (!$parent || $parent->active != 1) {
                echo json_encode(['success' => false, 'message' => _l('surveyor_not_active')]);
                return;
            }

            $admin_email     = trim($data['admin_email'] ?? '');
            $admin_firstname = trim($data['admin_firstname'] ?? '');
            $admin_lastname  = trim($data['admin_lastname'] ?? '');
            $admin_password  = $data['admin_password'] ?? '';

            if (!$admin_email || !$admin_firstname || !$admin_lastname || !$admin_password) {
                echo json_encode(['success' => false, 'message' => _l('primary_contact_information') . ' is required.']);
                return;
            }
            if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => _l('invalid_email')]);
                return;
            }
            if ($this->db->where('email', $admin_email)->count_all_results(db_prefix() . 'staff')) {
                echo json_encode(['success' => false, 'message' => _l('email_already_in_use')]);
                return;
            }

            $result = $this->surveyors_model->add_branch($parent_id, $data);

            if ($result) {
                $role    = $this->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Branch Admin'])->row();
                $role_id = $role ? $role->roleid : 0;

                $this->db->insert(db_prefix() . 'staff', [
                    'firstname'           => $admin_firstname,
                    'lastname'            => $admin_lastname,
                    'email'               => $admin_email,
                    'password'            => app_hash_password($admin_password),
                    'role'                => $role_id,
                    'client_id'           => $result,
                    'client_type'         => 'surveyor',
                    'is_entity_owner'     => 1,
                    'registration_status' => 'approved',
                    'active'              => 1,
                    'is_not_staff'        => 1,
                    'datecreated'         => date('Y-m-d H:i:s'),
                ]);
                $new_staff_id = $this->db->insert_id();
                if ($new_staff_id) {
                    $this->db->where('userid', $result)
                             ->update(db_prefix() . 'clients', ['addedfrom' => $new_staff_id]);
                }
            }
        }

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? _l('branch_saved') : _l('something_went_wrong'),
        ]);
    }

    public function delete_branch($id)
    {
        if (!$this->input->is_ajax_request()) {
            redirect(admin_url('surveyors'));
        }
        if (staff_cant('delete', 'surveyors') && !can_do_on_entity('edit', 'surveyors', (int) $id, 'surveyor')) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            return;
        }
        $success = $this->surveyors_model->delete_branch($id);
        echo json_encode([
            'success' => $success,
            'message' => $success ? _l('branch_deleted') : _l('branch_delete_failed'),
        ]);
    }

}
