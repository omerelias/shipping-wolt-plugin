/*global ajaxurl, wc_product_import_params */
;(function ( $, window ) {

    /**
     * citiesImportForm handles the import process.
     */
    var citiesImportForm = function( $form ) {
        this.$form           = $form;
        this.xhr             = false;
        this.position        = 0;
        this.file            = ocws_cities_import_params.file;
        this.delimiter       = ocws_cities_import_params.delimiter;
        this.security        = ocws_cities_import_params.import_nonce;

        // Number of import successes/failures.
        this.imported = 0;
        this.skipped  = 0;

        // Initial state.
        this.$form.find('.woocommerce-importer-progress').val( 0 );

        this.run_import = this.run_import.bind( this );

        // Start importing.
        this.run_import();
    };

    /**
     * Run the import in batches until finished.
     */
    citiesImportForm.prototype.run_import = function() {
        var $this = this;

        $.ajax( {
            type: 'POST',
            url: ajaxurl,
            data: {
                action          : 'oc_woo_shipping_do_ajax_cities_import',
                position        : $this.position,
                file            : $this.file,
                delimiter       : $this.delimiter,
                security        : $this.security,
                group_id        : ocws_cities_import_params.group_id
            },
            dataType: 'json',
            success: function( response ) {
                if ( response.success ) {
                    $this.position  = response.data.position;
                    $this.imported += response.data.imported;
                    $this.skipped  += response.data.skipped;
                    $this.$form.find('.woocommerce-importer-progress').val( response.data.percentage );

                    if ( 'done' === response.data.position ) {
                        var file_name = ocws_cities_import_params.file.split( '/' ).pop();
                        window.location = response.data.url +
                            '&cities-imported=' +
                            parseInt( $this.imported, 10 ) +
                            '&cities-skipped=' +
                            parseInt( $this.skipped, 10 ) +
                            '&file-name=' +
                            file_name;
                    } else {
                        $this.run_import();
                    }
                }
            }
        } ).fail( function( response ) {
            window.console.log( response );
        } );
    };

    /**
     * Function to call productImportForm on jQuery selector.
     */
    $.fn.ocws_cities_importer = function() {
        new citiesImportForm( this );
        return this;
    };

    $( '.woocommerce-importer' ).ocws_cities_importer();

})( jQuery, window );
