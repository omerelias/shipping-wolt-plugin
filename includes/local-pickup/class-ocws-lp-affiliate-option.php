<?php

defined( 'ABSPATH' ) || exit;

class OCWS_LP_Affiliate_Option {

    const WP_OPTION_GROUP_COMMON = 'ocws_lp_common';
    const WP_OPTION_GROUP_DEFAULT = 'ocws_lp_default';
    const WP_OPTION_GROUP_AFFILIATE = 'ocws_lp_aff_[affid]';

    const OPTION_PREFIX_COMMON = 'ocws_lp_common_';
    const OPTION_PREFIX_DEFAULT = 'ocws_lp_default_';
    const OPTION_PREFIX_AFFILIATE = 'ocws_lp_aff_[affid]_';

    public $affiliate_options;
    public $general_options;

    public function __construct() {

        $this->init_pickup_options();
    }

    public static function get_affiliate_option_prefix($aff_id) {
        return (str_replace('[affid]', intval($aff_id), self::OPTION_PREFIX_AFFILIATE));
    }

    public static function get_affiliate_option_group($aff_id) {
        return (str_replace('[affid]', intval($aff_id), self::WP_OPTION_GROUP_AFFILIATE));
    }

    public function init_pickup_options() {

        $this->affiliate_options = array(

            array(
                'title' => __('Preorder days for local pickup', 'ocws'),
                'name' => 'preorder_days',
                'type' => 'text',
                'default' => 30,
                'callback' => ''
            ),
            array(
                'title' => __('Minimum wait times (in minutes) for local pickup', 'ocws'),
                'name' => 'min_wait_times',
                'type' => 'text',
                'default' => 30,
                'callback' => ''
            ),
            array(
                'title' => __('Local pickup price', 'ocws'),
                'name' => 'pickup_price',
                'type' => 'text',
                'default' => 0,
                'callback' => ''
            ),
            array(
                'title' => __('Pickup scheduling type', 'ocws'),
                'name' => 'pickup_scheduling_type',
                'type' => 'radio',
                'default' => 'weekly',
                'options' => array(
                    array(
                        'title' => __('Weekly', 'ocws'),
                        'value' => 'weekly'
                    ),
                    array(
                        'title' => __('By specific days', 'ocws'),
                        'value' => 'dates'
                    ),
                ),
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_scheduling_type')
            ),
            array(
                'title' => __('Pickup schedule', 'ocws'),
                'name' => 'pickup_schedule_weekly',
                'type' => 'hidden',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_schedule_weekly')
            ),
            array(
                'title' => __('Specific dates for pickup', 'ocws'),
                'name' => 'pickup_schedule_dates',
                'type' => 'hidden',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_schedule_dates')
            ),
            /*array(
                'title' => __('Latest hour to order pickup for today', 'ocws'),
                'name' => 'max_hour_for_today',
                'type' => 'text',
                'default' => '18:00',
                'callback' => ''
            ),*/
            /*array(
                'title' => __('Latest hour to order pickup for tomorrow', 'ocws'),
                'name' => 'max_hour_for_tomorrow',
                'type' => 'text',
                'default' => '23:00',
                'callback' => ''
            ),*/
            array(
                'title' => __('Exclude days of week for local pickup', 'ocws'),
                'name' => 'closing_weekdays',
                'type' => 'hidden',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_closing_weekdays')
            ),
            array(
                'title' => __('Exclude dates for local pickup', 'ocws'),
                'name' => 'closing_dates',
                'type' => 'hidden',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_closing_dates')
            ),

            //'max_products_per_slot',
        );

        $this->general_options = array(

            array(
                'title' => __('Pickup dates style', 'ocws'),
                'name' => 'dates_style',
                'type' => 'select',
                'default' => 'slider_style',
                'options' => array(
                    array(
                        'title' => __('Slider style'),
                        'value' => 'slider_style'
                    ),
                    array(
                        'title' => __('Calendar style'),
                        'value' => 'calendar_style'
                    ),
                ),
                'callback' => '',
            ),
            array(
                'title' => __('Hide pickup time slots on checkout', 'ocws'),
                'name' => 'show_dates_only',
                'type' => 'checkbox',
                'default' => '0',
                'callback' => '',
            ),
            array(
                'title' => __('Hide pickup time slot in new order email to admin', 'ocws'),
                'name' => 'hide_slot_in_admin_mail',
                'type' => 'checkbox',
                'default' => '0',
                'callback' => '',
            ),
            array(
                'title' => __('Show time slot start time only', 'ocws'),
                'name' => 'show_slot_start_only',
                'type' => 'checkbox',
                'default' => '0',
                'callback' => '',
            ),
            /*array(
                'title' => __('Latest hour to order pickup for today', 'ocws'),
                'name' => 'max_hour_for_today',
                'type' => 'text',
                'default' => '18:00',
                'callback' => ''
            ),*/
            array(
                'title' => __('Local pickup time slots title on checkout', 'ocws'),
                'name' => 'checkout_slots_title',
                'type' => 'text',
                'default' => __('Checkout local pickup title', 'ocws'),
                'callback' => '',
                'multilingual' => true
            ),
            array(
                'title' => __('Local pickup time slots description on checkout', 'ocws'),
                'name' => 'checkout_slots_description',
                'type' => 'textarea',
                'default' =>  __('Checkout local pickup text', 'ocws'),
                'callback' => '',
                'multilingual' => true
            ),
            array(
                'title' => __('Local pickup time slots title on popup', 'ocws'),
                'name' => 'popup_slots_title',
                'type' => 'text',
                'default' => __('Popup local pickup title', 'ocws'),
                'callback' => '',
                'multilingual' => true
            ),
            array(
                'title' => __('Local pickup time slots description on popup', 'ocws'),
                'name' => 'popup_slots_description',
                'type' => 'textarea',
                'default' =>  __('Popup local pickup text', 'ocws'),
                'callback' => '',
                'multilingual' => true
            ),
            /*array(
                'title' => __('Export local pickup summary for production', 'ocws'),
                'name' => '',
                'type' => 'subsection',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_subsection_capture')
            ),
            array(
                'title' => __('Choose attributes to show', 'ocws'),
                'name' => 'export_production_attributes_to_show',
                'type' => 'checkbox_group',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_export_production_attributes_to_show')
            ),
            array(
                'title' => __('Export local pickup product details for production', 'ocws'),
                'name' => '',
                'type' => 'subsection',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_subsection_capture')
            ),
            array(
                'title' => __('Choose attributes to show', 'ocws'),
                'name' => 'export_production_details_attributes_to_show',
                'type' => 'checkbox_group',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_export_production_details_attributes_to_show')
            ),
            array(
                'title' => __('Local pickup export settings', 'ocws'),
                'name' => '',
                'type' => 'subsection',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_subsection_capture')
            ),
            array(
                'title' => __('Add pages for export', 'ocws'),
                'name' => 'export_production_details_pages',
                'type' => 'checkbox_group',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_export_production_details_pages')
            ),
            array(
                'title' => __('Choose order statuses for local pickup export', 'ocws'),
                'name' => 'export_order_statuses',
                'type' => 'checkbox_group',
                'default' => '',
                'callback' => array('OCWS_LP_Affiliate_Option', 'output_pickup_export_order_statuses')
            ),
            array(
                'title' => __('Hide summary column', 'ocws'),
                'name' => 'export_hide_summary',
                'type' => 'checkbox',
                'default' => '',
                'callback' => '',
            ),
            array(
                'title' => __('Hide time slots column', 'ocws'),
                'name' => 'export_hide_time_slots',
                'type' => 'checkbox',
                'default' => '',
                'callback' => '',
            ),
            array(
                'title' => __('Hide order items column', 'ocws'),
                'name' => 'export_hide_order_items',
                'type' => 'checkbox',
                'default' => '',
                'callback' => '',
            ),
            array(
                'title' => __('Hide number of items column', 'ocws'),
                'name' => 'export_hide_items_number',
                'type' => 'checkboxp',
                'default' => '',
                'callback' => '',
            ),
            array(
                'title' => __('Hide order notes column', 'ocws'),
                'name' => 'export_hide_order_notes',
                'type' => 'checkbox',
                'default' => '',
                'callback' => '',
            ),*/

        );
    }

    public function register_pickup_options() {

        $languages = ocws_get_languages();

        //error_log('registering options');
        //error_log('languages:');
        //error_log(print_r($languages, 1));

        /* register common local pickup settings*/
        foreach ($this->general_options as $option) {

            if ($option['type'] != 'subsection') {

                if (isset($option['multilingual']) && $option['multilingual'] && is_array($languages)) {
                    foreach($languages as $language_code) {

                        //error_log('registering multilingual option:' . self::OPTION_PREFIX_COMMON . $option['name'] . '_' . $language_code);
                        register_setting( self::WP_OPTION_GROUP_COMMON, self::OPTION_PREFIX_COMMON . $option['name'] . '_' . $language_code, array('default' => $option['default']) );
                    }
                }

                register_setting( self::WP_OPTION_GROUP_COMMON, self::OPTION_PREFIX_COMMON . $option['name'], array('default' => $option['default']) );
            }
        }

        /* register affiliate settings */
        $affiliates_data_store = new OCWS_LP_Affiliates();
        $affiliates = $affiliates_data_store->db_get_affiliates();

        foreach ($this->affiliate_options as $option) {

            if ($option['type'] != 'subsection') {
                register_setting( self::WP_OPTION_GROUP_DEFAULT, self::OPTION_PREFIX_DEFAULT . $option['name'], array('default' => $option['default']) );

                foreach ($affiliates as $affiliate) {
                    if ($affiliate->aff_id) {

                        $aff_option_name = self::get_affiliate_option_prefix($affiliate->aff_id) . $option['name'];
                        $use_default_opt = $aff_option_name . '_ud';
                        register_setting( self::get_affiliate_option_group($affiliate->aff_id), $aff_option_name );
                        register_setting( self::get_affiliate_option_group($affiliate->aff_id), $use_default_opt, array( 'default' => '1' ) );
                    }
                }
            }
        }

    }

    public static function get_common_option($option_name, $default='') {

        $common_opt_name = self::OPTION_PREFIX_COMMON . $option_name;
        // Distinguish between `false` as a default, and not passing one.
        $passed_default = func_num_args() > 1;
        $common_opt_value = $passed_default? get_option($common_opt_name, $default) : get_option($common_opt_name);

        return new OCWS_LP_General_Option_Model($common_opt_name, $common_opt_value, $default);
    }

    public static function get_common_option_ml($option_name, $default = '')
    {
        $common_opt_name = self::OPTION_PREFIX_COMMON . $option_name;
        // Distinguish between `false` as a default, and not passing one.
        $passed_default = func_num_args() > 1;
        $common_opt_value = ($passed_default? get_option($common_opt_name, $default) : get_option($common_opt_name));
        error_log('get multilingual option: '. $common_opt_name . ' = ' . get_option($common_opt_name));

        $l = ocws_get_languages();
        $locale = get_locale();

        if (!empty($l)) {
            if ($locale) {
                $curr_language = (strlen($locale) > 2) ? substr($locale, 0, 2) : $locale;
                $common_opt_name = $common_opt_name . '_' . $curr_language;
                $common_opt_value = ($passed_default? get_option($common_opt_name, $default) : get_option($common_opt_name));
                error_log('get multilingual option: '. $common_opt_name . ' = ' . get_option($common_opt_name));
            }
        }
        return new OCWS_LP_General_Option_Model($common_opt_name, $common_opt_value, $default);
    }

    public static function get_default_option($option_name, $default='') {

        $default_opt_name = self::OPTION_PREFIX_DEFAULT . $option_name;
        $default_opt_value = get_option($default_opt_name, $default);

        return new OCWS_LP_Affiliate_Default_Option_Model($default_opt_name, $default_opt_value, $default);
    }

    public static function get_option($aff_id, $option_name, $default='') {

        $aff_option_name = self::get_affiliate_option_prefix($aff_id) . $option_name;
        $use_default_opt = $aff_option_name . '_ud';
        $use_default = get_option($use_default_opt, false);

        //error_log('use_default ('.$use_default_opt.'):' . print_r($use_default, 1));
        if ($use_default === false) {
            $use_default = '1';
        }

        $default_opt_name = self::OPTION_PREFIX_DEFAULT . $option_name;
        $default_value = get_option($default_opt_name, $default);
        if ($use_default === '1') {
            $option_value = $default_value;
        }
        else {
            $option_value = get_option($aff_option_name, $default);
        }
        return new OCWS_LP_Affiliate_Option_Model($aff_option_name, $option_value, $use_default === '1', $default_value);
        /*return array(
            'use_default' => $use_default,
            'option_value' => $option_value,
            'default' => $default_value,
            'option_name' => $aff_option_name
        );*/
    }

    public static function output_use_default_switch(OCWS_LP_Affiliate_Option_Model $option) {
        ?>

        <label>
            <input data-rel="<?php echo esc_attr( $option->option_name ) ?>" type="checkbox" name="<?php echo esc_attr( $option->option_name . '_ud' ) ?>"
                   class="use-default-switch" id="<?php echo esc_attr( $option->option_name . '_ud' ) ?>"
                   value="1" <?php if ($option->use_default) { ?> checked="checked"<?php } ?> />
            <?php echo __('Use default value', 'ocws') ?>
        </label>

        <?php
    }

    /*
     * output default options
     * */

    public static function output_default_checkbox_option(OCWS_LP_Affiliate_Default_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_html($option->option_name); ?>"
                           class="" id="<?php echo esc_html($option->option_name); ?>"
                           value="1" <?php if ($option->option_value === '1') { ?> checked="checked"<?php } ?> />
                </label>
            </td>
        </tr>
        <?php
    }

    public static function output_default_text_option(OCWS_LP_Affiliate_Default_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr id="<?php echo esc_html($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>
                <input type="text" name="<?php echo esc_attr( $option->option_name ) ?>" value="<?php echo esc_attr( $option->option_value ); ?>" />
            </td>
        </tr>
        <?php

    }

    public static function output_default_radio_option(OCWS_LP_Affiliate_Default_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr id="<?php echo esc_attr($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>
                <?php foreach ($opt['options'] as $o): ?>
                    <label class="as-block">
                        <input type="radio" name="<?php echo esc_attr($option->option_name); ?>" id="<?php echo esc_attr($o['name']); ?>"
                               value="<?php echo esc_attr($o['value']); ?>" <?php if ($option->option_value == $o['value']) { ?> checked="checked"<?php } ?> />
                        <?php echo esc_html($o['title']) ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php

    }

    public static function output_default_select_option(OCWS_LP_Affiliate_Default_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <select name="<?php echo esc_html($option->option_name); ?>">
                    <?php foreach ($opt['options'] as $option): ?>
                        <option
                            <?php if ($option->option_value == $option['value']): ?>selected<?php endif ?>
                            value="<?php echo $option['value'] ?>">
                            <?php echo $option['title']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }

    public static function output_default_textarea_option(OCWS_LP_Affiliate_Default_Option_Model $option, $opt, $show_option=true) {

        $languages = ocws_get_languages();
        $multilingual = (isset($opt['multilingual']) && $opt['multilingual']);
        ?>
        <tr id="<?php echo esc_attr($option->option_name) ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <?php
                if ($multilingual && !empty($languages)) {
                    foreach($languages as $language_code) {
                        ?>

                        <div><?php echo esc_html($language_code) ?></div>
                        <div>
                            <textarea name="<?php echo esc_attr($option->option_name) ?>_<?php echo $language_code ?>">
                                <?php echo esc_html( get_option($option->option_name.'_'.$language_code, $opt['default']) ); ?>
                            </textarea>
                        </div>

                        <?php
                    }
                }
                else {
                    ?>

                    <textarea name="<?php echo esc_attr($option->option_name) ?>">
                        <?php echo esc_html( $option->option_value ); ?>
                     </textarea>

                    <?php
                }
                ?>


            </td>
        </tr>
        <?php

    }

    /*
     * output affiliate options
     * */

    public static function output_affiliate_radio_option(OCWS_LP_Affiliate_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr id="<?php echo esc_attr($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>
                <?php foreach ($opt['options'] as $o): ?><span><?php //var_dump($o); ?></span>
                    <label class="as-block">
                        <input type="radio" name="<?php echo esc_attr($option->option_name); ?>" id="<?php echo esc_attr($o['name']); ?>"
                               value="<?php echo esc_attr($o['value']); ?>" <?php if ($option->option_value == $o['value']) { ?> checked="checked"<?php } ?>
                               data-def-value="<?php echo esc_attr( $option->default ); ?>"
                                <?php echo ($option->use_default? 'disabled="true"' : '') ?>/>
                        <?php echo esc_html($o['title']) ?>
                    </label>
                <?php endforeach; ?>

                <div>
                    <?php self::output_use_default_switch($option); ?>
                </div>
            </td>
        </tr>
        <?php

    }

    public static function output_affiliate_checkbox_option(OCWS_LP_Affiliate_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_html($option->option_name); ?>"
                           class="" id="<?php echo esc_html($option->option_name); ?>"
                           value="1" <?php if ($option->option_value === '1') { ?> checked="checked"<?php } ?>
                           data-default-value="<?php echo esc_attr( $option->default ); ?>"
                            <?php echo ($option->use_default? 'disabled="true"' : '') ?> />
                </label>

                <div>
                    <?php self::output_use_default_switch($option); ?>
                </div>
            </td>
        </tr>
        <?php
    }

    public static function output_affiliate_select_option(OCWS_LP_Affiliate_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr id="<?php echo esc_attr($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>
                <div>

                    <select name="<?php echo esc_html($option->option_name); ?>" data-default-value="<?php echo esc_attr( $option->default ); ?>" <?php echo ($option->use_default? 'disabled="true"' : '') ?>>
                        <?php foreach ($opt['options'] as $option): ?>
                            <option
                                <?php if ($option->option_value == $option['value']): ?>selected<?php endif ?>
                                value="<?php echo $option['value'] ?>">
                                <?php echo $option['title']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <div>
                    <?php self::output_use_default_switch($option); ?>
                </div>
            </td>
        </tr>
        <?php

    }

    public static function output_affiliate_textarea_option(OCWS_LP_Affiliate_Option_Model $option, $opt, $show_option=true) {

        $languages = ocws_get_languages();
        $multilingual = (isset($opt['multilingual']) && $opt['multilingual']);
        ?>
        <tr id="<?php echo esc_attr($option->option_name) ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <div>

                    <?php
                    if ($multilingual && !empty($languages)) {
                        foreach($languages as $language_code) {
                            ?>

                            <div><?php echo esc_html($language_code) ?></div>
                            <div>
                            <textarea name="<?php echo esc_attr($option->option_name) ?>_<?php echo $language_code ?>" data-default-value="<?php echo esc_attr( $option->default ); ?>" <?php echo ($option->use_default? 'disabled="true"' : '') ?>>
                                <?php echo esc_html( get_option($option->option_name.'_'.$language_code, $opt['default']) ); ?>
                            </textarea>
                            </div>

                            <?php
                        }
                    }
                    else {
                        ?>

                        <textarea name="<?php echo esc_attr($option->option_name) ?>" data-default-value="<?php echo esc_attr( $option->default ); ?>" <?php echo ($option->use_default? 'disabled="true"' : '') ?>>
                        <?php echo esc_html( $option->option_value ); ?>
                     </textarea>

                        <?php
                    }
                    ?>

                </div>

                <div>
                    <?php self::output_use_default_switch($option); ?>
                </div>


            </td>
        </tr>
        <?php

    }

    public static function output_affiliate_text_option(OCWS_LP_Affiliate_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr id="<?php echo esc_html($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>
                <input
                    type="text" name="<?php echo esc_attr( $option->option_name ) ?>" value="<?php echo esc_attr( $option->option_value ); ?>"
                    data-default-value="<?php echo esc_attr( $option->default ); ?>" <?php echo ($option->use_default? 'disabled="true"' : '') ?>/>
                <br />
                <?php self::output_use_default_switch($option); ?>
            </td>
        </tr>
        <?php

    }



    /*
     * output common options
     * */

    public static function output_checkbox_option(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_html($option->option_name); ?>"
                           class="" id="<?php echo esc_html($option->option_name); ?>"
                           value="1" <?php if ($option->option_value === '1') { ?> checked="checked"<?php } ?> />
                </label>
            </td>
        </tr>
        <?php
    }

    public static function output_radio_option(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        ?>
        <tr id="<?php echo esc_attr($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>
                <?php foreach ($opt['options'] as $o): ?>
                    <label class="as-block">
                        <input type="radio" name="<?php echo esc_attr($option->option_name); ?>" id="<?php echo esc_attr($o['name']); ?>"
                               value="<?php echo esc_attr($o['value']); ?>" <?php if ($option->option_value == $o['value']) { ?> checked="checked"<?php } ?> />
                        <?php echo esc_html($o['title']) ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php

    }

    public static function output_select_option(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        $value = get_option(self::OPTION_PREFIX_COMMON . $opt['name'], $opt['default']);
        ?>
        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <select name="<?php echo esc_html($option->option_name); ?>">
                    <?php foreach ($opt['options'] as $o): ?>
                        <option
                            <?php if ($option->option_value == $o['value']): ?>selected<?php endif ?>
                            value="<?php echo $o['value'] ?>">
                            <?php echo $o['title']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }

    public static function output_text_option(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        $languages = ocws_get_languages();
        $multilingual = (isset($opt['multilingual']) && $opt['multilingual']);
        ?>
        <tr id="<?php echo esc_html($option->option_name); ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <?php
                if ($multilingual && !empty($languages)) {
                    foreach($languages as $language_code) {
                        ?>

                        <div><?php echo esc_html($language_code) ?></div>
                        <div><input type="text" name="<?php echo esc_attr($option->option_name) ?>_<?php echo $language_code ?>" value="<?php echo esc_attr( get_option($option->option_name.'_'.$language_code, $opt['default']) ); ?>" /></div>

                        <?php
                    }
                }
                else {
                    ?>

                    <input type="text" name="<?php echo esc_attr($option->option_name) ?>" value="<?php echo esc_attr( $option->option_value ); ?>" />

                    <?php
                }
                ?>


            </td>
        </tr>
        <?php

    }

    public static function output_textarea_option(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        $languages = ocws_get_languages();
        $multilingual = (isset($opt['multilingual']) && $opt['multilingual']);
        ?>
        <tr id="<?php echo esc_attr($option->option_name) ?>" valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <?php
                if ($multilingual && !empty($languages)) {
                    foreach($languages as $language_code) {
                        ?>

                        <div><?php echo esc_html($language_code) ?></div>
                        <div>
                            <textarea name="<?php echo esc_attr($option->option_name) ?>_<?php echo $language_code ?>">
                                <?php echo esc_html( get_option($option->option_name.'_'.$language_code, $opt['default']) ); ?>
                            </textarea>
                        </div>

                        <?php
                    }
                }
                else {
                    ?>

                    <textarea name="<?php echo esc_attr($option->option_name) ?>">
                        <?php echo esc_html( $option->option_value ); ?>
                     </textarea>

                    <?php
                }
                ?>


            </td>
        </tr>
        <?php

    }

    /*
     * custom callbacks
     * */

    public static function output_pickup_subsection_capture($opt) {

        ?>

        <tr valign="top">
            <th colspan="2" scope="row"><h3><?php echo $opt['title'] ?></h3></th>
        </tr>

        <?php
    }

    public static function output_pickup_export_production_attributes_to_show(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes_assoc = array();
        if ( $attribute_taxonomies ) {

            foreach ( $attribute_taxonomies as $tax ) {
                $attributes_assoc[$tax->attribute_name] = $tax->attribute_label;
                //var_dump($tax);
            }
        }

        ?>

        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo esc_html($opt['title']) ?></th>
            <td>

                <?php
                $selected = $option->option_value;
                if (!$selected || !is_array($selected)) {
                    $selected = array();
                }

                foreach ( $attributes_assoc as $id => $attr ) {
                    echo '<div><label><input type="checkbox" name="'. esc_attr($option->option_name).'[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($attr).'</label></div>';
                }
                ?>

            </td>
        </tr>

        <?php
    }

    public static function output_pickup_export_production_details_attributes_to_show(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes_assoc = array();
        if ( $attribute_taxonomies ) {

            foreach ( $attribute_taxonomies as $tax ) {
                $attributes_assoc[$tax->attribute_name] = $tax->attribute_label;
                //var_dump($tax);
            }
        }

        ?>

        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>

                <?php
                $selected = $option->option_value;
                if (!$selected || !is_array($selected)) {
                    $selected = array();
                }

                foreach ( $attributes_assoc as $id => $attr ) {
                    echo '<div><label><input type="checkbox" name="'.esc_attr($option->option_name).'[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($attr).'</label></div>';
                }
                ?>

            </td>
        </tr>

        <?php
    }

    public static function output_pickup_export_production_details_pages(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        ?>

        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <?php
                $pages = $option->option_value;
                if (!$pages || !is_array($pages)) {
                    $pages = array(__('Main', 'ocws'));
                }
                $additionalPagesCount = (count($pages) < 20 ? 20 - count($pages) : 0);


                $pageIndex = 0;
                foreach ( $pages as $id => $title ) {
                    $pageIndex++;
                    ?>
                    <div class="ocws_lp_export_page">
                        <div><?php echo esc_html(__('Export page', 'ocws') . ' ' . $pageIndex) ?></div>
                        <div>
                            <input placeholder="<?php echo esc_attr(__('Export page title', 'ocws')) ?>" type="text" name="<?php echo esc_attr($option->option_name) ?>[]" value="<?php echo esc_attr( $title ); ?>" />
                        </div>
                    </div>
                    <?php
                }
                ?>

                <?php

                for ($i = 0; $i < $additionalPagesCount; $i++) {
                    $pageIndex++;
                    ?>
                    <div style="display: none" class="ocws_lp_export_page">
                        <div><?php echo esc_html(__('Export page', 'ocws') . ' ' . $pageIndex) ?></div>
                        <div>
                            <input placeholder="<?php echo esc_attr(__('Export page title', 'ocws')) ?>" type="text" name="<?php echo esc_attr($option->option_name) ?>[]" value="" />
                        </div>
                    </div>
                    <?php
                }
                ?>

                <div class="ocws_add_export_page">
                    <input type="button" id="ocws-lp-add-export-page" class="button" value="<?php echo esc_attr(__('Add export page', 'ocws')); ?>">
                </div>

            </td>
        </tr>

        <script>
            jQuery(document).ready(function () {

                jQuery('#ocws-lp-add-export-page').on('click', function() {
                    var hidden = jQuery("div.ocws_lp_export_page:hidden");
                    if (hidden.length > 0) {
                        jQuery(hidden[0]).show();
                    }
                    if (hidden.length == 1) {
                        jQuery('div.ocws_lp_add_export_page').hide();
                    }
                });
            });

        </script>

        <?php
    }

    public static function output_pickup_export_order_statuses(OCWS_LP_General_Option_Model $option, $opt, $show_option=true) {

        ?>

        <tr valign="top" style="<?php echo (!$show_option? 'display:none;' : ''); ?>">
            <th scope="row"><?php echo $opt['title'] ?></th>
            <td>
                <?php
                $selected = esc_attr($option->option_value);
                if (!$selected || !is_array($selected)) {
                    $selected = array('wc-processing');
                }
                foreach ( wc_get_order_statuses() as $id => $status ) {
                    echo '<div><label><input type="checkbox" name="'.esc_attr($option->option_name).'[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($status).'</label></div>';
                }
                ?>

            </td>
        </tr>

        <?php
    }

    public static function output_pickup_scheduling_type($option_model, $opt, $aff_id='default') {

        if ($aff_id == 'default') {
            $default_option_model = $option_model;
            self::output_default_radio_option($default_option_model, $opt);
            ?>
            <script>
                jQuery(document).ready(function () {

                    jQuery('input[type=radio][name=<?php echo $default_option_model->option_name ?>]').on('change', function() {
                        var schedule_type = jQuery(this).filter(':checked').val();
                        if (schedule_type == 'weekly') {
                            jQuery('tr[data-rel=dates_type]').hide();
                            jQuery('tr[data-rel=weekly_type]').show();
                        }
                        else {
                            jQuery('tr[data-rel=dates_type]').show();
                            jQuery('tr[data-rel=weekly_type]').hide();
                        }
                    })
                });

            </script>
            <?php
        }
        else {
            $aff_option_model = $option_model;
            //self::output_affiliate_radio_option($aff_option_model, $opt);
            ?>
            <tr id="<?php echo esc_attr($aff_option_model->option_name); ?>" valign="top">
                <th scope="row"><?php echo esc_html($opt['title']) ?></th>
                <td>
                    <?php foreach ($opt['options'] as $o): ?><span><?php //var_dump($o); ?></span>
                        <label class="as-block">
                            <input type="radio" name="<?php echo esc_attr($aff_option_model->option_name); ?>" id="<?php echo esc_attr($o['name']); ?>"
                                   value="<?php echo esc_attr($o['value']); ?>" <?php if ($aff_option_model->option_value == $o['value']) { ?> checked="checked"<?php } ?>
                                   data-def-value="<?php echo esc_attr( $aff_option_model->default ); ?>"
                                <?php echo ($aff_option_model->use_default? 'disabled="true"' : '') ?>/>
                            <?php echo esc_html($o['title']) ?>
                        </label>
                    <?php endforeach; ?>

                    <div>
                        <?php //self::output_use_default_switch($aff_option_model); ?>
                        <label>
                            <input data-rel-old="<?php echo esc_attr( $aff_option_model->option_name ) ?>" type="checkbox" name="<?php echo esc_attr( $aff_option_model->option_name . '_ud' ) ?>"
                                   class="use-default-switch" id="<?php echo esc_attr( $aff_option_model->option_name . '_ud' ) ?>"
                                   value="1" <?php if ($aff_option_model->use_default) { ?> checked="checked"<?php } ?> />
                            <?php echo __('Use default value', 'ocws') ?>
                        </label>
                    </div>
                </td>
            </tr>
            <?php
            ?>
            <script>
                jQuery(document).ready(function () {

                    jQuery('input[type=radio][name=<?php echo $aff_option_model->option_name ?>]').on('change', function() {
                        var schedule_type = jQuery(this).filter(':checked').val();
                        if (schedule_type == 'weekly') {
                            jQuery('tr[data-rel=dates_type]').hide();
                            jQuery('tr[data-rel=dates_type_choose]').hide();
                            jQuery('tr[data-rel=weekly_type]').show();
                        }
                        else {
                            jQuery('tr[data-rel=dates_type]').show();
                            jQuery('tr[data-rel=weekly_type]').hide();
                            jQuery( document.body ).trigger( 'type_chosen_dates' );
                        }
                    });

                    jQuery('input[type=checkbox][name=<?php echo esc_attr( $aff_option_model->option_name . '_ud' ) ?>]').on('click', function() {
                        var radio = jQuery('input[type=radio][name=<?php echo esc_attr( $aff_option_model->option_name ) ?>]');
                        if (this.checked) {
                            jQuery(radio).filter('[value='+jQuery(this).data('default-value')+']').prop('checked', true).trigger('change');
                            jQuery(radio).prop('disabled', true);
                        }
                        else {
                            jQuery(radio).filter('[value=<?php echo $aff_option_model->option_value ?>]').prop('checked', true).trigger('change');
                            jQuery(radio).prop('disabled', false);
                        }
                    })
                });

            </script>
            <?php
        }
    }

    public static function output_pickup_schedule_weekly($option_model, $opt, $aff_id='default') {

        if ($aff_id == 'default') {
            $schedule_weekly_data = get_option('ocws_lp_default_pickup_schedule_weekly', array());
            $schedule_weekly_object = new OC_Woo_Shipping_Schedule();
            $schedule_weekly_object->set_scheduling_type('weekly');
            // filter data
            $schedule_weekly_data = $schedule_weekly_object->set_days($schedule_weekly_data);
            $schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_weekly_data );

            $scheduling_type = self::get_default_option('pickup_scheduling_type', 'weekly');
            $weekly_no_display = $scheduling_type == 'dates'? ' style="display:none;" ' : '';

            ?>

            <tr valign="top" data-rel="weekly_type" <?php echo $weekly_no_display; ?>>
					<th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
					<td>
						<input type="hidden" name="ocws_lp_default_pickup_schedule_weekly" id="ocws_lp_default_pickup_schedule_weekly"
							   value="<?php echo esc_attr( $schedule_weekly_json ); ?>" />
						<div id="schedule_weekly"></div>
						<script>
							jQuery(document).ready(function () {

								var scheduleWeeklyDiv = jQuery('#schedule_weekly');

								scheduleWeeklyDiv.jqs({
									mode: 'edit',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: ''
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: ''
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'Sunday',
										'Monday',
										'Tuesday',
										'Wednesday',
										'Thursday',
										'Friday',
										'Saturday'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {
										console.log('Add period');
										console.log(period);
										jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onUpdatePeriod: function (period, jqs) {
										console.log('Update period');
										console.log(period);
										jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onDragPeriod: function (period, jqs) {
										console.log('Drag period');
										console.log(period);
										jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onResizePeriod: function (period, jqs) {
										console.log('Resize period');
										console.log(period);
										jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onAfterRemovePeriod: function (jqs) {
										console.log('Remove period');
										jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onDuplicatePeriod: function (period, jqs) {
										console.log('Duplicate period');
										console.log(period);
										jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
                                    onChangeFilterPicker: function (period, jqs) {
                                        console.log(period, jqs);
                                    },
									onChangeMaxHour: function (jqs) {
										// if (jqs)
										 jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onClickPeriod: function () {}
								});

								scheduleWeeklyDiv.jqs('addMaxHour');

								scheduleWeeklyDiv.jqs('import', <?php echo $schedule_weekly_json; ?>);
								jQuery('#ocws_lp_default_pickup_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));

								scheduleWeeklyDiv.jqs('updateDaysList',
									[
									'Sun',
									'Mon',
									'Tue',
									'Wed',
									'Thu',
									'Fri',
									'Sat'
								]
								);
							});
						</script>
					</td>
				</tr>
				<?php
        }
        else {

            $scheduling_type_option = self::get_option($aff_id, 'pickup_scheduling_type', 'weekly');
            $scheduling_type = $scheduling_type_option->option_value;
            if (!$scheduling_type) {
                $scheduling_type = 'weekly';
            }
            $weekly_no_display = $scheduling_type == 'dates'? ' style="display:none;" ' : '';
            $dates_no_display = $scheduling_type == 'weekly'? ' style="display:none;" ' : '';

            $option_weekly = self::get_option($aff_id, 'pickup_schedule_weekly', array());

            //var_dump($option_weekly);

            $schedule_weekly_data = $option_weekly->option_value;
            $schedule_weekly_object = new OC_Woo_Shipping_Schedule();
            $schedule_weekly_object->set_scheduling_type('weekly');
            // filter data
            $schedule_weekly_data = $schedule_weekly_object->set_days($schedule_weekly_data);
            $schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_weekly_data );

            $default_schedule_weekly_data = $option_weekly->default;
            $default_schedule_weekly_object = new OC_Woo_Shipping_Schedule();
            $default_schedule_weekly_object->set_scheduling_type('weekly');
            // filter data
            $default_schedule_weekly_data = $default_schedule_weekly_object->set_days($default_schedule_weekly_data);
            $default_schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $default_schedule_weekly_data );

            $option_dates = self::get_option($aff_id, 'pickup_schedule_dates', array());

            //var_dump($option_dates);

            $schedule_dates_data = $option_dates->option_value;
            $schedule_dates_object = new OC_Woo_Shipping_Schedule();
            $schedule_dates_object->set_scheduling_type('dates');
            // filter data
            $schedule_dates_data = $schedule_dates_object->set_days($schedule_dates_data);
            $shipping_dates = $schedule_dates_object->get_dates();
            $schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_dates_data );
            //$schedule_dates_json = str_replace('"', "'", ($schedule_dates_json? $schedule_dates_json : ''));

            //echo ($schedule_dates_json);

            $default_schedule_dates_data = $option_dates->default;
            $default_schedule_dates_object = new OC_Woo_Shipping_Schedule();
            $default_schedule_dates_object->set_scheduling_type('dates');
            // filter data
            $default_schedule_dates_data = $default_schedule_dates_object->set_days($default_schedule_dates_data);
            $default_shipping_dates = $default_schedule_dates_object->get_dates();
            $default_schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $default_schedule_dates_data );
            //$default_schedule_dates_json = str_replace('"', "'", ($default_schedule_dates_json? $default_schedule_dates_json : ''));

            $choose_dates_no_display = ($scheduling_type == 'weekly' || $option_dates->use_default)? ' style="display:none;" ' : '';

            ?>

            <tr valign="top" data-rel="weekly_type" <?php echo $weekly_no_display; ?>>
                <th scope="row"></th>
                <td>
                    <div>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $option_weekly->option_name . '_ud' ) ?>" id="<?php echo esc_attr( $option_weekly->option_name . '_ud' ) ?>"
                                   value="1" <?php if ($option_weekly->use_default) { ?> checked="checked"<?php } ?> />
                            <?php echo __('Use default schedule', 'ocws') ?>
                        </label>
                    </div>
                </td>
            </tr>

            <tr valign="top" data-rel="weekly_type" <?php echo $weekly_no_display; ?>>
                <th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
                <td>
                    <style>
                        .jqs-table .jqs-period {
                            position: absolute;
                        }
                    </style>
                    <input type="hidden" name="<?php echo esc_attr( $option_weekly->option_name ) ?>" id="<?php echo esc_attr( $option_weekly->option_name ) ?>"
                           value="<?php echo esc_attr( $schedule_weekly_json ); ?>" />
                    <div style="<?php echo ($option_weekly->use_default? 'display: none;' : '') ?>" id="schedule_weekly"></div>
                    <div style="<?php echo ($option_weekly->use_default? '' : 'display: none;') ?>" id="default_schedule_weekly"></div>
                    <script>
                        jQuery(document).ready(function () {

                            var scheduleWeeklyDiv = jQuery('#schedule_weekly');

                            scheduleWeeklyDiv.jqs({
                                mode: 'edit',
                                hour: 24,
                                days: 7,
                                periodDuration: 30,
                                data: [],
                                periodOptions: true,
                                periodDefaultData: [
                                    {
                                        name: 'products',
                                        title: 'Max products',
                                        value: ''
                                    },
                                    {
                                        name: 'orders',
                                        title: 'Max orders',
                                        value: ''
                                    }
                                ],
                                periodColors: [],
                                periodTitle: '',
                                periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
                                periodBorderColor: '#2a3cff',
                                periodTextColor: '#000',
                                periodRemoveButton: 'Remove',
                                periodDuplicateButton: 'Duplicate',
                                periodTitlePlaceholder: 'Title',
                                daysList: [
                                    'Sunday',
                                    'Monday',
                                    'Tuesday',
                                    'Wednesday',
                                    'Thursday',
                                    'Friday',
                                    'Saturday'
                                ],
                                onInit: function () {},
                                onAddPeriod: function (period, jqs) {
                                    console.log('Add period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onUpdatePeriod: function (period, jqs) {
                                    console.log('Update period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onDragPeriod: function (period, jqs) {
                                    console.log('Drag period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onResizePeriod: function (period, jqs) {
                                    console.log('Resize period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onAfterRemovePeriod: function (jqs) {
                                    console.log('Remove period');
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onDuplicatePeriod: function (period, jqs) {
                                    console.log('Duplicate period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onChangeMaxHour: function (jqs) {
                                    // if (jqs)
                                    jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));
                                },
                                onChangeFilterPicker: function (period, jqs) {
                                    console.log(period, jqs);
                                },
                                onClickPeriod: function () {}
                            });

                            scheduleWeeklyDiv.jqs('addMaxHour');

                            scheduleWeeklyDiv.jqs('import', <?php echo $schedule_weekly_json; ?>);
                            jQuery('#<?php echo esc_attr( $option_weekly->option_name ) ?>').val(scheduleWeeklyDiv.jqs('export'));

                            // default schedule

                            var defaultScheduleWeeklyDiv = jQuery('#default_schedule_weekly');

                            defaultScheduleWeeklyDiv.jqs({
                                mode: 'read',
                                hour: 24,
                                days: 7,
                                periodDuration: 30,
                                data: [],
                                periodOptions: true,
                                periodDefaultData: [
                                    {
                                        name: 'products',
                                        title: 'Max products',
                                        value: ''
                                    },
                                    {
                                        name: 'orders',
                                        title: 'Max orders',
                                        value: ''
                                    }
                                ],
                                periodColors: [],
                                periodTitle: '',
                                periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
                                periodBorderColor: '#2a3cff',
                                periodTextColor: '#000',
                                periodRemoveButton: 'Remove',
                                periodDuplicateButton: 'Duplicate',
                                periodTitlePlaceholder: 'Title',
                                daysList: [
                                    'Sunday',
                                    'Monday',
                                    'Tuesday',
                                    'Wednesday',
                                    'Thursday',
                                    'Friday',
                                    'Saturday'
                                ],
                                onInit: function () {},
                                onAddPeriod: function (period, jqs) {

                                },
                                onUpdatePeriod: function (period, jqs) {

                                },
                                onDragPeriod: function (period, jqs) {

                                },
                                onResizePeriod: function (period, jqs) {

                                },
                                onAfterRemovePeriod: function (jqs) {

                                },
                                onDuplicatePeriod: function (period, jqs) {

                                },
                                onChangeMaxHour: function (jqs) {

                                },
                                onClickPeriod: function () {}
                            });

                            defaultScheduleWeeklyDiv.jqs('import', <?php echo $default_schedule_weekly_json; ?>);

                        });
                    </script>

                    <script>
                        jQuery(document).ready(function () {

                            jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_weekly->option_name . '_ud' ) ?>]').on('click', function() {
                                if (this.checked) {
                                    jQuery('#default_schedule_weekly').show();
                                    jQuery('#schedule_weekly').hide();
                                }
                                else {
                                    jQuery('#schedule_weekly').show();
                                    jQuery('#default_schedule_weekly').hide();
                                }
                            });

                            jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_dates->option_name . '_ud' ) ?>]').on('click', function() {
                                if (this.checked) {
                                    jQuery('#default_schedule_dates').show();
                                    jQuery('#schedule_dates').hide();
                                    jQuery('tr[data-rel=dates_type_choose]').hide();
                                }
                                else {
                                    jQuery('#schedule_dates').show();
                                    jQuery('#default_schedule_dates').hide();
                                    jQuery('tr[data-rel=dates_type_choose]').show();
                                }
                            });

                            jQuery( document.body ).on( 'type_chosen_dates', function () {
                                var chk = jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_dates->option_name . '_ud' ) ?>]');
                                if (chk[0].checked) {
                                    jQuery('tr[data-rel=dates_type_choose]').hide();
                                }
                                else {
                                    jQuery('tr[data-rel=dates_type_choose]').show();
                                }
                            } );
                        });

                    </script>
                </td>
            </tr>

            <?php
        }
    }

    public static function output_pickup_schedule_dates($option_model, $opt, $aff_id='default') {

        if ($aff_id == 'default') {

            $schedule_dates_data = get_option('ocws_lp_default_pickup_schedule_dates', array());
            $schedule_dates_object = new OC_Woo_Shipping_Schedule();
            $schedule_dates_object->set_scheduling_type('dates');
            // filter data
            $schedule_dates_data = $schedule_dates_object->set_days($schedule_dates_data);
            $shipping_dates = $schedule_dates_object->get_dates();
            $schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_dates_data );

            $scheduling_type = self::get_default_option('pickup_scheduling_type', 'weekly');
            $dates_no_display = $scheduling_type->option_value == 'weekly'? ' style="display:none;" ' : '';

            ?>

            <tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
                <th scope="row"><?php echo __('Choose specific dates', 'ocws') ?></th>
                <td>
                    <script>
                        jQuery(document).ready(function () {

                            jQuery("#ocws_lp_default_pickup_dates").multiDatesPicker({

                                dateFormat: 'dd/mm/yy',
                                minDate: 0,
                                maxPicks: 7,
                                <?php echo !empty($shipping_dates)? 'addDates: ' . json_encode($shipping_dates) . ',' : ''; ?>
                                onSelect: function() {
                                    var dates = jQuery(this).multiDatesPicker("getDates");
                                    var schedule = JSON.parse(jQuery('#schedule_dates').jqs('export'));
                                    var newSchedule = [];
                                    for (var k=0; k < dates.length; k++) {
                                        var date = dates[k];
                                        var alreadyInSchedule = false;
                                        for (var l=0; l < schedule.length; l++) {
                                            if (schedule[l].day == date) {
                                                alreadyInSchedule = true;
                                                newSchedule.push(schedule[l]);
                                                break;
                                            }
                                        }
                                        if (!alreadyInSchedule) {
                                            newSchedule.push({'day':date,'periods':[]});
                                        }
                                    }
                                    jQuery('#schedule_dates').jqs('reset');
                                    jQuery('#schedule_dates').jqs('import', newSchedule);
                                }
                            });
                        });

                    </script>
                    <!--<div id="ocws_default_delivery_dates_mdp"></div>-->
                    <div style="z-index: 100">
                        <input style="position: relative; z-index: 100000;" type="text" id="ocws_lp_default_pickup_dates" name="ocws_lp_default_pickup_dates" readonly="readonly"
                               value="<?php printf(get_option('ocws_lp_default_pickup_dates')); ?>">
                    </div>
                </td>
            </tr>

            <tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
                <th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
                <td>
                    <input type="hidden" name="ocws_lp_default_pickup_schedule_dates" id="ocws_lp_default_pickup_schedule_dates"
                           value="<?php echo esc_attr( $schedule_dates_json ); ?>" />
                    <div id="schedule_dates" style="direction: ltr"></div>
                    <script>
                        jQuery(document).ready(function () {

                            var scheduleDatesDiv = jQuery('#schedule_dates');

                            scheduleDatesDiv.jqs({
                                mode: 'edit',
                                type: 'dates',
                                hour: 24,
                                days: 7,
                                periodDuration: 30,
                                data: [],
                                periodOptions: true,
                                periodDefaultData: [
                                    {
                                        name: 'products',
                                        title: 'Max products',
                                        value: ''
                                    },
                                    {
                                        name: 'orders',
                                        title: 'Max orders',
                                        value: ''
                                    }
                                ],
                                periodColors: [],
                                periodTitle: '',
                                periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
                                periodBorderColor: '#2a3cff',
                                periodTextColor: '#000',
                                periodRemoveButton: 'Remove',
                                periodDuplicateButton: 'Duplicate',
                                periodTitlePlaceholder: 'Title',
                                daysList: [
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-'
                                ],
                                onInit: function () {},
                                onAddPeriod: function (period, jqs) {
                                    console.log('Add period');
                                    console.log(period);
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onUpdatePeriod: function (period, jqs) {
                                    console.log('Update period');
                                    console.log(period);
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onDragPeriod: function (period, jqs) {
                                    console.log('Drag period');
                                    console.log(period);
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onResizePeriod: function (period, jqs) {
                                    console.log('Resize period');
                                    console.log(period);
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onAfterRemovePeriod: function (jqs) {
                                    console.log('Remove period');
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onDuplicatePeriod: function (period, jqs) {
                                    console.log('Duplicate period');
                                    console.log(period);
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onChangeMaxHour: function (jqs) {
                                    // if (jqs)
                                    jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));
                                },
                                onClickPeriod: function () {}
                            });

                            scheduleDatesDiv.jqs('addMaxHour');

                            scheduleDatesDiv.jqs('import', <?php echo $schedule_dates_json; ?>);
                            jQuery('#ocws_lp_default_pickup_schedule_dates').val(scheduleDatesDiv.jqs('export'));

                        });
                    </script>
                </td>
            </tr>

            <?php
        }
        else {

            $scheduling_type_option = self::get_option($aff_id, 'pickup_scheduling_type', 'weekly');
            $scheduling_type = $scheduling_type_option->option_value;
            if (!$scheduling_type) {
                $scheduling_type = 'weekly';
            }
            $weekly_no_display = $scheduling_type == 'dates'? ' style="display:none;" ' : '';
            $dates_no_display = $scheduling_type == 'weekly'? ' style="display:none;" ' : '';

            $option_weekly = self::get_option($aff_id, 'pickup_schedule_weekly', array());

            //var_dump($option_weekly);

            $schedule_weekly_data = $option_weekly->option_value;
            $schedule_weekly_object = new OC_Woo_Shipping_Schedule();
            $schedule_weekly_object->set_scheduling_type('weekly');
            // filter data
            $schedule_weekly_data = $schedule_weekly_object->set_days($schedule_weekly_data);
            $schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_weekly_data );

            $default_schedule_weekly_data = $option_weekly->default;
            $default_schedule_weekly_object = new OC_Woo_Shipping_Schedule();
            $default_schedule_weekly_object->set_scheduling_type('weekly');
            // filter data
            $default_schedule_weekly_data = $default_schedule_weekly_object->set_days($default_schedule_weekly_data);
            $default_schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $default_schedule_weekly_data );

            $option_dates = self::get_option($aff_id, 'pickup_schedule_dates', array());

            //var_dump($option_dates);

            $schedule_dates_data = $option_dates->option_value;
            $schedule_dates_object = new OC_Woo_Shipping_Schedule();
            $schedule_dates_object->set_scheduling_type('dates');
            // filter data
            $schedule_dates_data = $schedule_dates_object->set_days($schedule_dates_data);
            $shipping_dates = $schedule_dates_object->get_dates();
            $schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_dates_data );
            //$schedule_dates_json = str_replace('"', "'", ($schedule_dates_json? $schedule_dates_json : ''));

            //echo ($schedule_dates_json);

            $default_schedule_dates_data = $option_dates->default;
            $default_schedule_dates_object = new OC_Woo_Shipping_Schedule();
            $default_schedule_dates_object->set_scheduling_type('dates');
            // filter data
            $default_schedule_dates_data = $default_schedule_dates_object->set_days($default_schedule_dates_data);
            $default_shipping_dates = $default_schedule_dates_object->get_dates();
            $default_schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $default_schedule_dates_data );
            //$default_schedule_dates_json = str_replace('"', "'", ($default_schedule_dates_json? $default_schedule_dates_json : ''));

            $choose_dates_no_display = ($scheduling_type == 'weekly' || $option_dates->use_default)? ' style="display:none;" ' : '';

            ?>

            <tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
                <th scope="row"></th>
                <td>
                    <div>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $option_dates->option_name . '_ud' ) ?>" id="<?php echo esc_attr( $option_dates->option_name . '_ud' ) ?>"
                                   value="1" <?php if ($option_dates->use_default) { ?> checked="checked"<?php } ?> />
                            <?php echo __('Use default schedule', 'ocws') ?>
                        </label>
                    </div>
                </td>
            </tr>

            <tr valign="top" data-rel="dates_type_choose" <?php echo $choose_dates_no_display; ?>>
                <th scope="row"><?php echo __('Choose specific dates', 'ocws') ?></th>
                <td>

                    <script>
                        jQuery(document).ready(function () {

                            jQuery("#ocws_lp_aff_pickup_dates").multiDatesPicker({

                                dateFormat: 'dd/mm/yy',
                                minDate: 0,
                                maxPicks: 7,
                                <?php echo !empty($shipping_dates)? 'addDates: ' . json_encode($shipping_dates) . ',' : ''; ?>
                                onSelect: function() {
                                    var dates = jQuery(this).multiDatesPicker("getDates");
                                    var schedule = JSON.parse(jQuery('#schedule_dates').jqs('export'));
                                    var newSchedule = [];
                                    for (var k=0; k < dates.length; k++) {
                                        var date = dates[k];
                                        var alreadyInSchedule = false;
                                        for (var l=0; l < schedule.length; l++) {
                                            if (schedule[l].day == date) {
                                                alreadyInSchedule = true;
                                                newSchedule.push(schedule[l]);
                                                break;
                                            }
                                        }
                                        if (!alreadyInSchedule) {
                                            newSchedule.push({'day':date,'periods':[]});
                                        }
                                    }
                                    jQuery('#schedule_dates').jqs('reset');
                                    jQuery('#schedule_dates').jqs('import', newSchedule);
                                }
                            });
                        });

                    </script>
                    <!--<div id="ocws_default_delivery_dates_mdp"></div>-->
                    <div style="<?php //echo ($option_dates['use_default']? 'display: none;' : '') ?>">
                        <input style="position: relative; z-index: 100000;" type="text" id="ocws_lp_aff_pickup_dates" name="ocws_lp_aff_pickup_dates" readonly="readonly"
                               value="<?php printf(get_option('ocws_lp_default_pickup_dates')); ?>">
                    </div>
                </td>
            </tr>

            <tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
                <th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
                <td>
                    <input type="hidden" name="<?php echo esc_attr( $option_dates->option_name ) ?>" id="<?php echo esc_attr( $option_dates->option_name ) ?>"
                           value="<?php echo esc_attr( $schedule_dates_json ); ?>" />
                    <div style="<?php echo ($option_dates->use_default? 'display: none;direction:ltr;' : 'direction:ltr;') ?>" id="schedule_dates"></div>
                    <div style="<?php echo ($option_dates->use_default? '' : 'display: none;') ?>" id="default_schedule_dates"></div>
                    <script>
                        jQuery(document).ready(function () {

                            var scheduleDatesDiv = jQuery('#schedule_dates');

                            scheduleDatesDiv.jqs({
                                mode: 'edit',
                                type: 'dates',
                                hour: 24,
                                days: 7,
                                periodDuration: 30,
                                data: [],
                                periodOptions: true,
                                periodDefaultData: [
                                    {
                                        name: 'products',
                                        title: 'Max products',
                                        value: ''
                                    },
                                    {
                                        name: 'orders',
                                        title: 'Max orders',
                                        value: ''
                                    }
                                ],
                                periodColors: [],
                                periodTitle: '',
                                periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
                                periodBorderColor: '#2a3cff',
                                periodTextColor: '#000',
                                periodRemoveButton: 'Remove',
                                periodDuplicateButton: 'Duplicate',
                                periodTitlePlaceholder: 'Title',
                                daysList: [
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-'
                                ],
                                onInit: function () {},
                                onAddPeriod: function (period, jqs) {
                                    console.log('Add period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onUpdatePeriod: function (period, jqs) {
                                    console.log('Update period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onDragPeriod: function (period, jqs) {
                                    console.log('Drag period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onResizePeriod: function (period, jqs) {
                                    console.log('Resize period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onAfterRemovePeriod: function (jqs) {
                                    console.log('Remove period');
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onDuplicatePeriod: function (period, jqs) {
                                    console.log('Duplicate period');
                                    console.log(period);
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onChangeMaxHour: function (jqs) {
                                    // if (jqs)
                                    jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));
                                },
                                onChangeFilterPicker: function (period, jqs) {
                                    console.log(period, jqs);
                                },
                                onClickPeriod: function () {}
                            });


                            scheduleDatesDiv.jqs('addMaxHour');
                            // HERE GOLD DEV
                            scheduleDatesDiv.jqs('import', <?php echo $schedule_dates_json; ?>);
                            jQuery('#<?php echo esc_attr( $option_dates->option_name ) ?>').val(scheduleDatesDiv.jqs('export'));

                            // default schedule

                            var defaultScheduleDatesDiv = jQuery('#default_schedule_dates');

                            defaultScheduleDatesDiv.jqs({
                                mode: 'read',
                                type: 'dates',
                                hour: 24,
                                days: 7,
                                periodDuration: 30,
                                data: [],
                                periodOptions: true,
                                periodDefaultData: [
                                    {
                                        name: 'products',
                                        title: 'Max products',
                                        value: ''
                                    },
                                    {
                                        name: 'orders',
                                        title: 'Max orders',
                                        value: ''
                                    }
                                ],
                                periodColors: [],
                                periodTitle: '',
                                periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
                                periodBorderColor: '#2a3cff',
                                periodTextColor: '#000',
                                periodRemoveButton: 'Remove',
                                periodDuplicateButton: 'Duplicate',
                                periodTitlePlaceholder: 'Title',
                                daysList: [
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-'
                                ],
                                onInit: function () {},
                                onAddPeriod: function (period, jqs) {

                                },
                                onUpdatePeriod: function (period, jqs) {

                                },
                                onDragPeriod: function (period, jqs) {

                                },
                                onResizePeriod: function (period, jqs) {

                                },
                                onAfterRemovePeriod: function (jqs) {

                                },
                                onDuplicatePeriod: function (period, jqs) {

                                },
                                onChangeMaxHour: function (jqs) {

                                },
                                onClickPeriod: function () {}
                            });

                            defaultScheduleDatesDiv.jqs('import', <?php echo $default_schedule_dates_json; ?>);

                        });
                    </script>
                </td>
            </tr>

            <?php
        }
    }

    public static function output_pickup_closing_weekdays($option_model, $opt, $aff_id='default') {

        if ($aff_id == 'default') {
            $closing_weekdays = $option_model->option_value;
            if (!$closing_weekdays && $closing_weekdays != 0) {
                $closing_weekdays_arr = array();
            }
            else {
                $closing_weekdays_arr = ocws_numbers_list_to_array( $closing_weekdays );
            }
            ?>

            <tr valign="top">
                <th scope="row"><?php echo __('Exclude days of week', 'ocws') ?></th>
                <td>
                    <input type="hidden" id="ocws_lp_default_closing_weekdays" name="ocws_lp_default_closing_weekdays"
                           value="<?php echo esc_attr( $closing_weekdays ); ?>">
                    <div id="closing_weekdays">
                        <label>
                            <input type="checkbox" id="closing_weekdays-0" data-weekday="0"
                                   value="1" <?php if (in_array('0', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Sunday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-1" data-weekday="1"
                                   value="1" <?php if (in_array('1', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Monday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-2" data-weekday="2"
                                   value="1" <?php if (in_array('2', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Tuesday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-3" data-weekday="3"
                                   value="1" <?php if (in_array('3', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Wednesday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-4" data-weekday="4"
                                   value="1" <?php if (in_array('4', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Thursday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-5" data-weekday="5"
                                   value="1" <?php if (in_array('5', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Friday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-6" data-weekday="6"
                                   value="1" <?php if (in_array('6', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Saturday', 'ocws') ?>
                        </label>
                    </div>

                    <script>
                        jQuery(document).ready(function () {

                            jQuery('#closing_weekdays input[type="checkbox"]').on('click', function() {
                                var hidden = jQuery("#ocws_lp_default_closing_weekdays");

                                var values = [];
                                jQuery('#closing_weekdays input[type=checkbox]:checked').each(function (index, value) {
                                    values.push(jQuery(value).data('weekday'));
                                });
                                hidden.val(values.join(','));

                            });
                        });

                    </script>
                </td>
            </tr>

            <?php
        }
        else {

            $closing_weekdays = $option_model->option_value;
            $default_closing_weekdays = $option_model->default;
            if (!$closing_weekdays && $closing_weekdays != 0) {
                $closing_weekdays = array();
            }
            else {
                $closing_weekdays = ocws_numbers_list_to_array( $closing_weekdays );
            }
            if (!$default_closing_weekdays && $default_closing_weekdays != 0) {
                $default_closing_weekdays = array();
            }
            else {
                $default_closing_weekdays = ocws_numbers_list_to_array( $default_closing_weekdays );
            }

            ?>

            <tr valign="top">
                <th scope="row"><?php echo __('Exclude days of week', 'ocws') ?></th>
                <td>
                    <input type="hidden" id="<?php echo esc_attr( $option_model->option_name ) ?>" name="<?php echo esc_attr( $option_model->option_name ) ?>"
                           data-default-value="<?php echo esc_attr( $option_model->default ); ?>"
                           value="<?php echo esc_attr( $option_model->option_value ); ?>">
                    <div id="closing_weekdays" style="<?php echo ($option_model->use_default? 'display: none' : '') ?>">
                        <label>
                            <input type="checkbox" id="closing_weekdays-0" data-weekday="0"
                                   value="1" <?php if (in_array('0', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Sunday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-1" data-weekday="1"
                                   value="1" <?php if (in_array('1', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Monday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-2" data-weekday="2"
                                   value="1" <?php if (in_array('2', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Tuesday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-3" data-weekday="3"
                                   value="1" <?php if (in_array('3', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Wednesday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-4" data-weekday="4"
                                   value="1" <?php if (in_array('4', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Thursday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-5" data-weekday="5"
                                   value="1" <?php if (in_array('5', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Friday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" id="closing_weekdays-6" data-weekday="6"
                                   value="1" <?php if (in_array('6', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Saturday', 'ocws') ?>
                        </label>
                    </div>
                    <div id="default_closing_weekdays" style="<?php echo (!$option_model->use_default? 'display: none' : '') ?>">
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('0', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Sunday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('1', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Monday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('2', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Tuesday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('3', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Wednesday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('4', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Thursday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('5', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Friday', 'ocws') ?>
                        </label>
                        <label>
                            <input type="checkbox" disabled="disabled"
                                <?php if (in_array('6', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Saturday', 'ocws') ?>
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $option_model->option_name . '_ud' ) ?>" id="<?php echo esc_attr( $option_model->option_name . '_ud' ) ?>"
                                   data-default-value="<?php echo esc_attr( $option_model->default ); ?>"
                                   value="1" <?php if ($option_model->use_default) { ?> checked="checked"<?php } ?> />
                            <?php echo __('Use default value', 'ocws') ?>
                        </label>
                    </div>

                    <script>
                        jQuery(document).ready(function () {

                            jQuery('#closing_weekdays input[type=checkbox]').on('change', function() {
                                var hidden = jQuery("#<?php echo esc_attr( $option_model->option_name ) ?>");
                                var values = [];
                                jQuery('#closing_weekdays input[type=checkbox]:checked').each(function (index, value) {
                                    values.push(jQuery(value).data('weekday'));
                                });
                                hidden.val(values.join(','));

                            });

                            jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_model->option_name . '_ud' ) ?>]').on('click', function() {
                                if (this.checked) {
                                    jQuery('#default_closing_weekdays').show();
                                    jQuery('#closing_weekdays').hide();
                                }
                                else {
                                    jQuery('#default_closing_weekdays').hide();
                                    jQuery('#closing_weekdays').show();
                                }
                            });
                        });

                    </script>
                </td>
            </tr>

            <?php

        }
    }

    public static function output_pickup_closing_dates($option_model, $opt, $aff_id='default') {

        if ($aff_id == 'default') {

            $closing_dates = $option_model->option_value;
            if (!$closing_dates) {
                $closing_dates = array();
            }
            else {
                $closing_dates = ocws_dates_list_to_array( $closing_dates );
            }

            ?>

            <tr valign="top">
                <th scope="row"><?php echo __('Exclude dates', 'ocws') ?></th>
                <td>
                    <div>
                        <input style="position: relative; z-index: 100000;" type="text" id="ocws_lp_default_closing_dates" name="ocws_lp_default_closing_dates" readonly="readonly"
                               value="<?php esc_attr( $option_model->option_value ); ?>">
                    </div>

                    <script>
                        jQuery(document).ready(function () {

                            jQuery("#ocws_lp_default_closing_dates").multiDatesPicker({

                                dateFormat: 'dd/mm/yy',
                                minDate: 0,
                                maxPicks: 100,
                                <?php echo !empty($closing_dates)? 'addDates: ' . json_encode($closing_dates) . ',' : ''; ?>
                                onSelect: function() {}
                            });
                        });

                    </script>
                </td>
            </tr>

            <?php
        }
        else {

            $closing_dates = $option_model->option_value;
            if (!$closing_dates) {
                $closing_dates = array();
            }
            else {
                $closing_dates = ocws_dates_list_to_array( $closing_dates );
            }

            ?>

            <tr valign="top">
                <th scope="row"><?php echo __('Exclude dates', 'ocws') ?></th>
                <td>
                    <div>
                        <input style="<?php echo (!$option_model->use_default? 'display: none;' : '') ?>" type="text" id="ocws_lp_default_closing_dates" readonly="readonly"
                               value="<?php echo esc_attr( $option_model->default ); ?>">

                        <input style="position: relative; z-index: 100000;<?php echo ($option_model->use_default? 'display: none;' : '') ?>"
                               type="text" id="<?php echo esc_attr( $option_model->option_name ) ?>"
                               name="<?php echo esc_attr( $option_model->option_name ) ?>" readonly="readonly"
                               value="">
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $option_model->option_name . '_ud' ) ?>" id="<?php echo esc_attr( $option_model->option_name . '_ud' ) ?>"
                                   data-default-value="<?php echo esc_attr( $option_model->default ); ?>"
                                   value="1" <?php if ($option_model->use_default) { ?> checked="checked"<?php } ?> />
                            <?php echo __('Use default value', 'ocws') ?>
                        </label>
                    </div>

                    <script>
                        jQuery(document).ready(function () {

                            jQuery("#<?php echo esc_attr( $option_model->option_name ) ?>").multiDatesPicker({

                                dateFormat: 'dd/mm/yy',
                                minDate: 0,
                                maxPicks: 100,
                                <?php echo !empty($closing_dates)? 'addDates: ' . json_encode($closing_dates) . ',' : ''; ?>
                                onSelect: function() {}
                            });

                            jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_model->option_name . '_ud' ) ?>]').on('click', function() {
                                if (this.checked) {
                                    jQuery('#ocws_lp_default_closing_dates').show();
                                    jQuery('#<?php echo esc_attr( $option_model->option_name ) ?>').hide();
                                }
                                else {
                                    jQuery('#<?php echo esc_attr( $option_model->option_name ) ?>').show();
                                    jQuery('#ocws_lp_default_closing_dates').hide();
                                }
                            });
                        });

                    </script>
                </td>
            </tr>

            <?php
        }
    }


}