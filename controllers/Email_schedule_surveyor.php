<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Email_schedule_surveyor extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('email_schedule_model');
    }

    public function create($id)
    {
        if (staff_cant('create', 'surveyors')) {
            ajax_access_denied();
        }

        $this->load->model('surveyors_model');

        if ($this->input->post()) {
            $data = $this->input->post();

            $this->email_schedule_model->create($id, 'surveyor', [
                'scheduled_at' => to_sql_date($data['scheduled_at'], true),
                'cc'           => $data['cc'],
                'contacts'     => $data['sent_to'],
                'attach_pdf'   => isset($data['attach_pdf']) ? 1 : 0,
                'template'     => 'surveyor_send_to_surveyor',
            ]);

            set_alert('success', _l('email_scheduled_successfully'));
            redirect(admin_url('surveyors/list_surveyors/' . $id));
        }

        $data            = $this->scheduleData($id);
        $data['formUrl'] = admin_url('email_schedule_surveyor/create/' . $id);
        $data['date']    = get_scheduled_email_default_date();
        $this->load->view('admin/surveyors/schedule', $data);
    }

    public function edit($id)
    {
        $schedule = $this->email_schedule_model->getById($id);
        $this->load->model('surveyors_model');
        $data = $this->scheduleData($schedule->rel_id);
        if (staff_can('edit', 'surveyors') || $data['surveyor']->addedfrom == get_staff_user_id()) {
            if ($this->input->post()) {
                $data = $this->input->post();

                $this->email_schedule_model->update($id, [
                    'scheduled_at' => to_sql_date($data['scheduled_at'], true),
                    'cc'           => $data['cc'],
                    'contacts'     => $data['sent_to'],
                    'attach_pdf'   => isset($data['attach_pdf']) ? 1 : 0,
                ]);

                set_alert('success', _l('email_scheduled_successfully'));
                redirect(admin_url('surveyors/list_surveyors/' . $schedule->rel_id));
            }

            $data['schedule'] = $schedule;
            $data['formUrl']  = admin_url('email_schedule_surveyor/edit/' . $id);
            $data['date']     = $schedule->scheduled_at;

            $this->load->view('admin/surveyors/schedule', $data);
        } else {
            ajax_access_denied();
        }
    }

    protected function scheduleData($id)
    {
        $surveyor = $this->surveyors_model->get($id);

        $data['surveyor'] = $surveyor;

        $this->load->model('emails_model');
        $template_name = 'surveyor_send_to_surveyor';
        $slug          = $this->app_mail_template->get_default_property_value('slug', $template_name);
        $template      = $this->emails_model->get(['slug' => $slug, 'language' => 'english'], 'row');

        $data['template_disabled']    = $template->active == 0;
        $data['template_id']          = $template->emailtemplateid;
        $data['template_system_name'] = $template->name;

        return $data;
    }
}
