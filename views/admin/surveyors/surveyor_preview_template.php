<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?= form_hidden('_attachment_sale_id', $surveyor->userid); ?>
<?= form_hidden('_attachment_sale_type', 'surveyor'); ?>
<div class="col-md-12 no-padding">
    <div class="panel_s">
        <div class="panel-body">
            <div class="horizontal-scrollable-tabs preview-tabs-top panel-full-width-tabs">
                <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                <div class="horizontal-tabs">
                    <ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#tab_surveyor" aria-controls="tab_surveyor" role="tab" data-toggle="tab">
                                <?= _l('surveyor'); ?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip"
                            title="<?= _l('surveyor_view_activity_tooltip'); ?>">
                            <a href="#tab_activity" aria-controls="tab_activity" role="tab" data-toggle="tab">
                                <?php if (! is_mobile()) { ?>
                                <i class="fa fa-history" aria-hidden="true"></i>
                                <?php } else { ?>
                                <?= _l('surveyor_view_activity_tooltip'); ?>
                                <?php } ?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip"
                            title="<?= _l('surveyor_reminders'); ?>">
                            <a href="#tab_reminders"
                                onclick="initDataTable('.table-reminders', admin_url + 'misc/get_reminders/' + <?= $surveyor->userid; ?> + '/' + 'surveyor', undefined, undefined, undefined,[1,'asc']); return false;"
                                aria-controls="tab_reminders" role="tab" data-toggle="tab">
                                <?php if (! is_mobile()) { ?>
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                <?php } else { ?>
                                <?= _l('surveyor_reminders'); ?>
                                <?php } ?>
                                <?php
                        $total_reminders = total_rows(
                            db_prefix() . 'reminders',
                            [
                                'isnotified' => 0,
                                'staff'      => get_staff_user_id(),
                                'rel_type'   => 'surveyor',
                                'rel_id'     => $surveyor->userid,
                            ]
                        );
if ($total_reminders > 0) {
    echo '<span class="badge">' . $total_reminders . '</span>';
}
?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip"
                            title="<?= _l('surveyor_notes'); ?>"
                            class="tab-separator">
                            <a href="#tab_notes"
                                onclick="get_sales_notes(<?= e($surveyor->userid); ?>,'surveyors'); return false"
                                aria-controls="tab_notes" role="tab" data-toggle="tab">
                                <?php if (! is_mobile()) { ?>
                                <i class="fa-regular fa-sticky-note" aria-hidden="true"></i>
                                <?php } else { ?>
                                <?= _l('surveyor_notes'); ?>
                                <?php } ?>
                                <span class="notes-total">
                                    <?php if ($totalNotes > 0) { ?>
                                    <span class="badge"><?= e($totalNotes); ?></span>
                                    <?php } ?>
                                </span>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip"
                            title="<?= _l('emails_tracking'); ?>"
                            class="tab-separator">
                            <a href="#tab_emails_tracking" aria-controls="tab_emails_tracking" role="tab"
                                data-toggle="tab">
                                <?php if (! is_mobile()) { ?>
                                <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>
                                <?php } else { ?>
                                <?= _l('emails_tracking'); ?>
                                <?php } ?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip"
                            data-title="<?= _l('view_tracking'); ?>"
                            class="tab-separator">
                            <a href="#tab_views" aria-controls="tab_views" role="tab" data-toggle="tab">
                                <?php if (! is_mobile()) { ?>
                                <i class="fa fa-eye"></i>
                                <?php } else { ?>
                                <?= _l('view_tracking'); ?>
                                <?php } ?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip"
                            data-title="<?= _l('toggle_full_view'); ?>"
                            class="tab-separator toggle_view">
                            <a href="#" onclick="small_table_full_view(); return false;">
                                <i class="fa fa-expand"></i>
                            </a>
                        </li>
                        <?php hooks()->do_action('after_admin_surveyor_preview_template_tab_menu_last_item', $surveyor); ?>
                    </ul>
                </div>
            </div>
            <?php
            $_me_staff    = get_staff(get_staff_user_id());
            $_client_type = $_me_staff ? ($_me_staff->client_type ?? '') : '';
            $_can_edit    = can_do_on_entity('edit', 'surveyors', (int) $surveyor->userid, 'surveyor');
            ?>

            <!-- Company name — always visible regardless of active tab -->
            <div class="tw-mt-4 tw-mb-3">
                <h2 class="tw-text-2xl tw-font-bold tw-text-neutral-800 tw-m-0 tw-leading-tight">
                    <?= e($surveyor->company); ?>
                </h2>
            </div>

            <div class="row mtop20">
                <div class="col-md-3">
                    <span id="surveyor-status-badge"><?= format_surveyor_status($surveyor->active == 1 ? 'active' : 'inactive', 'mtop5 inline-block'); ?></span>
                </div>
                <div class="col-md-9">
                    <div class="visible-xs">
                        <div class="mtop10"></div>
                    </div>
                    <div class="pull-right _buttons">
                        <?php if ($_can_edit) { ?>
                        <a href="<?= admin_url('surveyors/surveyor/' . $surveyor->userid); ?>"
                            class="btn btn-default btn-with-tooltip sm:!tw-px-3" data-toggle="tooltip"
                            title="<?= _l('edit_surveyor_tooltip'); ?>"
                            data-placement="bottom"><i class="fa-regular fa-pen-to-square"></i></a>
                        <?php } ?>
                        <div class="btn-group">
                            <a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false"><i
                                    class="fa-regular fa-file-pdf"></i><?= is_mobile() ? ' PDF' : ''; ?>
                                <span class="caret"></span></a>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li class="hidden-xs">
                                    <a
                                        href="<?= admin_url('surveyors/pdf/' . $surveyor->userid . '?output_type=I'); ?>">
                                        <?= _l('view_pdf'); ?>
                                    </a>
                                </li>
                                <li class="hidden-xs">
                                    <a href="<?= admin_url('surveyors/pdf/' . $surveyor->userid . '?output_type=I'); ?>"
                                        target="_blank">
                                        <?= _l('view_pdf_in_new_window'); ?>
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="<?= admin_url('surveyors/pdf/' . $surveyor->userid); ?>">
                                        <?= _l('download'); ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= admin_url('surveyors/pdf/' . $surveyor->userid . '?print=true'); ?>"
                                        target="_blank">
                                        <?= _l('print'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default pull-left dropdown-toggle"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?= _l('more'); ?>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <?php hooks()->do_action('after_surveyor_view_as_client_link', $surveyor); ?>
                                <?php if (staff_can('create', 'surveyors')) { ?>
                                <li>
                                    <a
                                        href="<?= admin_url('surveyors/copy/' . $surveyor->userid); ?>">
                                        <?= _l('copy_surveyor'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <?php if (staff_can('delete', 'surveyors')) { ?>
                                <?php
                                               if ((get_option('delete_only_on_last_surveyor') == 1 && is_last_surveyor($surveyor->userid))
                                                   || (get_option('delete_only_on_last_surveyor') == 0)) { ?>
                                <li>
                                    <a href="<?= admin_url('surveyors/delete/' . $surveyor->userid); ?>"
                                        class="text-danger delete-text _delete">
                                        <?= _l('delete_surveyor_tooltip'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
            <hr class="hr-panel-separator" />
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane ptop10 active" id="tab_surveyor">
                    <?php if (isset($surveyor->scheduled_email) && $surveyor->scheduled_email) { ?>
                    <div class="alert alert-warning">
                        <?= e(_l('invoice_will_be_sent_at', _dt($surveyor->scheduled_email->scheduled_at))); ?>
                        <?php if ($_can_edit) { ?>
                        <a href="#"
                            onclick="edit_surveyor_scheduled_email(<?= $surveyor->scheduled_email->id; ?>); return false;">
                            <?= _l('edit'); ?>
                        </a>
                        <?php } ?>
                    </div>
                    <?php } ?>
                    <?php
                    // Profile completeness — shown when company is inactive (for both admin and surveyor user)
                    $_me_staff = get_staff(get_staff_user_id());
                    $_is_own_surveyor = $_me_staff && $_me_staff->client_type === 'surveyor'
                        && (int)$_me_staff->client_id === (int)$surveyor->userid;
                    $_show_completeness = ($surveyor->active == 0) && (is_admin() || $_is_own_surveyor);

                    if ($_show_completeness) :
                        $_checks = [
                            _l('surveyor_vat')        => !empty($surveyor->vat),
                            _l('client_phonenumber')  => !empty($surveyor->phonenumber),
                            _l('client_address')      => !empty($surveyor->address),
                            _l('client_state')        => !empty($surveyor->state),
                            _l('client_city')         => !empty($surveyor->city),
                            _l('billing_address')     => !empty($surveyor->billing_street) && !empty($surveyor->billing_city) && !empty($surveyor->billing_state),
                            _l('surveyor_logo_light') => !empty($surveyor->logo_light) || !empty($surveyor->logo_dark),
                            _l('surveyor_npwp_file')  => !empty($surveyor->npwp_file),
                        ];
                        $_total    = count($_checks);
                        $_filled   = count(array_filter($_checks));
                        $_percent  = (int) round(($_filled / $_total) * 100);
                        $_missing  = array_keys(array_filter($_checks, fn($v) => !$v));
                        $_bar_class = $_percent === 100 ? 'success' : ($_percent >= 60 ? 'warning' : 'danger');
                    ?>
                    <div class="tw-mb-4">
                        <div class="tw-flex tw-items-center tw-justify-between tw-mb-1">
                            <span class="tw-text-sm tw-font-medium tw-text-neutral-600">
                                <?= _l('profile_completeness'); ?>
                            </span>
                            <span class="tw-text-sm tw-font-semibold text-<?= $_bar_class; ?>">
                                <?= $_filled; ?>/<?= $_total; ?> &mdash; <?= $_percent; ?>%
                            </span>
                        </div>
                        <div class="progress tw-mb-2" style="height:8px;margin-bottom:6px;">
                            <div class="progress-bar progress-bar-<?= $_bar_class; ?>"
                                style="width:<?= $_percent; ?>%;"></div>
                        </div>
                        <?php if (!empty($_missing)) { ?>
                        <div class="tw-text-xs tw-text-neutral-500">
                            <span class="tw-font-medium"><?= _l('profile_missing'); ?>:</span>
                            <?php foreach ($_missing as $_field) { ?>
                            <span class="label label-danger tw-mr-1 tw-mb-1"><?= e($_field); ?></span>
                            <?php } ?>
                        </div>
                        <?php } else { ?>
                        <div class="tw-text-xs tw-text-success">
                            <i class="fa fa-check-circle tw-mr-1"></i><?= _l('profile_complete_ready'); ?>
                        </div>
                        <?php } ?>
                    </div>
                    <?php endif; ?>

                    <div id="surveyor-preview">
                        <div class="row">
                            <div class="col-md-6 col-sm-6">
                                <?php $tags = get_tags_in($surveyor->userid, 'surveyor'); ?>
                                <?php if (count($tags) > 0) { ?>
                                <p class="tw-mb-1">
                                    <i class="fa fa-tag text-muted tw-mr-1" data-toggle="tooltip"
                                       data-title="<?= e(implode(', ', $tags)); ?>"></i>
                                    <?php foreach ($tags as $tag) { ?>
                                    <span class="label label-default tw-mr-1"><?= e($tag); ?></span>
                                    <?php } ?>
                                </p>
                                <?php } ?>
                                <address class="tw-text-neutral-500">
                                    <?php if (!empty($surveyor->phonenumber)) { ?>
                                    <p class="no-mbot"><?= e($surveyor->phonenumber); ?></p>
                                    <?php } ?>
                                    <?php if (!empty($surveyor->website)) { ?>
                                    <p class="no-mbot"><a href="<?= e($surveyor->website); ?>" target="_blank"><?= e($surveyor->website); ?></a></p>
                                    <?php } ?>
                                    <?php if (!empty($surveyor->address)) { ?>
                                    <p class="no-mbot"><?= nl2br(e($surveyor->address)); ?></p>
                                    <?php } ?>
                                    <?php if (!empty($surveyor->city) || !empty($surveyor->state)) { ?>
                                    <p class="no-mbot"><?= e(implode(', ', array_filter([$surveyor->city ?? '', $surveyor->state ?? '']))); ?></p>
                                    <?php } ?>
                                </address>
                            </div>
                        </div>
                        <div class="row mtop15">
                            <div class="col-md-12">
                                <table class="table table-condensed no-margin">
                                    <tbody>
                                        <?php if (!empty($surveyor->vat)) { ?>
                                        <tr>
                                            <td class="tw-w-1/3 text-muted"><?= _l('client_vat_number'); ?></td>
                                            <td><?= e($surveyor->vat); ?></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if (!empty($surveyor->state)) { ?>
                                        <tr>
                                            <td class="text-muted"><?= _l('client_state'); ?></td>
                                            <td><?= e($surveyor->state); ?></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if (!empty($surveyor->city)) { ?>
                                        <tr>
                                            <td class="text-muted"><?= _l('client_city'); ?></td>
                                            <td><?= e($surveyor->city); ?></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if (!empty($surveyor->zip)) { ?>
                                        <tr>
                                            <td class="text-muted"><?= _l('client_postal_code'); ?></td>
                                            <td><?= e($surveyor->zip); ?></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if (!empty($country_name)) { ?>
                                        <tr>
                                            <td class="text-muted"><?= _l('client_country'); ?></td>
                                            <td><?= e($country_name); ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if (!empty($coordinates)): ?>
                        <div class="row mtop15">
                            <div class="col-md-12">
                                <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                                    <span class="tw-text-sm tw-font-semibold tw-text-neutral-600">
                                        <i class="fa fa-map-marker tw-mr-1"></i><?= _l('location'); ?>
                                    </span>
                                    <a href="https://www.google.com/maps?q=<?= $coordinates->latitude; ?>,<?= $coordinates->longitude; ?>"
                                       target="_blank" rel="noopener"
                                       class="tw-text-xs tw-text-neutral-400 hover:tw-text-neutral-600">
                                        <i class="fa fa-external-link tw-mr-1"></i>Google Maps
                                    </a>
                                </div>
                                <?php if (!empty($coordinates->address)): ?>
                                <p class="tw-text-sm tw-text-neutral-500 tw-mb-2"><?= e($coordinates->address); ?></p>
                                <?php endif; ?>
                                <div id="map-preview-<?= $surveyor->userid; ?>"
                                     style="height:180px; border:1px solid #ddd; border-radius:4px; background:#f5f5f5;">
                                </div>
                            </div>
                        </div>
                        <script>
                        (function(){
                            var _lat = <?= (float) $coordinates->latitude; ?>;
                            var _lng = <?= (float) $coordinates->longitude; ?>;
                            var _el  = document.getElementById('map-preview-<?= $surveyor->userid; ?>');
                            function _initMap() {
                                if (!_el || !window.L) return;
                                delete L.Icon.Default.prototype._getIconUrl;
                                L.Icon.Default.mergeOptions({
                                    iconUrl:       '<?= module_dir_url('apps','assets/js/leaflet/images/marker-icon.png'); ?>',
                                    iconRetinaUrl: '<?= module_dir_url('apps','assets/js/leaflet/images/marker-icon-2x.png'); ?>',
                                    shadowUrl:     '<?= module_dir_url('apps','assets/js/leaflet/images/marker-shadow.png'); ?>',
                                });
                                var m = L.map(_el, { zoomControl:true, dragging:false, scrollWheelZoom:false })
                                          .setView([_lat, _lng], 15);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution:'&copy; OpenStreetMap', maxZoom:19
                                }).addTo(m);
                                L.marker([_lat, _lng]).addTo(m);
                                setTimeout(function(){ m.invalidateSize(); }, 300);
                            }
                            if (window.L) {
                                _initMap();
                            } else {
                                var _css = document.createElement('link');
                                _css.rel = 'stylesheet';
                                _css.href = '<?= module_dir_url('apps','assets/js/leaflet/leaflet.css'); ?>';
                                document.head.appendChild(_css);
                                var _js = document.createElement('script');
                                _js.src = '<?= module_dir_url('apps','assets/js/leaflet/leaflet.js'); ?>';
                                _js.onload = _initMap;
                                document.head.appendChild(_js);
                            }
                        })();
                        </script>
                        <?php endif; ?>

                    </div>
                </div>

                <?php
                $_preview_doc_types = [
                    'nib'               => ['label' => _l('doc_type_nib'),            'indent' => false],
                    'npwp'              => ['label' => _l('doc_type_npwp'),           'indent' => false],
                    'akte_pendirian'    => ['label' => _l('doc_type_akte_pendirian'), 'indent' => false, 'has_notary' => true],
                    'akte_pendirian_sk' => ['label' => _l('doc_sk_kemenkumham'),      'indent' => true],
                    'akte_perubahan'    => ['label' => _l('doc_type_akte_perubahan'), 'indent' => false, 'has_notary' => true],
                    'akte_perubahan_sk' => ['label' => _l('doc_sk_kemenkumham'),      'indent' => true],
                    'bpjs_tk'           => ['label' => _l('doc_type_bpjs_tk'),        'indent' => false],
                    'bpjs_kes'          => ['label' => _l('doc_type_bpjs_kes'),       'indent' => false],
                ];
                $legal_docs = $legal_docs ?? [];
                ?>
                <div class="row mtop15">
                    <div class="col-md-12">
                        <p class="tw-font-semibold tw-text-sm tw-text-neutral-600 tw-mb-2"><?= _l('legal_documents'); ?></p>
                        <table class="table table-condensed no-margin">
                            <tbody>
                                <?php foreach ($_preview_doc_types as $_dt => $_cfg):
                                    $_doc    = $legal_docs[$_dt] ?? null;
                                    $_has_no = !empty($_doc->doc_number);
                                    $_has_fi = !empty($_doc->file);
                                    $_meta   = (!empty($_doc->meta) && !empty($_cfg['has_notary']))
                                        ? json_decode($_doc->meta, true) : [];
                                ?>
                                <tr<?= !empty($_cfg['indent']) ? ' class="tw-bg-neutral-50"' : ''; ?>>
                                    <td class="tw-w-2/5 text-muted tw-text-xs tw-align-top<?= !empty($_cfg['indent']) ? ' tw-pl-6' : ''; ?>">
                                        <?= !empty($_cfg['indent']) ? '<i class="fa fa-level-up fa-rotate-90 tw-mr-1 tw-text-neutral-400"></i>' : ''; ?>
                                        <?= $_cfg['label']; ?>
                                    </td>
                                    <td class="tw-text-xs tw-align-top">
                                        <?= $_has_no ? '<span class="tw-font-medium">' . e($_doc->doc_number) . '</span>' : '<span class="text-muted">—</span>'; ?>
                                        <?php if (!empty($_meta['notary_name'])): ?>
                                            <div class="text-muted tw-mt-0.5"><?= _l('doc_notary_name'); ?>: <?= e($_meta['notary_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tw-text-center tw-align-top">
                                        <?php if ($_has_fi): ?>
                                            <a href="<?= admin_url('surveyors/download_legal_doc/' . $surveyor->userid . '/' . $_dt); ?>"
                                                class="btn btn-xs btn-default" target="_blank" data-toggle="tooltip" title="<?= _l('download'); ?>">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="label label-danger tw-text-xs"><?= _l('missing'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div role="tabpanel" class="tab-pane" id="tab_reminders">
                    <a href="#" data-toggle="modal" class="btn btn-primary"
                        data-target=".reminder-modal-surveyor-<?= e($surveyor->userid); ?>"><i
                            class="fa-regular fa-bell"></i>
                        <?= _l('surveyor_set_reminder_title'); ?></a>
                    <hr />
                    <?php render_datatable([_l('reminder_description'), _l('reminder_date'), _l('reminder_staff'), _l('reminder_is_notified')], 'reminders'); ?>
                    <?php $this->load->view('admin/includes/modals/reminder', ['id' => $surveyor->userid, 'name' => 'surveyor', 'members' => $members, 'reminder_title' => _l('surveyor_set_reminder_title')]); ?>
                </div>
                <div role="tabpanel" class="tab-pane ptop10" id="tab_emails_tracking">
                    <?php $this->load->view('admin/includes/emails_tracking', [
                        'tracked_emails' => get_tracked_emails($surveyor->userid, 'surveyor'),
                    ]); ?>
                </div>
                <div role="tabpanel" class="tab-pane" id="tab_notes">
                    <?= form_open(admin_url('surveyors/add_note/' . $surveyor->userid), ['id' => 'surveyor-notes', 'class' => 'surveyor-notes-form']); ?>
                    <?= render_textarea('description'); ?>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary mtop15 mbot15">
                            <?= _l('surveyor_add_note'); ?>
                        </button>
                    </div>
                    <?= form_close(); ?>
                    <hr />
                    <div class="mtop20" id="sales_notes_area">
                    </div>
                </div>

                <div role="tabpanel" class="tab-pane" id="tab_activity">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="activity-feed">
                                <?php foreach ($activity as $activity) {
                                    $_custom_data = false; ?>
                                <div class="feed-item"
                                    data-sale-activity-id="<?= e($activity['id']); ?>">
                                    <div class="date">
                                        <span class="text-has-action" data-toggle="tooltip"
                                            data-title="<?= e(_dt($activity['date'])); ?>">
                                            <?= e(time_ago($activity['date'])); ?>
                                        </span>
                                    </div>
                                    <div class="text">
                                        <?php if (is_numeric($activity['staffid']) && $activity['staffid'] != 0) { ?>
                                        <a
                                            href="<?= admin_url('profile/' . $activity['staffid']); ?>">
                                            <?= staff_profile_image($activity['staffid'], ['staff-profile-xs-image pull-left mright5']);
                                            ?>
                                        </a>
                                        <?php } ?>
                                        <?php
                                            $additional_data = '';
                                    if (! empty($activity['additional_data'])) {
                                        $additional_data = app_unserialize($activity['additional_data']);
                                        $i               = 0;

                                        foreach ((is_array($additional_data) ? $additional_data : []) as $data) {
                                            if (strpos($data, '<original_status>') !== false) {
                                                $original_status     = get_string_between($data, '<original_status>', '</original_status>');
                                                $additional_data[$i] = format_surveyor_status($original_status, '', false);
                                            } elseif (strpos($data, '<new_status>') !== false) {
                                                $new_status          = get_string_between($data, '<new_status>', '</new_status>');
                                                $additional_data[$i] = format_surveyor_status($new_status, '', false);
                                            } elseif (strpos($data, '<status>') !== false) {
                                                $status              = get_string_between($data, '<status>', '</status>');
                                                $additional_data[$i] = format_surveyor_status($status, '', false);
                                            } elseif (strpos($data, '<custom_data>') !== false) {
                                                $_custom_data = get_string_between($data, '<custom_data>', '</custom_data>');
                                                unset($additional_data[$i]);
                                            }
                                            $i++;
                                        }
                                    }

                                    $_formatted_activity = _l($activity['description'], $additional_data);

                                    if ($_custom_data !== false) {
                                        $_formatted_activity .= ' - ' . $_custom_data;
                                    }

                                    if (! empty($activity['full_name'])) {
                                        $_formatted_activity = e($activity['full_name']) . ' - ' . $_formatted_activity;
                                    }

                                    echo $_formatted_activity;

                                    // Show plain-text diff when additional_data is a raw string (not serialized)
                                    if (!empty($activity['additional_data']) && $additional_data === false) {
                                        echo '<pre class="tw-text-xs tw-text-neutral-500 tw-mt-1 tw-mb-0 tw-whitespace-pre-wrap tw-bg-neutral-50 tw-rounded tw-p-2">' . e($activity['additional_data']) . '</pre>';
                                    }

                                    if (is_admin()) {
                                        echo '<a href="#" class="pull-right text-muted" onclick="delete_sale_activity(' . $activity['id'] . '); return false;"><i class="fa-regular fa-trash-can"></i></a>';
                                    } ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane ptop10" id="tab_views">
                    <?php
                  $views_activity = get_views_tracking('surveyor', $surveyor->userid);
if (count($views_activity) === 0) {
    echo '<h4 class="tw-m-0 tw-text-base tw-font-medium tw-text-neutral-500">' . _l('not_viewed_yet', _l('surveyor_lowercase')) . '</h4>';
}

foreach ($views_activity as $activity) { ?>
                    <p class="text-success no-margin">
                        <?= _l('view_date') . ': ' . _dt($activity['date']); ?>
                    </p>
                    <p class="text-muted">
                        <?= _l('view_ip') . ': ' . $activity['view_ip']; ?>
                    </p>
                    <hr />
                    <?php } ?>
                </div>
                <?php hooks()->do_action('after_admin_surveyor_preview_template_tab_content_last_item', $surveyor); ?>
            </div>
        </div>
    </div>
</div>
<script>
    init_items_sortable(true);
    init_btn_with_tooltips();
    init_datepicker();
    init_selectpicker();
    init_form_reminder();
    init_tabs_scrollable();
    init_surveyor_notes();
</script>
