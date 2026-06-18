<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APP_MODULES_PATH . 'demo/database/seeds/BaseSeeder.php';

/**
 * Kantor cabang 10 perusahaan surveyor K3.
 * IDs dinamis dari max(existing) setelah SurveyorsSeeder::run().
 * NITKU prefix 03.XXX | Email sv.staff*@demo.local
 */
class SurveyorBranchesSeeder extends BaseSeeder
{
    const CLIENT_START = 510;   // client IDs: 511..536 (26 branches, after HQ 501..510)
    const STAFF_START  = 2030;  // staff IDs : 2031..2056 (26 branch owners, after HQ 2001..2030)

    private array $names = [
        'Aditya','Pratama','Bagus','Cahyo','Danu','Erlangga','Farhan','Galih','Hanif','Indra',
        'Jefri','Kevin','Lukman','Miftah','Nanda','Oki','Prabowo','Qomar','Rangga','Satria',
        'Taufik','Umar','Vicky','Wahyu','Xaverius','Yusuf','Zaki','Abdul','Bambang','Chandra',
        'Dwipa','Edy','Fauzi','Guntur','Hafiz','Irwan','Jamal','Kamal','Latif','Munir',
    ];

    // same pool as SurveyorsSeeder — branch company names append city suffix instead
    private array $company_names = [
        'Prima','Utama','Andal','Unggul','Presisi','Handal','Terpadu','Akurat','Mutu','Profesional',
        'Inspeksi','Sertifikasi','Pengujian','Riksauji','Keselamatan','Audit','Verifikasi','Periksa','Penilaian','Standar',
        'Teknik','Rekayasa','Industri','Mekanik','Listrik','Konstruksi','Pesawat','Tekan','Safety','Proteksi',
    ];

    private array $branches = [
        // [parent_idx, phone, city, state, zip, nitku, staff]
        // ── parent 0 ──────────────────────────────────────────────────────────
        [0,'031-5031234','Kota Surabaya',      'Jawa Timur',       '60271','03.001.000.1-054.000',['Inspector',     '085111220001']],
        [0,'061-4531234','Kota Medan',         'Sumatera Utara',   '20152','03.001.000.2-054.000',['K3 Specialist', '085111220002']],
        [0,'0542-731234','Kota Balikpapan',    'Kalimantan Timur', '76112','03.001.000.3-054.000',['Inspector',     '085111220003']],
        [0,'0411-431234','Kota Makassar',      'Sulawesi Selatan', '90222','03.001.000.4-054.000',['K3 Engineer',   '085111220004']],
        // ── parent 1 ──────────────────────────────────────────────────────────
        [1,'022-7501234','Kota Bandung',       'Jawa Barat',       '40115','03.002.000.1-054.000',['Inspector',     '085111220005']],
        [1,'024-7621234','Kota Semarang',      'Jawa Tengah',      '50132','03.002.000.2-054.000',['K3 Specialist', '085111220006']],
        [1,'031-5671234','Kota Surabaya',      'Jawa Timur',       '60271','03.002.000.3-054.000',['Inspector',     '085111220007']],
        // ── parent 2 ──────────────────────────────────────────────────────────
        [2,'031-3291234','Kota Surabaya',      'Jawa Timur',       '60177','03.003.000.1-054.000',['Inspector',     '085111220008']],
        [2,'024-3541234','Kota Semarang',      'Jawa Tengah',      '50134','03.003.000.2-054.000',['K3 Specialist', '085111220009']],
        [2,'0411-851234','Kota Makassar',      'Sulawesi Selatan', '90111','03.003.000.3-054.000',['Inspector',     '085111220010']],
        // ── parent 3 ──────────────────────────────────────────────────────────
        [3,'0254-381234','Kota Cilegon',       'Banten',           '42411','03.004.000.1-054.000',['K3 Engineer',   '085111220011']],
        [3,'022-4231234','Kota Bandung',       'Jawa Barat',       '40252','03.004.000.2-054.000',['Inspector',     '085111220012']],
        [3,'031-7421234','Kota Surabaya',      'Jawa Timur',       '60181','03.004.000.3-054.000',['K3 Specialist', '085111220013']],
        // ── parent 4 ──────────────────────────────────────────────────────────
        [4,'021-5221234','Kota Jakarta Pusat', 'DKI Jakarta',      '10340','03.005.000.1-054.000',['Inspector',     '085111220014']],
        [4,'024-7601234','Kota Semarang',      'Jawa Tengah',      '50144','03.005.000.2-054.000',['K3 Engineer',   '085111220015']],
        // ── parent 5 ──────────────────────────────────────────────────────────
        [5,'021-2512000','Kota Jakarta Pusat', 'DKI Jakarta',      '10350','03.006.000.1-054.000',['Inspector',     '085111220016']],
        [5,'031-5671567','Kota Surabaya',      'Jawa Timur',       '60271','03.006.000.2-054.000',['K3 Specialist', '085111220017']],
        // ── parent 6 ──────────────────────────────────────────────────────────
        [6,'021-5204100','Kota Jakarta Selatan','DKI Jakarta',     '12920','03.007.000.1-054.000',['Inspector',     '085111220018']],
        [6,'022-4201234','Kota Bandung',        'Jawa Barat',      '40111','03.007.000.2-054.000',['K3 Engineer',   '085111220019']],
        // ── parent 7 ──────────────────────────────────────────────────────────
        [7,'031-3981900','Kabupaten Gresik',   'Jawa Timur',       '61151','03.008.000.1-054.000',['Inspector',     '085111220020']],
        [7,'021-6541234','Kota Jakarta Utara', 'DKI Jakarta',      '14310','03.008.000.2-054.000',['K3 Specialist', '085111220021']],
        [7,'022-4201567','Kota Bandung',       'Jawa Barat',       '40135','03.008.000.3-054.000',['Inspector',     '085111220022']],
        // ── parent 8 ──────────────────────────────────────────────────────────
        [8,'031-3291567','Kota Surabaya',      'Jawa Timur',       '60177','03.009.000.1-054.000',['K3 Engineer',   '085111220023']],
        [8,'061-4512345','Kota Medan',         'Sumatera Utara',   '20238','03.009.000.2-054.000',['Inspector',     '085111220024']],
        // ── parent 9 ──────────────────────────────────────────────────────────
        [9,'031-8421234','Kota Surabaya',      'Jawa Timur',       '60293','03.010.000.1-054.000',['K3 Specialist', '085111220025']],
        [9,'022-7601234','Kota Bandung',       'Jawa Barat',       '40144','03.010.000.2-054.000',['Inspector',     '085111220026']],
    ];

    public function run(array $surveyor_ids = []): array
    {
        $this->_reset_names();
        $r          = $this->db->get_where(db_prefix() . 'roles', ['name' => 'Surveyor Branch Admin'])->row();
        $rid_branch = $r ? (int) $r->roleid : 0;

        $i = self::CLIENT_START;
        $j = self::STAFF_START;

        $branch_ids = [];

        foreach ($this->branches as $idx => $b) {
            [$parent_idx, $bphone,
             $bcity, $bstate, $bzip, $bnitku, $bstaff] = $b;

            $cwords   = $this->_pick_words($this->company_names, $parent_idx, 2);
            $bcompany = 'PT. ' . implode(' ', $cwords) . ' ' . preg_replace('/^(Kota|Kabupaten) /i', '', $bcity);

            $branchid   = $i + 1 + $idx;
            $branch_sid = $j + 1 + $idx;
            $parentid   = $surveyor_ids[$parent_idx] ?? 0;

            [, $bsphone] = $bstaff;
            [$bsfirst, $bslast]  = $this->_random_name($this->names);
            $this->_insert_branch_owner($branch_sid, $bsfirst . ' ' . $bslast, $bsphone, $branchid, $rid_branch);

            $this->upsert('clients', 'userid', [
                'userid'      => $branchid,
                'company'     => $bcompany,
                'phonenumber' => $bphone,
                'city'        => $bcity,
                'state'       => $bstate,
                'zip'         => $bzip,
                'country'     => $this->country,
                'nitku'       => $bnitku,
                'use_vat'     => 0,
                'client_type' => 'surveyor',
                'company_id'  => $parentid,
                'active'      => 1,
                'addedfrom'   => $branch_sid,
                'datecreated' => $this->now,
                'year' => date('Y'),
            ]);

            $branch_ids[] = $branchid;
        }

        return $branch_ids;
    }

    private function _insert_branch_owner(int $staffid, string $name, string $phone,
                                           int $client_id, int $role_id): void
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
            'active'              => 1,
            'is_not_staff'        => 1,
            'is_entity_owner'     => 0,
            'is_branch_owner'     => 1,
            'client_id'           => $client_id,
            'client_type'         => 'surveyor',
            'registration_status' => 'approved',
            'datecreated'         => $this->now,
        ]);
    }
}
