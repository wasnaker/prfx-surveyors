<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Mobile API
$route['api/v1/surveyors/auth/login']  = 'surveyors/surveyors_api/login';
$route['api/v1/surveyors/auth/logout'] = 'surveyors/surveyors_api/logout';
$route['api/v1/surveyors/auth/me']     = 'surveyors/surveyors_api/me';

$route['surveyors/(:num)/(:any)']                   = 'surveyor/index/$1/$2';
$route['authentication/register/surveyor']           = 'surveyors/surveyorauth/index';
$route['admin/surveyors/pending_approvals']                        = 'surveyors/surveyors/pending_approvals';
$route['admin/surveyors/activate_user/(:num)']                     = 'surveyors/surveyors/activate_user/$1';
$route['admin/surveyors/approve_registration/(:num)']              = 'surveyors/surveyors/approve_registration/$1';
$route['admin/surveyors/reject_registration/(:num)']               = 'surveyors/surveyors/reject_registration/$1';
$route['admin/surveyors/serve_legal_doc/(:num)/(:any)']            = 'surveyors/surveyors/serve_legal_doc/$1/$2';
$route['admin/surveyors/download_legal_doc/(:num)/(:any)']         = 'surveyors/surveyors/download_legal_doc/$1/$2';
$route['admin/surveyors/delete_legal_doc/(:num)/(:any)']           = 'surveyors/surveyors/delete_legal_doc/$1/$2';
