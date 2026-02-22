<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var int $available_methods_number
 * @var int $chosen_method_index
 * @var array $methods assoc array with keys 'method_id', 'method_instance_id', 'type' ('pickup', 'shipping'), 'is_chosen', 'title'
 * @var array $pickup_branches
 * @var array $shipping_locations
 *
 */

$show_shipping_options      = false;
$show_pickup_options        = false;
$shipping_popup_description = get_option('ocws_common_shipping_popup_description');
?>

<div class="choose-shipping-popup">
    <div style="display: none">
        <?php
        //var_dump($available_methods_number);
        ?>
    </div>
    <div class="white-overlay"></div>
    <div class="inner">

        <div class="inner-wrapper">
            <div class="pop-close">
                <img src="<?php echo OCWS_ASSESTS_URL; ?>/images/cancel.svg" alt="">
            </div>
            <header>
                <!--<button type="button" class="close" aria-label="Close">
                    <span aria-hidden="true">&times;*****************</span>
                </button>-->
                <?php if(get_field('shipping_popup_icon' , 'option')):?>
                    <div class="icon">
                        <img src="<?php the_field('shipping_popup_icon' , 'option')?>">
                    </div>
                <?php endif;?>
                <h2 class="entry-title crossed-title"><?php echo esc_attr( ocws_get_multilingual_option('ocws_common_popup_title') ); ?></h2>
            </header>

            <form id="choose-shipping" class="choose-shipping" action="" method="post">
                <div class="ship-choose">
                    <?php foreach ($methods as $method) { ?>
                        <div class="shipping-method-wraper <?php echo $method['method_id'].':'.$method['method_instance_id'];?>"
                             style="<?php echo ($available_methods_number === 1? 'display: none;' : '') ?>">

                            <label for="<?php echo $method['method_id'].':'.$method['method_instance_id'];?>">
                                <div class="radio-wrapper">
                                    <input data-title="<?php echo esc_attr($method['title']) ?>"
                                           type="radio" <?php echo $method['is_chosen'] ? 'checked' : '' ?>
                                           name="popup-shipping-method"
                                           value="<?php echo $method['method_id'].':'.$method['method_instance_id']?>"
                                           id="<?php echo $method['method_id'].':'.$method['method_instance_id']?>">
                                    <span class="radiocheck"></span>
                                </div>
                                <span class="label"><?php echo esc_attr($method['title']); ?></span>
                            </label>

                            <?php
                            if ($method['is_chosen']) {
                                if ($method['type'] == 'shipping') {
                                    $show_shipping_options = true;
                                }
                                else if ($method['type'] == 'pickup') {
                                    $show_pickup_options = true;
                                }
                            }
                            ?>


                        </div>
                    <?php } ?>
                </div>
                <div id="popup-shipping-options" style="<?php echo $show_shipping_options? '' : 'display: none;' ?>">
                <?php if (!ocws_use_google_cities_and_polygons()) { ?>
                    <?php if(isset($shipping_locations)) { ?>

                        <div class="shipping-description">
                            <?php do_action( 'ocws_shipping_popup_decription'); ?>
                            <div><?php echo $shipping_popup_description; ?></div>
                        </div>
                        <?php if ($available_methods_number === 1) { ?>
                            <label for="selected-city" class=""><?php echo esc_html(__('Choose shipping location', 'ocws'))?>&nbsp;<abbr class="required" title="<?php echo esc_html(__('Required', 'ocws'))?>">*</abbr></label>
                        <?php } ?>
                        <div class="selected-city">
                            <select name="selected-city" class="ocws-enhanced-select">
                                <option selected disabled><?php echo esc_html(__('Select your distribution area', 'ocws')) ?></option>
                                <?php foreach ($shipping_locations as $code => $city_option):?>
                                    <option value="<?php echo $code?>"><?php echo $city_option?></option>
                                <?php endforeach;?>
                            </select>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="ocws-checkout-inputs-pp">

                        <input type="text" class="input-text ocws-checkout-pac-input pac-target-input"
                               name="billing_google_autocomplete" id="billing_google_autocomplete_p" placeholder="<?php echo esc_html(__('Enter your address here', 'ocws')) ?>" value="" autocomplete="off">

                        <input type="hidden" name="billing_city" id="billing_city_pp" value="">

                        <input type="hidden" name="billing_city_code" id="billing_city_code_pp" value="">

                        <input type="hidden" name="billing_city_name" id="billing_city_name_pp" value="">

                        <input type="hidden" name="billing_street" id="billing_street_pp" value="">

                        <input type="hidden" name="billing_house_num" id="billing_house_num_pp" value="">

                        <input type="hidden" name="billing_address_coords" id="billing_address_coords_pp" value="">

                    </div>
                <?php } ?>
                </div>

                <div id="popup-shipping-form-messages" style="<?php echo $show_shipping_options? '' : 'display: none;' ?>"></div>

                <div id="popup-shipping-city-slots" style="<?php echo $show_shipping_options? '' : 'display: none;' ?>"></div>
                <p class="ocws-popup-day-limit-notice" style="display: none; margin: 0.5em 0 0; font-size: 0.78em; color: #c00;"><span class="ocws-popup-day-limit-message"></span></p>

                <div id="popup-pickup-options" style="<?php echo $show_pickup_options? '' : 'display: none;' ?>">
                    <?php OCWS_LP_Local_Pickup::render_pickup_additional_fields(); ?>
                </div>

                <div id="popup-pickup-form-messages" style="<?php echo $show_pickup_options? '' : 'display: none;' ?>"></div>

                <div id="popup-form-messages" style=""></div>
                <input type="submit" class="button green" value="<?php _e('אישור' , 'ocws')?>">
            </form>
        </div><!--inner-wrapper-->
    </div><!--inner-->
</div><!--choose-shipping-popup-->
