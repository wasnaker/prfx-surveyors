<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Surveyor_contact_set_password extends App_mail_template
{
    protected $for = 'surveyor';

    protected $contact;

    protected $password_data;

    public $slug = 'surveyor-set-password';

    public $rel_type = 'contact';

    public function __construct($contact, $password_data)
    {
        parent::__construct();

        $this->contact       = $contact;
        $this->password_data = $password_data;
    }

    public function build()
    {
        $this->ci->load->library('surveyors/merge_fields/surveyor_merge_fields');

        $this->to($this->contact->email)
        ->set_rel_id($this->contact->id)
        ->set_merge_fields('surveyor_merge_fields', $this->contact->userid, $this->contact->id)
        ->set_merge_fields($this->ci->surveyor_merge_fields->password($this->password_data, 'set'));
    }
}
