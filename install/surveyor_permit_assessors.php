<?php
defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();

$CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "surveyor_permit_assessors` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `permit_id`    int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `permit_id` (`permit_id`),
  KEY `personnel_id` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
