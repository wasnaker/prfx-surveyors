<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Surveyors_api extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('apimanagers/api_gateway');
        $this->api_gateway->guard('surveyor');
    }

    // ── POST /api/v1/surveyors/auth/login ─────────────────────────────────────

    public function login()
    {
        if ($this->input->method() !== 'post') {
            return $this->_err('Method not allowed', 405);
        }

        $body     = $this->_body();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            return $this->_err('Email and password required');
        }

        $staff = $this->db->get_where(db_prefix() . 'staff', [
            'email'        => $email,
            'client_type'  => 'surveyor',
            'is_not_staff' => 1,
        ])->row();

        if (!$staff || !app_hasher()->CheckPassword($password, $staff->password)) {
            return $this->_err('Invalid credentials', 401);
        }

        if (!(int) $staff->active) {
            return $this->_err('Account pending approval', 403);
        }

        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . 'api_tokens', [
            'staffid'     => $staff->staffid,
            'token'       => $token,
            'client_type' => 'surveyor',
            'client_id'   => $staff->client_id,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->_ok([
            'token' => $token,
            'staff' => $this->_user_data($staff),
        ]);
    }

    // ── POST /api/v1/surveyors/auth/logout ────────────────────────────────────

    public function logout()
    {
        $token = $this->_bearer();
        if ($token) {
            $this->db->where('token', $token)->where('client_type', 'surveyor')
                     ->delete(db_prefix() . 'api_tokens');
        }
        $this->_ok(['message' => 'Logged out']);
    }

    // ── GET /api/v1/surveyors/auth/me ─────────────────────────────────────────

    public function me()
    {
        $row = $this->_token_row('surveyor');
        if (!$row) return $this->_err('Unauthorized', 401);

        $staff = $this->db->get_where(db_prefix() . 'staff', ['staffid' => $row->staffid])->row();
        if (!$staff) return $this->_err('User not found', 404);

        $this->_ok(['staff' => $this->_user_data($staff)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function _user_data($staff)
    {
        $role = $staff->role
            ? $this->db->get_where(db_prefix() . 'roles', ['roleid' => $staff->role])->row()
            : null;

        $client = $this->db->get_where(db_prefix() . 'clients', ['userid' => $staff->client_id])->row();

        return [
            'staffid'         => (int) $staff->staffid,
            'firstname'       => $staff->firstname,
            'lastname'        => $staff->lastname,
            'email'           => $staff->email,
            'phonenumber'     => $staff->phonenumber ?? null,
            'profile_image'   => $staff->profile_image ?? null,
            'position'        => $staff->position ?? null,
            'client_type'     => $staff->client_type,
            'userid'          => (int) $staff->client_id,
            'role'            => $role ? $role->name : null,
            'company_name'    => $client ? $client->company : null,
            'active'          => (int) $staff->active,
            'is_not_staff'    => (int) $staff->is_not_staff,
            'is_entity_owner' => (int) $staff->is_entity_owner,
            'is_branch_owner' => (int) ($staff->is_branch_owner ?? 0),
        ];
    }

    private function _token_row($client_type)
    {
        $token = $this->_bearer();
        if (!$token) return null;

        $row = $this->db->get_where(db_prefix() . 'api_tokens', [
            'token'       => $token,
            'client_type' => $client_type,
        ])->row();

        if (!$row) return null;
        if ($row->expires_at && strtotime($row->expires_at) < time()) return null;

        $this->db->where('id', $row->id)
                 ->update(db_prefix() . 'api_tokens', ['last_used_at' => date('Y-m-d H:i:s')]);
        return $row;
    }

    private function _bearer()
    {
        $h = $this->input->server('HTTP_AUTHORIZATION') ?? '';
        return preg_match('/Bearer\s+(.+)$/i', $h, $m) ? trim($m[1]) : null;
    }

    private function _body()
    {
        return json_decode($this->input->raw_input_stream, true) ?? [];
    }

    private function _ok($data)
    {
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => true, 'data' => $data]));
    }

    private function _err($message, $code = 400)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => false, 'message' => $message]));
    }
}
