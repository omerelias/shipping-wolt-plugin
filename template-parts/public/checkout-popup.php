
<div class="ocws-checkout-choose-city-popup choose-shipping-popup" style="">
    <div class="white-overlay"></div>
    <div class="inner">

        <div class="inner-wrapper">
            <div class="pop-close">
                <img src="<?php echo OCWS_ASSESTS_URL; ?>/images/cancel.svg" alt="">
            </div>

            <div class="ajax-message"></div>

            <!-- back to shop and choose localpickup function  -->
            <div class="additional-controlls">
                <a href="<?php echo get_site_url(); ?>" class="button popup-shipping-controll btn-primary"><?php _e( 'Back to shop', 'ocws' ); ?></a>
                <button value="localpickup" type="button" class="button popup-shipping-controll btn-primary"><?php _e( 'Choose local pickup', 'ocws' ); ?></button>
            </div>
            <button value="choose-city" type="button" class="button green popup-shipping-controll choose-city"><?php _e( 'Choose city', 'ocws' ); ?></button>

            <div class="choose-city-form--wrapper">
                <header>
                    <h2 class="entry-title crossed-title"><?php echo esc_attr( __('Please, choose your location', 'ocws') ); ?></h2>
                </header>

                <form id="ocws-checkout-choose-city-form" class="choose-shipping" action="" method="post">

                    <?php if (!ocws_use_google_cities_and_polygons()) { ?>

                    <div id="ocws-checkout-choose-city-options">
                        <?php
                        $use_simple_cities = !ocws_use_google_cities_and_polygons();
                        $use_polygons = ocws_use_google_cities_and_polygons();
                        $use_google_cities = ocws_use_google_cities();
                        $city_options = OC_Woo_Shipping_Groups::get_all_locations(true, $use_simple_cities, $use_polygons, $use_google_cities);
                        ?>
                        <?php if(isset($city_options)) { ?>

                            <div class="selected-city">
                                <select name="selected-city" class="ocws-enhanced-select">
                                    <!--<option selected disabled>בחר את אזור החלוקה שלך</option>-->
                                    <option selected disabled><?php echo esc_html(__('Select your distribution area', 'ocws')) ?></option>
                                    <?php foreach ($city_options as $code => $city_option):?>
                                        <option value="<?php echo $code?>"><?php echo $city_option?></option>
                                    <?php endforeach;?>
                                </select>
                            </div>
                        <?php } ?>
                    </div>

                    <?php } else { ?>

                        <div class="ocws-checkout-inputs">

                            <input type="text" class="input-text ocws-checkout-pac-input pac-target-input"
                                   name="billing_google_autocomplete" id="billing_google_autocomplete_p" placeholder="<?php echo esc_html(__('Enter your address here', 'ocws')) ?>" value="" autocomplete="off">

                            <input type="hidden" name="billing_city" id="billing_city_p" value="">

                            <input type="hidden" name="billing_city_code" id="billing_city_code_p" value="">

                            <input type="hidden" name="billing_city_name" id="billing_city_name_p" value="">

                            <input type="hidden" name="billing_street" id="billing_street_p" value="">

                            <input type="hidden" name="billing_house_num" id="billing_house_num_p" value="">

                            <input type="hidden" name="billing_address_coords" id="billing_address_coords_p" value="">

                        </div>

                    <?php } ?>

                    <div id="form-messages"></div>

                    <input id="checkout-popup-submit-btn" type="submit" class="button green" value="<?php _e('Continue' , 'ocws')?>">
                </form>
                <footer>
                    <button class="btn button back-to-main-popup" type="button" value=""><?php _e( 'Back', 'ocws' ) ?></button>
                </footer>
            </div>
        </div><!--inner-wrapper-->
    </div><!--inner-->
</div><!--choose-shipping-popup-->