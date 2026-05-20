<?php

defined('BASEPATH') or exit('No direct script access allowed');


class Surveyor_contact_password_resetted extends App_mail_template
{
    protected $for = 'surveyor';

    protected $contact_email;

    protected $contact_id;

    protected $client_id;

    public $slug = 'surveyor-password-reseted';

    public $rel_type = 'contact';

    public function __construct($contact_email, $client_id, $contact_id)
    {
        parent::__construct();

        $this->contact_email = $contact_email;
        $this->contact_id    = $contact_id;
        $this->client_id     = $client_id;
    }

    public function build()
    {
        $this->to($this->contact_email)
        ->set_rel_id($this->contact_id)
        ->set_merge_fields('surveyor_merge_fields', $this->client_id, $this->contact_id);
    }
}
