<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Shared token table — same as customers/install/api_tokens.php, IF NOT EXISTS keeps it safe
$CI = &get_instance();
$CI->db->query('
    CREATE TABLE IF NOT EXISTS ' . db_prefix() . 'api_tokens (
        id          INT(11)      NOT NULL AUTO_INCREMENT,
        staffid     INT(11)      NOT NULL,
        token       VARCHAR(64)  NOT NULL,
        client_type VARCHAR(20)  NOT NULL,
        client_id   INT(11)      NOT NULL,
        expires_at  DATETIME     NULL DEFAULT NULL,
        last_used_at DATETIME    NULL DEFAULT NULL,
        created_at  DATETIME     NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_token (token),
        KEY idx_staffid (staffid),
        KEY idx_client (client_type, client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
');
