<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APP_MODULES_PATH . 'demo/database/seeds/BaseSeeder.php';

/**
 * Seeder untuk tblsurveyor_permits + tblsurveyor_permit_assessors
 *
 * 5 surveyor × 6 group (ID 1–6) = 30 permits
 * Static permit IDs: 1001–1030
 * Format nomor: 5/[rand 5-digit]/AS.01.02/[bulan romawi]/[tahun terbit]
 * Masa berlaku: 3 tahun; status derived dari expired_date
 * Assessors: staff personnel surveyor terkait yang punya permit untuk group yang sama
 */
class SurveyorPermitsSeeder extends BaseSeeder
{
    private static $roman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];

    private static $groups = [1, 2, 3, 4, 5, 6];

    // Static permit IDs per surveyor (6 per company, starting at 1001)
    private static $permit_id_start = [
        'sucofindo' => 1001,
        'si'        => 1007,
        'bki'       => 1013,
        'alkon'     => 1019,
        'ajg'       => 1025,
    ];

    private function _permit_number(string $publish_date): string
    {
        [$y, $m] = explode('-', $publish_date);
        $roman   = self::$roman[(int)$m - 1];
        $nomor   = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        return '5/' . $nomor . '/AS.01.02/' . $roman . '/' . $y;
    }

    private function _publish_date(): string
    {
        $year  = (int)date('Y') - rand(1, 5);
        $month = rand(1, 12);
        $day   = rand(1, 28);
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function _expired_date(string $publish): string
    {
        $d = new DateTime($publish);
        $d->modify('+3 years');
        return $d->format('Y-m-d');
    }

    private function _status(string $expired): string
    {
        return $expired < date('Y-m-d') ? 'expired' : ['active', 'pending'][rand(0, 1)];
    }

    /**
     * @param array $surveyor_ids      output dari SurveyorsSeeder::run()
     * @param array $personnels        output dari PersonnelsSeeder::run()
     * @param array $personnel_permits output dari PermitsSeeder::run()
     */
    public function run(array $surveyor_ids = [], array $personnels = [], array $personnel_permits = []): array
    {
        $this->safe_delete('surveyor_permit_assessors');
        $this->safe_delete('surveyor_permits');

        // surveyor_ids is a flat array: [sucofindo_cid, si_cid, bki_cid, alkon_cid, ajg_cid, ...]
        [$sucofindo, $si, $bki, $alkon, $ajg] = array_pad($surveyor_ids, 5, 0);

        $surveyor_map = [
            'sucofindo' => $sucofindo,
            'si'        => $si,
            'bki'       => $bki,
            'alkon'     => $alkon,
            'ajg'       => $ajg,
        ];

        // Build admin map: client_id → staffid of is_entity_owner=1
        $admin_map = [];
        foreach ($this->db->where('client_type', 'surveyor')->where('is_entity_owner', 1)
                           ->get(db_prefix() . 'staff')->result() as $row) {
            $admin_map[(int)$row->client_id] = (int)$row->staffid;
        }

        // Helper: find personnel IDs with a permit for a given groupid
        $assessors_for_group = function(string $key, int $groupid) use ($personnels, $personnel_permits): array {
            $pids = array_column($personnels['surveyor'][$key] ?? [], 'id');
            return array_values(array_filter($pids, function($pid) use ($groupid, $personnel_permits) {
                foreach ($personnel_permits[$pid] ?? [] as $pp) {
                    if ((int)$pp['groupid'] === $groupid) { return true; }
                }
                return false;
            }));
        };

        $inserted = [];

        foreach ($surveyor_map as $key => $client_id) {
            $id_cursor = self::$permit_id_start[$key];
            $addedfrom = $admin_map[$client_id] ?? $this->addedfrom;

            foreach (self::$groups as $groupid) {
                $publish   = $this->_publish_date();
                $expired   = $this->_expired_date($publish);
                $status    = $this->_status($expired);
                $assessors = $assessors_for_group($key, $groupid);

                $this->insert('surveyor_permits', [
                    'id'           => $id_cursor,
                    'surveyor_id'  => $client_id,
                    'groupid'      => $groupid,
                    'number'       => $this->_permit_number($publish),
                    'publish_date' => $publish,
                    'expired_date' => $expired,
                    'status'       => $status,
                    'file'         => '',
                    'addedfrom'    => $addedfrom,
                    'datecreated'  => $this->now,
                ]);

                foreach ($assessors as $pid) {
                    $this->insert('surveyor_permit_assessors', [
                        'permit_id'    => $id_cursor,
                        'personnel_id' => $pid,
                    ]);
                }

                $inserted[] = $id_cursor;
                $id_cursor++;
            }
        }

        return $inserted;
    }
}
