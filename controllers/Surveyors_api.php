<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// Pre-load Format and Authorization_token from modules/api/libraries/
// so REST_Controller constructor can find them when called from outside the api module
require_once __DIR__ . '/../../api/libraries/Format.php';
require_once __DIR__ . '/../../api/libraries/Authorization_token.php';

require_once __DIR__ . '/../../api/controllers/REST_Controller.php';

class Surveyors_api extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('surveyors/Surveyors_model');
    }

    /**
     * GET api/surveyors/data — List all surveyors
     * GET api/surveyors/data/:id — Get single surveyor
     */
    public function data_get($id = '')
    {
        $data = $this->Surveyors_model->get($id);
        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'No data were found'], REST_Controller::HTTP_NOT_FOUND);
        }
    }

    /**
     * GET api/surveyors/data_search/:key — Search surveyors
     */
    public function data_search_get($key = '')
    {
        $data = $this->Surveyors_model->get();
        if (!empty($key)) {
            $data = array_filter($data, function ($s) use ($key) {
                return stripos($s['company'] ?? '', $key) !== false
                    || stripos($s['vat'] ?? '', $key) !== false
                    || stripos($s['phonenumber'] ?? '', $key) !== false;
            });
            $data = array_values($data);
        }
        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'No data were found'], REST_Controller::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST api/surveyors/data — Create surveyor
     */
    public function data_post()
    {
        $this->form_validation->set_rules('company', 'Company', 'trim|required|max_length[600]');
        if ($this->form_validation->run() == false) {
            $this->response([
                'status'  => false,
                'error'   => $this->form_validation->error_array(),
                'message' => validation_errors(),
            ], REST_Controller::HTTP_NOT_FOUND);
            return;
        }

        $insert_data = [
            'company'     => $this->input->post('company', true),
            'vat'         => $this->input->post('vat', true),
            'phonenumber' => $this->input->post('phonenumber', true),
            'website'     => $this->input->post('website', true),
            'address'     => $this->input->post('address', true),
            'city'        => $this->input->post('city', true),
            'state'       => $this->input->post('state', true),
            'zip'         => $this->input->post('zip', true),
            'country'     => $this->input->post('country', true),
        ];

        $output = $this->Surveyors_model->add($insert_data);
        if ($output > 0) {
            $this->response(['status' => true, 'message' => 'Surveyor created successfully', 'id' => $output], REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'Surveyor creation failed'], REST_Controller::HTTP_NOT_FOUND);
        }
    }

    /**
     * PUT api/surveyors/data/:id — Update surveyor
     */
    public function data_put($id = '')
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents('php://input')), true);

        if (empty($id) || !is_numeric($id)) {
            $this->response(['status' => false, 'message' => 'Invalid Surveyor ID'], REST_Controller::HTTP_NOT_FOUND);
            return;
        }

        $output = $this->Surveyors_model->update($id, $this->input->post());
        if ($output) {
            $this->response(['status' => true, 'message' => 'Surveyor updated successfully'], REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'Surveyor update failed'], REST_Controller::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE api/surveyors/data/:id — Delete surveyor
     */
    public function data_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) || !is_numeric($id)) {
            $this->response(['status' => false, 'message' => 'Invalid Surveyor ID'], REST_Controller::HTTP_NOT_FOUND);
            return;
        }

        $output = $this->Surveyors_model->delete($id);
        if ($output === true) {
            $this->response(['status' => true, 'message' => 'Surveyor deleted successfully'], REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'Surveyor deletion failed'], REST_Controller::HTTP_NOT_FOUND);
        }
    }
}
