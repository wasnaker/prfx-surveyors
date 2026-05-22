<?php
defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();

$CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "surveyor_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `surveyor_id` int(11) NOT NULL,
  `surveyor_equipment_id` int(11) DEFAULT NULL,
  `surveyor_equipment_name` varchar(255) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `long_description` longtext DEFAULT NULL,
  `item_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `surveyor_id` (`surveyor_id`),
  KEY `surveyor_equipment_id` (`surveyor_equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
