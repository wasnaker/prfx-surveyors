<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APP_MODULES_PATH . 'demo/database/seeds/BaseSeeder.php';

/**
 * Seeds pending surveyor permits for the 26 branch surveyors
 * that have no association registration (Condition-3 simulation).
 *
 * - Status: always 'pending' (awaiting platform approval)
 * - Groups: 2-3 random from tblitems_groups per branch
 * - Number format: matches existing permits (5/NNNNN/AS.01.02/MM/YYYY)
 * - publish_date / expired_date: NULL (not yet issued)
 */
class BranchSurveyorPermitsSeeder extends BaseSeeder
{
    private static $roman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];

    private function _permit_number(): string
    {
        $m     = rand(1, 12);
        $y     = (int) date('Y');
        $roman = self::$roman[$m - 1];
        $nomor = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        return '5/' . $nomor . '/AS.01.02/' . $roman . '/' . $y;
    }

    public function run(): array
    {
        // Branches with no association registration
        $branches = $this->db->query("
            SELECT c.userid, c.company_id
            FROM " . db_prefix() . "clients c
            WHERE c.client_type = 'surveyor'
              AND c.userid NOT IN (
                  SELECT DISTINCT surveyor_id FROM " . db_prefix() . "surveyors_associations
              )
        ")->result_array();

        if (empty($branches)) { return []; }

        // All available group IDs
        $all_groups = array_column(
            $this->db->get(db_prefix() . 'items_groups')->result_array(),
            'id'
        );

        // Branch admin map: client_id → staffid (is_branch_owner=1 or is_entity_owner=1)
        $admin_map = [];
        foreach ($this->db
            ->where('client_type', 'surveyor')
            ->where_in('is_entity_owner', [0, 1])
            ->get(db_prefix() . 'staff')->result() as $row) {
            if (!isset($admin_map[(int)$row->client_id])) {
                $admin_map[(int)$row->client_id] = (int)$row->staffid;
            }
        }
        // Also include branch owners
        foreach ($this->db
            ->where('client_type', 'surveyor')
            ->where('is_branch_owner', 1)
            ->get(db_prefix() . 'staff')->result() as $row) {
            $admin_map[(int)$row->client_id] = (int)$row->staffid;
        }

        $inserted = [];

        foreach ($branches as $branch) {
            $branch_id = (int) $branch['userid'];
            $addedfrom = $admin_map[$branch_id] ?? $this->addedfrom;

            // 2-3 random groups
            shuffle($all_groups);
            $groups = array_slice($all_groups, 0, rand(2, 3));

            foreach ($groups as $groupid) {
                $id = $this->insert('surveyor_permits', [
                    'surveyor_id'  => $branch_id,
                    'groupid'      => $groupid,
                    'number'       => $this->_permit_number(),
                    'publish_date' => null,
                    'expired_date' => null,
                    'status'       => 'pending',
                    'file'         => '',
                    'addedfrom'    => $addedfrom,
                    'datecreated'  => $this->now,
                ]);
                $inserted[] = $id;
            }
        }

        return $inserted;
    }
}
