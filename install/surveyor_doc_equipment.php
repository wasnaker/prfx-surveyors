<?php
defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();

$CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "surveyor_doc_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `surveyor_id` int(11) NOT NULL,
  `surveyor_equipment_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_surveyor_doc_eq` (`surveyor_id`, `surveyor_equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
