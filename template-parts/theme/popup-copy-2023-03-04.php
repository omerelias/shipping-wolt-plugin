<div class="choose-shipping-popup">
    <div class="white-overlay"></div>
    <div class="inner">

        <div class="inner-wrapper">
            <div class="pop-close">
                <img src="<?php echo OCWS_ASSESTS_URL; ?>/images/cancel.svg" alt="">
            </div>
            <header>
                <!--<button type="button" class="close" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>-->
                <?php if(get_field('shipping_popup_icon' , 'option')):?>
                    <div class="icon">
                        <img src="<?php the_field('shipping_popup_icon' , 'option')?>">
                    </div>
                <?php endif;?>
                <h2 class="entry-title crossed-title"><?php echo esc_attr( ocws_get_multilingual_option('ocws_common_popup_title') ); ?></h2>
            </header>

            <form id="choose-shipping" class="choose-shipping" action="" method="post">
                <?php $shipping_zones = WC_Shipping_Zones::get_zones();
                $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
                $chosen_shipping = $chosen_methods[0];
                $show_shipping_options = false;
                $show_pickup_options = false;

                $affs_ds = new OCWS_LP_Affiliates();
                $affiliates_dropdown = $affs_ds->get_affiliates_dropdown(true);

                // var_dump($chosen_shipping);
                ?>
                <div class="ship-choose">
                    <?php if($shipping_zones):$count=0;?>
                        <?php foreach($shipping_zones as $shipping_zone):
                            $shipping_methods = $shipping_zone['shipping_methods'];
                            $city_options = OC_Woo_Shipping_Groups::get_all_locations(true);
                            ?>
                            <?php foreach($shipping_methods as $shipping_method):
                            //if ( isset( $shipping_method->enabled ) && 'yes' === $shipping_method->enabled )
                            $shipping_attr = $shipping_method->id.':'.$shipping_method->instance_id;
                            ?>

                            <?php if( isset( $shipping_method->enabled ) && 'yes' === $shipping_method->enabled ):?>
                            <div class="shipping-method-wraper <?php echo $shipping_attr;?>">
                                <div class="radio-wrapper">
                                    <input data-title="<?php echo ocws_translate_shipping_method_title( $shipping_method->title, $shipping_attr ) ?>" type="radio" <?php echo $chosen_shipping ? ($chosen_shipping == $shipping_attr ? 'checked' : '') : ($count == 0 ? 'checked' : '') ?> name="popup-shipping-method" value="<?php echo $shipping_attr?>" id="<?php echo $shipping_attr?>">
                                    <label for="<?php echo $shipping_attr?>"><?php echo ocws_translate_shipping_method_title( $shipping_method->title, $shipping_attr );?></label>
                                    <span class="radiocheck"></span>
                                </div>
                                <?php
                                if ($chosen_shipping && ($chosen_shipping == $shipping_attr)) {
                                    if (ocws_is_method_id_shipping($shipping_method->id)) {
                                        $show_shipping_options = true;
                                    }
                                    else if (ocws_is_method_id_pickup($shipping_method->id)) {
                                        $show_pickup_options = true;
                                    }
                                }
                                ?>


                            </div>
                        <?php endif;?>
                            <?php $count++;endforeach;?>
                        <?php endforeach;?>
                    <?php endif;?>
                </div>
                <div id="popup-shipping-options" style="<?php echo $show_shipping_options? '' : 'display: none;' ?>">
                    <?php if(isset($city_options)) { ?>
                        <?php //if(get_field('shipping_text' , 'option')):?>
                        <div class="shipping-description">
                            <?php do_action( 'ocws_shipping_popup_decription'); ?>
                        </div>
                        <?php //endif;?>
                        <div class="selected-city">
                            <select name="selected-city" class="ocws-enhanced-select">
                                <option selected disabled><?php echo esc_html(__('Select your distribution area', 'ocws')) ?></option>
                                <?php foreach ($city_options as $code => $city_option):?>
                                    <option value="<?php echo $code?>"><?php echo $city_option?></option>
                                <?php endforeach;?>
                            </select>
                        </div>
                    <?php } ?>
                </div>

                <div id="popup-shipping-form-messages" style="<?php echo $show_shipping_options? '' : 'display: none;' ?>"></div>

                <div id="popup-shipping-city-slots" style="<?php echo $show_shipping_options? '' : 'display: none;' ?>"></div>

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