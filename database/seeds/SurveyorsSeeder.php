<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APP_MODULES_PATH . 'demo/database/seeds/BaseSeeder.php';

/**
 * 10 perusahaan jasa K3 / surveyor sungguhan.
 * IDs dinamis dari max(existing) + 1 setelah cleanup.
 * VAT prefix 03.XXX | Email sv.*@demo.local | password: Demo1234!
 */
class SurveyorsSeeder extends BaseSeeder
{
    const CLIENT_START = 500;   // client IDs: 501..510 (10 HQ)
    const STAFF_START  = 2000;  // staff IDs : 2001..2030 (10 owners + 10 regular + 10 assessors)

    private array $company_names = [
        // kualitas/posisi perusahaan
        'Prima','Utama','Andal','Unggul','Presisi','Handal','Terpadu','Akurat','Mutu','Profesional',
        // aktivitas K3 (istilah Permenaker)
        'Inspeksi','Sertifikasi','Pengujian','Riksauji','Keselamatan','Audit','Verifikasi','Periksa','Penilaian','Standar',
        // domain teknis K3
        'Teknik','Rekayasa','Industri','Mekanik','Listrik','Konstruksi','Pesawat','Tekan','Safety','Proteksi',
    ];

    private array $names = [
        'Aditya','Pratama','Bagus','Cahyo','Danu','Erlangga','Farhan','Galih','Hanif','Indra',
        'Jefri','Kevin','Lukman','Miftah','Nanda','Oki','Prabowo','Qomar','Rangga','Satria',
        'Taufik','Umar','Vicky','Wahyu','Xaverius','Yusuf','Zaki','Abdul','Bambang','Chandra',
        'Dwipa','Edy','Fauzi','Guntur','Hafiz','Irwan','Jamal','Kamal','Latif','Munir',
    ];

    // Koordinat realistis — address diambil dari $companies saat run()
    private array $hq_coords = [
        [-6.2893, 106.8413],
        [-6.2371, 106.8153],
        [-6.1204, 106.8928],
        [-6.2435, 107.0014],
        [-6.1186, 106.1505],
        [-6.0194, 106.0500],
        [-6.1783, 106.6300],
        [-6.1568, 106.2153],
        [-6.1031, 106.7961],
        [-6.3015, 106.7954],
    ];

    private array $companies = [
        // [email, staff_lastname, vat, company, phone, address, city, state, zip, staff]
        ['sv.sucofindo@demo.local',   'Sucofindo',   '03.001.000.0-054.000',
            'PT. Sucofindo (Persero)',
            '021-7983666', 'Jl. Raya Pasar Minggu Kav.34',
            'Jakarta Selatan', 'DKI Jakarta', '12780',
            ['Hendra Sutanto',   'Inspector',         '082111220001']],

        ['sv.si@demo.local',          'SI',          '03.002.000.0-054.000',
            'PT. Surveyor Indonesia (Persero)',
            '021-2528000', 'Jl. Jend. Gatot Subroto Kav.56',
            'Jakarta Selatan', 'DKI Jakarta', '12950',
            ['Ratna Kusumawati', 'K3 Specialist',     '082111220002']],

        ['sv.bki@demo.local',         'BKI',         '03.003.000.0-054.000',
            'PT. Biro Klasifikasi Indonesia (Persero)',
            '021-6516482', 'Jl. Yos Sudarso No.38-40',
            'Jakarta Utara', 'DKI Jakarta', '14320',
            ['Fajar Wahyudi',    'Senior Inspector',  '082111220003']],

        ['sv.alkon@demo.local',       'Alkon',       '03.004.000.0-054.000',
            'PT. Alkon Tirta Kencana',
            '021-8971234', 'Jl. Raya Bekasi Timur No.12',
            'Kota Bekasi', 'Jawa Barat', '17113',
            ['Agus Dermawan',    'K3 Engineer',       '082111220004']],

        ['sv.ajg@demo.local',         'AJG',         '03.005.000.0-054.000',
            'PT. Aneka Jasa Grhadika',
            '0254-221456', 'Jl. Ahmad Yani No.12',
            'Kota Serang', 'Banten', '42117',
            ['Siti Rahayu',      'Inspector',         '082111220005']],

        ['sv.mal@demo.local',         'MAL',         '03.006.000.0-054.000',
            'PT. Mutu Agung Lestari',
            '0254-395678', 'Jl. Jend. Sudirman No.55',
            'Kota Cilegon', 'Banten', '42411',
            ['Taufik Hidayat',   'QC Specialist',     '082111220006']],

        ['sv.sistekindo@demo.local',  'Sistekindo',  '03.007.000.0-054.000',
            'PT. Sistekindo Gemilang',
            '021-5537890', 'Jl. M.H. Thamrin Blok B No.8',
            'Kota Tangerang', 'Banten', '15111',
            ['Nurul Aini',       'K3 Specialist',     '082111220007']],

        ['sv.wahana@demo.local',      'Wahana',      '03.008.000.0-054.000',
            'PT. Wahana Mitra Usaha',
            '0254-480234', 'Ruko Serang Trade Center Blok C No.14',
            'Kabupaten Serang', 'Banten', '42161',
            ['Wahyu Saputra',    'Senior Inspector',  '082111220008']],

        ['sv.sarana@demo.local',      'Sarana',      '03.009.000.0-054.000',
            'PT. Sarana Total International',
            '021-5261234', 'Jl. Pluit Raya No.11, Jakarta Utara',
            'Jakarta Utara', 'DKI Jakarta', '14440',
            ['Sri Handayani',    'K3 Engineer',       '082111220009']],

        ['sv.qualis@demo.local',      'Qualis',      '03.010.000.0-054.000',
            'PT. Qualis Indonesia',
            '021-7990234', 'Jl. Letjen TB Simatupang Kav.88',
            'Jakarta Selatan', 'DKI Jakarta', '12520',
            ['Sigit Prabowo',    'Inspector',         '082111220010']],
    ];

    public function run(): array
    {
        $this->_clean();
        $this->_reset_names();

        // Load ULID helper for hash generation
        $CI = get_instance();
        $CI->load->helper('apps/ulid');

        $r             = $this->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Admin'])->row();
        $rid_admin     = $r ? (int) $r->roleid : 0;
        $r             = $this->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor'])->row();
        $rid_base      = $r ? (int) $r->roleid : 0;
        $r             = $this->db->get_where(db_prefix() . 'roles', ['name' => 'Assessor'])->row();
        $rid_assessor  = $r ? (int) $r->roleid : $rid_base;

        $i  = self::CLIENT_START;
        $j  = self::STAFF_START;
        $n  = count($this->companies);
        $ids = [];

        foreach ($this->companies as $idx => $c) {
            [$email, , $vat, ,
             $phone, $address, $city, $state, $zip, $staff] = $c;

            $cwords  = $this->_pick_words($this->company_names, $idx, 2);
            $company = 'PT. ' . implode(' ', $cwords);
            [$admin_first, $admin_last] = $this->_random_name($this->names);

            $userid  = $i + 1 + $idx;
            $staffid = $j + 1 + $idx;

            $this->upsert('clients', 'userid', [
                'userid'      => $userid,
                'company'     => $company,
                'phonenumber' => $phone,
                'address'     => $address,
                'city'        => $city,
                'state'       => $state,
                'zip'         => $zip,
                'country'     => $this->country,
                'vat'         => $vat,
                'client_type' => 'surveyor',
                'hash'        => ulid(),
                'active'      => 1,
                'addedfrom'   => $staffid,
                'datecreated' => $this->now,
            ]);

            $this->upsert('staff', 'staffid', [
                'staffid'             => $staffid,
                'email'               => $email,
                'firstname'           => $admin_first,
                'lastname'            => $admin_last,
                'password'            => app_hash_password('Demo1234!'),
                'role'                => $rid_admin,
                'hash'                => ulid(),
                'active'              => 1,
                'is_not_staff'        => 1,
                'is_entity_owner'     => 1,
                'client_id'           => $userid,
                'client_type'         => 'surveyor',
                'registration_status' => 'approved',
                'datecreated'         => $this->now,
            ]);
            [, $spos, $sphone]     = $staff;
            [$sfirst, $slast]      = $this->_random_name($this->names);
            $staff_sid = $j + 1 + $n + $idx;
            $this->_insert_staff($staff_sid, $sfirst . ' ' . $slast, $spos, $sphone, $userid, $rid_base);

            // Assessor — pool offset di atas regular staff range (idx + n)
            [$afirst, $alast] = $this->_random_name($this->names);
            $assessor_sid = $j + 1 + (2 * $n) + $idx;
            $this->_insert_staff($assessor_sid, $afirst . ' ' . $alast, 'Assessor', '0800000' . $idx, $userid, $rid_assessor);

            [$clat, $clng] = $this->hq_coords[$idx];
            $this->_upsert_coordinates($userid, 'surveyor', $clat, $clng, $address . ', ' . $city . ', ' . $state);

            $ids[] = $userid;
        }

        return $ids;
    }

    private function _upsert_coordinates(int $userid, string $entity_type, float $lat, float $lng, string $address): void
    {
        $t = db_prefix() . 'entity_coordinates';
        $this->db->query(
            "INSERT INTO {$t} (userid, entity_type, latitude, longitude, address, dateupdated)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE latitude=VALUES(latitude), longitude=VALUES(longitude),
                                     address=VALUES(address), dateupdated=NOW()",
            [$userid, $entity_type, $lat, $lng, $address]
        );
    }

    private function _clean(): void
    {
        $hq_ids = range(self::CLIENT_START + 1, self::CLIENT_START + 10);
        $this->no_debug(function () use ($hq_ids) {
            $this->db->where_in('userid', $hq_ids)
                     ->where('entity_type', 'surveyor')
                     ->delete(db_prefix() . 'entity_coordinates');
        });
        $this->no_debug(function () {
            $this->db->where('client_type', 'surveyor')
                     ->where('company_id IS NOT NULL', null, false)
                     ->delete(db_prefix() . 'clients');
            $this->safe_delete('clients', ['client_type' => 'surveyor']);
        });
        $this->no_debug(function () {
            $this->db->query(
                "DELETE sp FROM " . db_prefix() . "staff_permissions sp
                 INNER JOIN " . db_prefix() . "staff s ON s.staffid = sp.staff_id
                 WHERE s.client_type = 'surveyor'"
            );
            $this->db->query("DELETE FROM " . db_prefix() . "staff WHERE client_type = 'surveyor'");
        });

        // Hapus stale staff: client_type surveyor tapi role tidak valid
        // (sisa data dari seeder lama dengan ID/role yang salah)
        $this->no_debug(function () {
            $valid_roles = ['Surveyor Admin', 'Surveyor', 'Surveyor Branch Admin', 'Assessor'];
            $placeholders = implode(',', array_fill(0, count($valid_roles), '?'));
            $this->db->query(
                "DELETE sp FROM " . db_prefix() . "staff_permissions sp
                 INNER JOIN " . db_prefix() . "staff s ON s.staffid = sp.staff_id
                 INNER JOIN " . db_prefix() . "roles r ON r.roleid = s.role
                 WHERE s.client_type = 'surveyor'
                 AND r.name NOT IN ({$placeholders})",
                $valid_roles
            );
            $this->db->query(
                "DELETE s FROM " . db_prefix() . "staff s
                 INNER JOIN " . db_prefix() . "roles r ON r.roleid = s.role
                 WHERE s.client_type = 'surveyor'
                 AND r.name NOT IN ({$placeholders})",
                $valid_roles
            );
        });
    }

    private function _insert_staff(int $staffid, string $name, string $position,
                                    string $phone, int $client_id, int $role_id): void
    {
        $parts = explode(' ', trim($name), 2);
        $this->upsert('staff', 'staffid', [
            'staffid'             => $staffid,
            'email'               => 'sv.staff' . $staffid . '@demo.local',
            'firstname'           => $parts[0] ?? '',
            'lastname'            => $parts[1] ?? '',
            'position'            => $this->_random_positions('surveyor'),
            'phonenumber'         => $phone,
            'password'            => app_hash_password('Demo1234!'),
            'role'                => $role_id,
            'hash'                => ulid(),
            'active'              => 1,
            'is_not_staff'        => 1,
            'is_entity_owner'     => 0,
            'client_id'           => $client_id,
            'client_type'         => 'surveyor',
            'registration_status' => 'approved',
            'datecreated'         => $this->now,
        ]);
    }
}
