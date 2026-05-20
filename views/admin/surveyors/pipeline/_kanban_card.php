<?php defined('BASEPATH') or exit('No direct script access allowed');
if ($surveyor['status'] == $status) { ?>
<li data-surveyor-id="<?= e($surveyor['id']); ?>"
    class="<?= $surveyor['quotationid'] != null ? 'not-sortable' : ''; ?>">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-font-semibold tw-text-base pipeline-heading tw-mb-0.5">
                    <a href="<?= admin_url('surveyors/list_surveyors/' . $surveyor['id']); ?>"
                        class="tw-text-neutral-700 hover:tw-text-neutral-900 active:tw-text-neutral-900"
                        onclick="surveyor_pipeline_open(<?= e($surveyor['id']); ?>); return false;">
                        <?= e(format_surveyor_number($surveyor['id'])); ?>
                    </a>
                    <?php if (can_do_on_entity('edit', 'surveyors', (int) $surveyor['id'], 'surveyor')) { ?>
                    <a href="<?= admin_url('surveyors/surveyor/' . $surveyor['id']); ?>"
                        target="_blank" class="pull-right tw-font-medium">
                        <small>
                            <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                        </small>
                    </a>
                    <?php } ?>
                </h4>
                <span class="tw-inline-block tw-w-full tw-mb-2">
                    <a href="<?= admin_url('clients/client/' . $surveyor['clientid']); ?>"
                        target="_blank">
                        <?= e($surveyor['company']); ?>
                    </a>
                </span>
            </div>
            <div class="col-md-12">
                <div class="tw-flex">
                    <div class="tw-grow">
                        <p class="tw-mb-0 tw-text-sm tw-text-neutral-700">
                            <span class="tw-text-neutral-500">
                                <?= _l('surveyor_total'); ?>:
                            </span>
                            <?= e(app_format_money($surveyor['total'], $surveyor['currency_name'])); ?>
                        </p>
                        <p class="tw-mb-0 tw-text-sm tw-text-neutral-700">
                            <span class="tw-text-neutral-500">
                                <?= _l('surveyor_data_date'); ?>:
                            </span>
                            <?= e(_d($surveyor['date'])); ?>
                        </p>
                        <?php if (is_date($surveyor['expirydate']) || ! empty($surveyor['expirydate'])) { ?>
                        <p class="tw-mb-0 tw-text-sm tw-text-neutral-700">
                            <span class="tw-text-neutral-500">
                                <?= _l('surveyor_data_expiry_date'); ?>:
                            </span>
                            <?= e(_d($surveyor['expirydate'])); ?>
                        </p>
                        <?php } ?>
                    </div>
                    <div class="tw-shrink-0 text-right">
                        <small>
                            <i class="fa fa-paperclip"></i>
                            <?= _l('surveyor_notes'); ?>:
                            <?= total_rows(db_prefix() . 'notes', [
                                'rel_id'   => $surveyor['id'],
                                'rel_type' => 'surveyor',
                            ]); ?>
                        </small>
                    </div>
                    <?php $tags = get_tags_in($surveyor['id'], 'surveyor'); ?>
                    <?php if (count($tags) > 0) { ?>
                    <div class="kanban-tags tw-text-sm tw-inline-flex">
                        <?= render_tags($tags); ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</li>
<?php } ?>