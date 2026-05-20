<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="panel-table-full">
                <div id="vueApp">
                    <div class="col-md-12 tw-mb-3">
                        <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('surveyors'); ?></h4>
                        <a href="#" 
							class="surveyors-total tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700"
							onclick="slideToggle('#stats-top'); init_surveyors_total(true); return false;">
								<?= _l('view_financial_stats'); ?>
						</a>
                    </div>                  
                    <div class="col-md-12">
                        <?php $this->load->view('admin/surveyors/quick_stats'); ?>
                    </div>
                    <?php $this->load->view('admin/surveyors/list_template'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Branch -->
<div class="modal fade" id="branch-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="branch-modal-title"><?= _l('new_surveyor_branch'); ?></h4>
            </div>
            <div class="modal-body">
                <form id="branch-form">
                    <input type="hidden" id="branch_id" value="">
                    <input type="hidden" id="branch_parent_id" value="0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label"><?= _l('branch_name'); ?> <span class="required">*</span></label>
                                <input type="text" id="branch_company" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label"><?= _l('branch_nitku'); ?></label>
                                <div class="checkbox checkbox-primary tw-mb-2">
                                    <input type="checkbox" id="branch_use_vat">
                                    <label for="branch_use_vat"><?= _l('branch_use_vat'); ?></label>
                                </div>
                                <input type="text" id="branch_nitku" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label"><?= _l('client_phonenumber'); ?></label>
                                <input type="text" id="branch_phonenumber" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group select-placeholder">
                                <label class="control-label"><?= _l('client_state'); ?></label>
                                <select id="branch_state" class="form-control selectpicker" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" data-live-search="true">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group select-placeholder">
                                <label class="control-label"><?= _l('client_city'); ?></label>
                                <select id="branch_city" class="form-control selectpicker" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" data-live-search="true">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label"><?= _l('client_postal_code'); ?></label>
                                <input type="text" id="branch_zip" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label"><?= _l('client_address'); ?></label>
                                <textarea id="branch_address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div id="branch-admin-section" style="display:none;">
                        <hr />
                        <h4 class="tw-font-semibold tw-mb-3"><?= _l('primary_contact_information'); ?></h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label"><?= _l('firstname'); ?> <span class="required">*</span></label>
                                    <input type="text" id="branch_admin_firstname" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label"><?= _l('lastname'); ?> <span class="required">*</span></label>
                                    <input type="text" id="branch_admin_lastname" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label"><?= _l('email_address'); ?> <span class="required">*</span></label>
                                    <input type="email" id="branch_admin_email" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label"><?= _l('password'); ?> <span class="required">*</span></label>
                                    <input type="password" id="branch_admin_password" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label"><?= _l('repeat_password'); ?> <span class="required">*</span></label>
                                    <input type="password" id="branch_admin_password2" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="button" class="btn btn-primary" id="branch-save-btn"><?= _l('submit'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
window.surveyorBranchLang = {
    edit_branch:                  '<?= _l('edit_branch'); ?>',
    new_surveyor_branch:          '<?= _l('new_surveyor_branch'); ?>',
    branch_name:                  '<?= _l('branch_name'); ?>',
    primary_contact_information:  '<?= _l('primary_contact_information'); ?>',
    passwords_not_match:          '<?= _l('passwords_not_match'); ?>',
    confirm_action_prompt:        '<?= _l('confirm_action_prompt'); ?>',
    no_surveyor_selected:         '<?= _l('no_surveyor_selected'); ?>',
};

var hidden_columns = [1];

$(function() {
    initDataTable(
        '.table-surveyors',
        admin_url + 'surveyors/table',
        false,
        false,
        {},
        [0, 'asc']
    );
    init_surveyor();
    init_surveyor_branch();
});
</script>
</body>

</html>