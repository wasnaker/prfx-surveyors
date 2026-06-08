<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $w = get_surveyors_widget_data(); $widget_id = create_widget_id(); ?>
<div class="widget relative surveyors widget-<?php echo $widget_id; ?>" id="widget-<?php echo $widget_id; ?>" data-name="<?php echo _l('surveyors'); ?>">
    <div class="panel_s">
        <div class="panel-body <?php echo $w->theme; ?>-padding">
            <div class="widget-dragger"></div>
                <div class="metronic materialize">

                    <div class="widget-title-bar <?php echo $w->theme; ?>-title">
                        <i class="fa fa-map-marker widget-title-icon"></i>
                        <h4 class="widget-title"><?php echo _l('surveyors'); ?></h4>
                    </div>

                    <div class="widget-content">
                        <div class="text-3xl widget-number">
                            <?php echo $w->total; ?>
                        </div>
                        <h4 class="widget-title-alt"><?php echo _l('surveyors'); ?></h4>
                        <p class="text-xs subtitle"><?php echo _l('total'); ?></p>
                    </div>
                </div>
        </div>
    </div>
</div>
