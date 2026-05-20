<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="col-md-12">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <div class="tw-flex tw-items-center tw-gap-2">
            <?php
            $_me           = get_staff(get_staff_user_id());
            $_is_cust_user = $_me && $_me->client_type === 'surveyor';
            ?>
            <?php if (staff_can('create', 'surveyors') && !$_is_cust_user) { ?>
            <a href="<?= admin_url('surveyors/surveyor'); ?>" class="btn btn-primary">
                <i class="fa fa-plus tw-mr-1"></i><?= _l('create_new_surveyor'); ?>
            </a>
            <?php } ?>
            <?php
            $_show_branch_btn = false;
            if (staff_can('create', 'surveyors')) {
                if ($_is_cust_user) {
                    // Surveyor entity user: only show if their company is active
                    $_my_client = get_instance()->db->get_where(db_prefix() . 'clients', ['userid' => (int) $_me->client_id])->row();
                    $_show_branch_btn = $_my_client && $_my_client->active == 1;
                } else {
                    $_show_branch_btn = true;
                }
            }
            ?>
            <?php if ($_show_branch_btn) { ?>
            <a href="#" class="btn btn-default" onclick="var cid=$('input[name=surveyorid]').val(); if(cid){open_branch_form(cid);}else{alert_float('warning',(window.surveyorBranchLang||{}).no_surveyor_selected||'Select a surveyor first');} return false;">
                <i class="fa fa-plus tw-mr-1"></i><?= _l('surveyor_branch'); ?>
            </a>
            <?php } ?>
        </div>
        <div class="tw-flex tw-items-center tw-gap-2">
            <a href="#" class="btn btn-default btn-with-tooltip sm:!tw-px-3 toggle-small-view hidden-xs"
                onclick="toggle_small_view('.table-surveyors','#surveyor'); return false;"
                data-toggle="tooltip" title="<?= _l('toggle_full_view'); ?>">
                <i class="fa fa-angle-double-left"></i>
            </a>
        </div>
    </div>

    <div class="row tw-mt-2">
        <div class="col-md-12" id="small-table">
            <div class="panel_s">
                <div class="panel-body">
                    <?= form_hidden('surveyorid', isset($surveyorid) ? $surveyorid : ''); ?>
                    <?php $this->load->view('admin/surveyors/table_html'); ?>
                </div>
            </div>
        </div>
        <div class="col-md-7 small-table-right-col">
            <div id="surveyor" class="hide"></div>
        </div>
    </div>
</div>
