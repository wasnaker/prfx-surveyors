<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(APPPATH . 'libraries/pdf/App_pdf.php');

class Surveyor_pdf extends App_pdf
{
    protected $surveyor;

    private $surveyor_number;

    public function __construct($surveyor, $tag = '')
    {
        $this->load_language($surveyor->userid);

        $surveyor                = hooks()->apply_filters('surveyor_html_pdf_data', $surveyor);
        $GLOBALS['surveyor_pdf'] = $surveyor;

        parent::__construct();

        $this->tag             = $tag;
        $this->surveyor        = $surveyor;
        $this->surveyor_number = e($surveyor->company);

        $this->SetTitle($this->surveyor_number);
    }

    public function prepare()
    {
        $this->set_view_vars([
            'status'          => $this->surveyor->active == 1 ? 'active' : 'inactive',
            'surveyor_number' => $this->surveyor_number,
            'surveyor'        => $this->surveyor,
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'surveyor';
    }

    protected function file_path()
    {
        $theme      = active_clients_theme();
        $customPath = APPPATH . 'views/themes/' . $theme . '/views/my_surveyorpdf.php';
        $actualPath = FCPATH . 'modules/surveyors/views/themes/' . $theme . '/views/surveyorpdf.php';

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
