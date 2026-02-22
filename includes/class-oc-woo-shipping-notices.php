<?php

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Notices {

    /**
     * Check if a notice has already been added.
     *
     * @param  string $message The text to display in the notice.
     * @param  string $notice_type Optional. The name of the notice type - either error, success or notice.
     * @param  string $context
     * @return bool
     */
    public static function has_notice( $message, $notice_type = 'success', $context = 'ocws_notices' ) {

        $notices = WC()->session->get( $context, array() );
        $notices = isset( $notices[ $notice_type ] ) ? $notices[ $notice_type ] : array();
        return array_search( $message, $notices, true ) !== false;
    }

    /**
     * Add and store a notice.
     *
     * @param string $message     The text to display in the notice.
     * @param string $notice_type Optional. The name of the notice type - either error, success or notice.
     */
    public static function add_notice( $message, $notice_type = 'success', $context = 'ocws_notices' ) {
        $notices = WC()->session->get( $context, array() );

        if ( ! empty( $message ) ) {
            if (!isset( $notices[ $notice_type ] )) {
                $notices[ $notice_type ] = array();
            }
            $notices[ $notice_type ][] = $message;
        }

        WC()->session->set( $context, $notices );
    }

    /**
     * Set all notices at once.
     *
     * @param array[] $notices Array of notices.
     */
    public static function set_notices( $notices, $context = 'ocws_notices' ) {

        WC()->session->set( $context, $notices );
    }

    /**
     * Unset all notices
     */
    public static function clear_notices( $incl_permanent = false, $context = 'ocws_notices') {

        $permanent_notices = self::get_notices( 'permanent-notice' );
        $permanent_success_notices = self::get_notices( 'permanent-success' );
        $permanent_hidden_notices = self::get_notices( 'permanent-hidden' );

        WC()->session->set( $context, null );

        if (!$incl_permanent) {
            $notices = array();
            if (count( $permanent_hidden_notices ) > 0) {
                $notices['permanent-hidden'] = $permanent_hidden_notices;
            }
            if (count( $permanent_notices ) > 0) {
                $notices['permanent-notice'] = $permanent_notices;
            }
            if (count( $permanent_success_notices ) > 0) {
                $notices['permanent-success'] = $permanent_success_notices;
            }
            if (count( $notices ) > 0) {
                WC()->session->set($context, $notices);
            }
        }
    }

    public static function get_notices( $type, $context = 'ocws_notices' ) {

        $all_notices  = WC()->session->get( $context, array() );

        if ( isset( $all_notices[ $type ] ) ) {

            return  $all_notices[ $type ];

        }
        return array();
    }

    /**
     * Prints messages and errors which are stored in the session, then clears them.
     *
     * @param bool $return true to return rather than echo.
     * @return string|null
     */
    public static function print_notices( $return = false, $context = 'ocws_notices' ) {
        $all_notices  = WC()->session->get( $context, array() );
        $notice_types = array( 'error', 'success', 'notice', 'permanent-success', 'permanent-notice' );
        // Buffer output.
        ob_start();

        foreach ( $notice_types as $notice_type ) {
            if ( self::notice_count( $notice_type ) > 0 ) {

                foreach ( $all_notices[ $notice_type ] as $notice ) {

                    ?>
                        <div class="<?php echo 'ocws-notice-' . str_replace('permanent-', '', $notice_type); ?> <?php echo $notice_type; ?>">
                            <?php echo /*ocws_kses_notice*/( $notice ); ?>
                        </div>
                    <?php
                }
            }
        }

        self::clear_notices();

        $notices = ( ob_get_clean() );

        if ( $return ) {
            return $notices;
        }

        echo $notices;
        return null;

    }

    /**
     * Get the count of notices added, either for all notices (default) or for one.
     * particular notice type specified by $notice_type.
     *
     * @param  string $notice_type Optional. The name of the notice type - either error, success or notice.
     * @return int
     */
    public static function notice_count( $notice_type = '', $context = 'ocws_notices' ) {

        $notice_count = 0;
        $all_notices  = WC()->session->get( $context, array() );

        if ( isset( $all_notices[ $notice_type ] ) ) {

            $notice_count = count( $all_notices[ $notice_type ] );

        } elseif ( empty( $notice_type ) ) {

            foreach ( $all_notices as $notices ) {
                $notice_count += count( $notices );
            }
        }

        return $notice_count;
    }
}