<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$total_surveyors = $GLOBALS['CI']->db->where('client_type', 'surveyor')->count_all_results('tblclients');
?>
<div class="widget-box widget materialize-widget-container widget-unified"
     id="widget-<?php echo create_widget_id(); ?>"
     data-name="<?php echo _l('surveyors'); ?>"
     style="--widget-bg-color: #e8f5e9; --widget-number-color: #388e3c; --widget-title-color: #388e3c;">
    <div class="widget-head materialize-widget widget-unified-header">
        <h4 class="widget-unified-title"><?php echo _l('surveyors'); ?></h4>
        <div class="widget-dragger"></div>
    </div>
    <div class="widget-body materialize-widget-body widget-unified-body">
        <div class="panel_s">
            <div class="panel-body padding-10 widget-unified-content">
                <div class="text-3xl materialize-number-40 widget-unified-number">
                    <?php echo $total_surveyors; ?>
                </div>
                <h4 class="widget-unified-title-alt">
                    <?php echo _l('surveyors'); ?>
                </h4>
                <p class="text-xs materialize-subtitle"><?php echo _l('total'); ?></p>
            </div>
        </div>
    </div>
</div>
