<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$total_surveyors = $GLOBALS['CI']->db->where('client_type', 'surveyor')->count_all_results('tblclients');
$theme = get_option('active_admin_theme'); 


?>
<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="<?php echo _l('surveyors'); ?>">
    <div class="panel_s">
        <div class="panel-body padding-10">
            <div class="widget-dragger"></div>
                <div class="metronic materialize">

                    <div class="widget-title-bar <?php echo $theme?>-title">
                        <h4 class="widget-title"><?php echo _l('surveyors'); ?></h4>
                    </div>

                    <div class="widget-content">
                        <div class="text-3xl widget-number">
                            <?php echo $total_surveyors; ?>
                        </div>
                        <h4 class="widget-title-alt"><?php echo _l('surveyors'); ?></h4>
                        <p class="text-xs subtitle"><?php echo _l('total'); ?></p>
                    </div>
                </div>
        </div>
    </div>
</div>
