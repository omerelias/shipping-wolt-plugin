<?php

defined( 'ABSPATH' ) || exit;

class OCWS_LP_Activator {

    public static function activate() {

        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        /*
         * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
         * As of WP 4.2, however, they moved to utf8mb4, which uses 4 bytes per character. This means that an index which
         * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
         */
        $max_index_length = 191;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = "
CREATE TABLE {$wpdb->prefix}oc_woo_shipping_affiliates (
  aff_id BIGINT UNSIGNED NOT NULL auto_increment,
  aff_name varchar(200) NOT NULL,
  aff_address TEXT,
  aff_descr TEXT,
  aff_order BIGINT UNSIGNED NOT NULL,
  is_enabled tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (aff_id)
) $collate;
		";

        error_log('OCWS_LP_Activator before dbDelta');
        dbDelta( $tables );
        error_log('OCWS_LP_Activator after dbDelta');
    }

    public static function deactivate() {

    }

    public static function uninstall() {

        global $wpdb;

        $tables = array(
            "{$wpdb->prefix}oc_woo_shipping_affiliates",
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }
}