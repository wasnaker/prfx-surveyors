<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Surveyors_model extends App_Model
{
    private $statuses = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('clients_model');

        $this->statuses = hooks()->apply_filters('before_set_surveyor_statuses', [
            'pending',
            'active',
            'inactive',
        ]);        
    }

    /**
     * Get one surveyor or all surveyors from tblclients.
     */
    public function get($id = false)
    {
        $this->db->where('client_type', 'surveyor');
        if ($id) {
            return $this->db->get_where(db_prefix() . 'clients', ['userid' => $id])->row();
        }
        return $this->db->get(db_prefix() . 'clients')->result_array();
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

    /**
     * Strip all non-digit characters from a tax number (NPWP/NITKU).
     * e.g. "12.345.678.9-012.000" → "123456789012000"
     */
    public function normalize_tax_number($value)
    {
        return preg_replace('/[^0-9]/', '', (string) $value);
    }

    /**
     * Check if a vat value is already used by another client.
     * Returns true if the vat is available (not a duplicate).
     *
     * @param string $vat
     * @param int    $exclude_id  userid to exclude (for updates)
     */
    public function is_vat_available($vat, $exclude_id = 0)
    {
        if ($vat === '') {
            return true; // empty is always allowed
        }
        $this->db->where('vat', $vat);
        if ($exclude_id) {
            $this->db->where('userid !=', $exclude_id);
        }
        return $this->db->count_all_results(db_prefix() . 'clients') === 0;
    }

    /**
     * Check if a branch name is unique within the same parent.
     */
    public function is_branch_name_available($parent_id, $company, $exclude_id = 0)
    {
        $this->db->where('company_id', $parent_id);
        $this->db->where('company', $company);
        if ($exclude_id) {
            $this->db->where('userid !=', $exclude_id);
        }
        return $this->db->count_all_results(db_prefix() . 'clients') === 0;
    }

    /**
     * Check if a nitku value is already used by another client.
     */
    public function is_nitku_available($nitku, $exclude_id = 0)
    {
        if ($nitku === '') {
            return true;
        }
        $this->db->where('nitku', $nitku);
        if ($exclude_id) {
            $this->db->where('userid !=', $exclude_id);
        }
        return $this->db->count_all_results(db_prefix() . 'clients') === 0;
    }

    /**
     * Add a new surveyor — delegates to core clients_model.
     * Returns new client ID on success, false on failure.
     */
    public function add($data)
    {
        if (isset($data['vat'])) {
            $data['vat'] = $this->normalize_tax_number($data['vat']) ?: null;
        }
        $data['year'] = date('Y');
        return $this->clients_model->add($data);
    }

    /**
     * Update a surveyor — delegates to core clients_model.
     * Core signature: update($data, $id) — note data comes first.
     */
    public function update($id, $data)
    {
        if (isset($data['vat'])) {
            $data['vat'] = $this->normalize_tax_number($data['vat']) ?: null;
        }
        return $this->clients_model->update($data, $id);
    }

    /**
     * Delete a surveyor — delegates to core clients_model.
     */
    public function delete($id)
    {
        return $this->clients_model->delete($id);
    }

    /**
     * Get branches of a surveyor (company_id = parent userid).
     */
    public function get_branches($parent_id)
    {
        return $this->db->get_where(db_prefix() . 'clients', [
            'company_id'  => $parent_id,
            'client_type' => 'surveyor',
        ])->result_array();
    }

    /**
     * Add a branch record.
     */
    public function add_branch($parent_id, $data)
    {
        $use_vat = !empty($data['use_vat']) ? 1 : 0;
        $insert = [
            'company'     => $data['company']     ?? '',
            'phonenumber' => $data['phonenumber'] ?? '',
            'use_vat'     => $use_vat,
            'nitku'       => $use_vat ? null : ($this->normalize_tax_number($data['nitku'] ?? '') ?: null),
            'address'     => $data['address']     ?? '',
            'city'        => $data['city']        ?? '',
            'state'       => $data['state']       ?? '',
            'zip'         => $data['zip']         ?? '',
            'country'     => $data['country']     ?? 0,
            'active'      => 1,
            'client_type' => 'surveyor',
            'company_id'  => (int) $parent_id,
            'datecreated' => date('Y-m-d H:i:s'),
            'year'        => date('Y'),
            'addedfrom'   => get_staff_user_id(),
        ];
        $this->db->insert(db_prefix() . 'clients', $insert);
        return $this->db->insert_id();
    }

    /**
     * Update a branch record.
     */
    public function update_branch($id, $data)
    {
        $use_vat = !empty($data['use_vat']) ? 1 : 0;
        $update = [
            'company'     => $data['company']     ?? '',
            'phonenumber' => $data['phonenumber'] ?? '',
            'use_vat'     => $use_vat,
            'nitku'       => $use_vat ? null : ($this->normalize_tax_number($data['nitku'] ?? '') ?: null),
            'address'     => $data['address']     ?? '',
            'city'        => $data['city']        ?? '',
            'state'       => $data['state']       ?? '',
            'zip'         => $data['zip']         ?? '',
            'country'     => $data['country']     ?? 0,
        ];
        $this->db->where('userid', $id);
        $this->db->update(db_prefix() . 'clients', $update);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Delete a branch record.
     */
    public function delete_branch($id)
    {
        $this->db->where('userid', $id);
        $this->db->where('company_id IS NOT NULL');
        $this->db->delete(db_prefix() . 'clients');
        return $this->db->affected_rows() > 0;
    }

    public static $permit_statuses = ['active', 'pending', 'expired', 'revoked'];

    public static $permit_status_labels = [
        'active'  => 'label-success',
        'pending' => 'label-warning',
        'expired' => 'label-danger',
        'revoked' => 'label-default',
    ];

    /**
     * Get permits count for badge.
     */
    public function get_permits_count($surveyor_id)
    {
        return $this->db->where('surveyor_id', $surveyor_id)
            ->count_all_results(db_prefix() . 'surveyor_permits');
    }

    /**
     * Get a single permit.
     */
    public function get_permit($id)
    {
        return $this->db->get_where(db_prefix() . 'surveyor_permits', ['id' => $id])->row();
    }

    /**
     * Add a permit.
     */
    public function add_permit($data, $file_path = '')
    {
        $this->db->insert(db_prefix() . 'surveyor_permits', [
            'surveyor_id'  => (int) $data['surveyor_id'],
            'number'       => $data['number'] ?? '',
            'groupid'      => (int) ($data['groupid'] ?? 0),
            'publish_date' => !empty($data['publish_date']) ? $data['publish_date'] : null,
            'expired_date' => !empty($data['expired_date']) ? $data['expired_date'] : null,
            'status'       => in_array($data['status'] ?? '', self::$permit_statuses) ? $data['status'] : 'active',
            'file'         => $file_path,
            'addedfrom'    => get_staff_user_id(),
            'datecreated'  => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    /**
     * Update a permit.
     */
    public function update_permit($id, $data, $file_path = null)
    {
        $update = [
            'number'       => $data['number'] ?? '',
            'groupid'      => (int) ($data['groupid'] ?? 0),
            'publish_date' => !empty($data['publish_date']) ? $data['publish_date'] : null,
            'expired_date' => !empty($data['expired_date']) ? $data['expired_date'] : null,
            'status'       => in_array($data['status'] ?? '', self::$permit_statuses) ? $data['status'] : 'active',
        ];
        if ($file_path !== null) {
            $update['file'] = $file_path;
        }
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'surveyor_permits', $update);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Delete a permit and its file.
     */
    public function delete_permit($id)
    {
        $permit = $this->get_permit($id);
        if (!$permit) { return false; }

        if (!empty($permit->file)) {
            $path = FCPATH . 'uploads/surveyor_permits/' . $permit->file;
            if (file_exists($path)) { unlink($path); }
        }
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'surveyor_permits');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Get activity log for a surveyor.
     */
    public function get_activity($id)
    {
        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'surveyor');
        $this->db->order_by('date', 'desc');
        return $this->db->get(db_prefix() . 'sales_activity')->result_array();
    }

    /**
     * Log an activity entry for a surveyor.
     *
     * @param int    $id
     * @param string $description  lang key
     * @param string $additional_data  plain-text diff string (optional)
     */
    public function log_activity($id, $description = '', $additional_data = '')
    {
        $staffid   = get_staff_user_id();
        $full_name = get_staff_full_name($staffid);
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
     * Compare old and new data, return human-readable diff string.
     * Only includes fields that actually changed.
     *
     * @param array $old       old record as array
     * @param array $new       new POST data
     * @param array $fields    [ db_column => display_label ]
     * @return string
     */
    public function build_diff(array $old, array $new, array $fields)
    {
        $changes = [];
        foreach ($fields as $key => $label) {
            $oldVal = isset($old[$key]) ? trim((string)$old[$key]) : '';
            $newVal = isset($new[$key]) ? trim((string)$new[$key]) : '';
            if ($oldVal !== $newVal) {
                $changes[] = $label . ': "' . $oldVal . '" → "' . $newVal . '"';
            }
        }
        return implode("\n", $changes);
    }

    public function get_surveyors_years()
    {
        return $this->db
            ->select('DISTINCT(year) as year')
            ->where('client_type', 'surveyor')
            ->where('company_id IS NULL', null, false)
            ->order_by('year', 'DESC')
            ->get(db_prefix() . 'clients')
            ->result_array();
    }
}
