<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Mobile API — Surveyors Auth
$route['api/v1/surveyors/auth/login']  = 'surveyors/surveyors_api/login';
$route['api/v1/surveyors/auth/logout'] = 'surveyors/surveyors_api/logout';
$route['api/v1/surveyors/auth/me']     = 'surveyors/surveyors_api/me';
