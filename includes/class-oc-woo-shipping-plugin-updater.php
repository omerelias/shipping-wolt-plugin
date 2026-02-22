<?php

if ( !function_exists('show') ){  
    function show( $data, $ex = '' ){
        echo '<h3  style="direction:ltr;text-align:left;">' . $ex .'</h3>';
        echo '<pre style="direction:ltr;text-align:left;">';
        print_r( $data );
        echo '</pre>';
    }
}


if ( !class_exists( 'Bitbucket_WP_Plugin_Updater' ) ){
    // Update WP plugin from private Bitbucket repository 
    class Bitbucket_WP_Plugin_Updater{
        private $slug;
        private $real_slug;
        private $plugin_data;

        /**
         * Add filters to check plugin version
         *
         * Bitbucket_Plugin_Updater constructor.
         *
         * @param $plugin_settings
         */
        public function __construct( $plugin_settings ) {
            $this->init_plugin_variables( $plugin_settings );
            $this->init_plugin_data();
            // clean transient for plugin update
            add_action( 'wp_after_admin_bar_render',             array( $this , 'clean_transient' ) );
            // filter for plugin updates , set transient object for plugin if he need to update 
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_transient' ) );
            add_filter( 'plugins_api',                           array( $this, 'set_plugin_info' ), 10, 3 );
            add_filter( 'upgrader_post_install',                 array( $this, 'post_install' ), 10, 3 );
            add_filter( 'upgrader_pre_install',                  array( $this, 'check_pre_install' ), 10, 3 );
            add_filter( 'http_request_args',                     array( $this, 'request_args' ), 10, 2 );
        }

        // set plugin variables to class properties 
        private function init_plugin_variables( $plugin_settings ){
            $this->plugin_file   = $plugin_settings['plugin_file'];
            $this->plugin_slug   = $plugin_settings['plugin_slug'];
            $this->host          = $plugin_settings['bb_host'];
            $this->download_host = $plugin_settings['bb_download_host'];
            $this->username      = $plugin_settings['bb_owner'];
            $this->password      = $plugin_settings['bb_password'];
            $this->project_name  = $plugin_settings['bb_project_name'];
            $this->repo          = $plugin_settings['bb_repo_name'];
            // APP token for auth requests, get from bitbucket!
            $this->bb_app_token  = $plugin_settings['bb_app_token'];
        }

        /**
         * Returns slug, real slug and plugin data
         */
        private function init_plugin_data() {
            $this->slug        = plugin_basename( $this->plugin_file );
            $this->real_slug   = $this->get_slug_name( $this->slug );
            $this->plugin_data = get_plugin_data( $this->plugin_file );
        }

        /**
         * Returns real slug name
         *
         * @param $slug plugin slug
         *
         * @return string real plugin slug
         */
        public function get_slug_name( $slug ) {
          if( strpos( $slug, '/') !== false ) {
            $pos = strpos( $slug, '/' );
            $slug = substr( $slug, 0, $pos );
          }
          return $slug; 
        }

        /**
         * Get the plugin version information from Bitbucket API
         */
        public function set_transient( $transient ) {
            // If we have checked the plugin data before, don't re-check
            if ( empty( $transient->checked ) || ! isset( $transient->checked[ $this->slug ] ) ) {
                return $transient;
            }
            // default - don't update the plugin
            $do_update = 0;
            // if bitbucket live
            if ( $this->is_repository_live() ) {
                // Get plugin & Bitbucket release information
                $this->get_repo_release_info();
                // Check the versions if we need to do an update
                // $do_update = version_compare( $this->check_version_name( $this->version ), $transient->checked[ $this->slug ] );
                $do_update = version_compare( $this->version, $transient->checked[ $this->slug ] );
            }
            // Update the transient to include our updated plugin data
            if ( $do_update == 1 ) {
                $package                            = $this->get_download_url();
                $this->download_link                = $package;
                // create object for transient plugin update
                $obj                                = new \stdClass();
                $obj->plugin                        = $this->slug;
                $obj->slug                          = $this->real_slug;
                $obj->new_version                   = $this->version;
                $obj->url                           = "website_url";
                $obj->package                       = $this->download_link;
                $transient->response[ $this->slug ] = $obj;
            }
            return $transient;
        }

        /*
            Filter the plugin data which will be shown in "show detail" lightbox.
            Parsedown package: used to strip the field content from a markdown file
            // TO DO need to include this package
        */
        public function set_plugin_info( $false, $action, $response ) {
            if ( 'plugin_information' == $action && $response->slug == $this->plugin_slug ) {
                // Get plugin & Bitbucket release information
                $this->init_plugin_data();
                if ( $this->is_repository_live() ) {
                    $this->get_repo_release_info();
                    // Add our plugin information
                    $response->last_updated = $this->commit_date;
                    $response->slug         = $this->real_slug;
                    $response->plugin_name  = $this->plugin_data["Name"];
                    $response->version      = $this->version;
                    $response->author       = $this->plugin_data["AuthorName"];
                    $response->name         = $this->plugin_data['Name'];
                    $response->homepage     = "testurl";

                    // This is our release download zip file
                    $response->download_link = $this->get_download_url();

                    $change_log = $this->change_log;

                    $matches = null;
                    preg_match_all( "/[##|-].*/", $this->change_log, $matches );
                    if ( ! empty( $matches ) ) {
                        if ( is_array( $matches ) ) {
                            if ( count( $matches ) > 0 ) {
                                $change_log = '<p>';
                                foreach ( $matches[0] as $match ) {
                                    if ( strpos( $match, '##' ) !== false ) {
                                        $change_log .= '<br>';
                                    }
                                    $change_log .= $match . '<br>';
                                }
                                $change_log .= '</p>';
                            }
                        }
                    }

                    // Create tabs in the lightbox
                    $response->sections = array(
                        'description' => $this->plugin_data["Description"],
                        'changelog'   => Parsedown::instance()->parse( $change_log )
                    );

                    // Gets the required version of WP if available
                    $matches = null;
                    preg_match( "/requires:\s([\d\.]+)/i", $this->change_log, $matches );
                    if ( ! empty( $matches ) ) {
                        if ( is_array( $matches ) ) {
                            if ( count( $matches ) > 1 ) {
                                $response->requires = $matches[1];
                            }
                        }
                    }

                    // Gets the tested version of WP if available
                    $matches = null;
                    preg_match( "/tested:\s([\d\.]+)/i", $this->change_log, $matches );
                    if ( ! empty( $matches ) ) {
                        if ( is_array( $matches ) ) {
                            if ( count( $matches ) > 1 ) {
                                $response->tested = $matches[1];
                            }
                        }
                    }

                    return $response;
                }
            }
            return $false;
        }

        /**
         * Perform additional actions to successfully install our plugin
         */
        public function post_install( $true, $hook_extra, $result ) {
            // Since we are hosted in Bitbucket, our plugin folder would have a dirname of
            // reponame-tagname change it to our original one:
            global $wp_filesystem;

            $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->real_slug . DIRECTORY_SEPARATOR;
            $wp_filesystem->move( $result['destination'], $plugin_folder );
            $result['destination'] = $plugin_folder;

            // Re-activate plugin if needed
            if ( $this->plugin_activated ) {
                activate_plugin( $this->real_slug );
            }

            return $result;
        }

        /**
         * Check if plugin is activated
         *
         * @param $true
         * @param $args
         */
        public function check_pre_install( $true, $args ) {
            $this->plugin_activated = is_plugin_active( $this->slug );
        }

        /**
         * Add Bitbucket credentials to request url
         *
         * @param $r
         * @param $url
         *
         * @return mixed
         */
        public function request_args( $r, $url ) {
            if ( strpos( $url, $this->check_download_url() ) !== false ) {
                $r['headers'] = array( 'Authorization' => 'Basic ' . base64_encode( "$this->username:$this->password" ) );
            }
            return $r;
        }

        /**
         * Check if the Bitbucket repository is live
         *
         * @return bool
         */
        public function is_repository_live() {
            $new_url = $this->host . "/2.0/repositories/" . $this->project_name . "/" . $this->repo;
            $headers = $this->get_token_auth_header();
            $request = wp_remote_get( $new_url, array( 'headers' => $headers ) );
            if ( ! is_wp_error( $request ) && $request['response']['code'] == 200 ) {
                return true;
            }
            return false;
        }

        /**
         * Get information regarding our plugin from Bitbucket
         * // TO DO 
        */
        private function get_repo_release_info() {
            // Only do this once
            if ( ! empty( $this->bb_api_result ) ) {
                return;
            }

            // Query the Bitbucket API
            $url    = $this->get_tag_url();
            $result = $this->get_bb_data( $url );
            // check if code response OK
            if ( $result['response']['code'] == 200 ) {
                $decoded_result      = json_decode( $result['body'] );
                $this->bb_api_result = $decoded_result;
                // first one is correct
                $latest_tag             = current( $decoded_result->values );

                // SOMETHING WRONG HERE TO DO 
                // $changelog  = $this->get_changelog_content( $latest_tag->target->hash );
                // if ( $changelog !== false ) {
                //     $this->change_log = $changelog;
                // } else {
                //     $this->change_log = $latest_tag->target->message;
                // }
                if ( !empty($latest_tag) ){
                    $this->version     = $latest_tag->name;
                    $this->commit_date = date( 'Y-m-d H:i:s', strtotime( $latest_tag->target->date ) );
                }
            }
        }

        /**
         * Get content of changelog.md file from Bitbucket
         *
         * @param $commit_hash
         *
         * @return string content of changelog
         *          bool    false if wp errors
         */
        protected function get_changelog_content( $commit_hash ) {
            $url        = 'https://bitbucket.org/' . $this->project_name . '/' . $this->repo . '/raw/' . $commit_hash . '/CHANGELOG.md';
            $headers    = $this->get_token_auth_header();
            $changelog  = wp_remote_get( $url, array( 'headers' => $headers ) );

            if ( is_wp_error( $changelog ) ) {
                return false;
            }

            return $changelog['body'];
        }

        /**
        * Returns ZIP download url
        **/
        public function get_download_url() {
            return "{$this->download_host}/{$this->project_name}/{$this->repo}/get/{$this->version}.zip";
        }

        /**
        * Returns download URL to validate against a string
        **/
        public function check_download_url() {
            return "{$this->download_host}/{$this->project_name}/{$this->repo}/get/";
        }

        /**
        * Returns url to check latest tag version in the Bitbucket repository.
        **/
        public function get_tag_url() {
            return "{$this->host}/2.0/repositories/{$this->project_name}/{$this->repo}/refs/tags?sort=-target.date";
        }

        /**
         * Returns Bitbucket API response
         * 
         * @param $url
         *
         * @return array|\WP_Error
         */
        private function get_bb_data( $url ) {
            $headers = array( 'Authorization' => 'Basic ' . base64_encode( "$this->username:$this->password" ) );
            $result  = wp_remote_get( $url, array( 'headers' => $headers ) );
            return $result;
        }

        // delete transient ( caching data for WP sites ) for plugin update
        public function clean_transient(){
            if ( isset($_GET['delete_transient'] ) ){
                delete_site_transient( 'update_plugins' );
            }
        }

        // get header for auth requests ( Token bearer )
        private function get_token_auth_header(){
            $headers = array(
                'Authorization' => 'Bearer ' . $this->bb_app_token,
                'Content-Type' => 'application/json',
            );

            return $headers;
        }

        // test function for version compare TO DO !
        public function check_version_name( $version ){
            return $version;
        }
    }
}


// Shell class for OC_Woo_Shipping_Plugin
class Oc_Woo_Shipping_Plugin_Updater {

    public function __construct(){
        $path_to_root_file  = OCWS_PATH. '/oc-woo-shipping.php';
        $plugin_slug        = 'oc-woo-shipping';
        // data from Bitbucket 
        $bb_project_name   = 'milla_oc';
        $bb_repo_name      = 'original-concepts-advanced-shipping-2';
        // App User name 
        $bb_owner          = 'Rost_Dan';
        // APP password, generates in Bitbucket
        $bb_password       = 'ATBBnSraJyzQYNqQqCjsXfeacUBB4DE65019';
        // APP token for auth requests, get from bitbucket!
        $bb_app_token      = 'ATCTT3xFfGN0GTgsABZG7Qc3_Rz7hjuyCagkpRmXix3vF492MhMcur3x6oi-KnlTCr2aE8P01beqlGoARVscwiwhTjyslGJ7eQTCUnBnG6h6-iiAhe8D1LF3gblF8vpIGFqiKAVNmstoXPU9fNuuK5YImpkaq6ZfNzWMyuVDfncy1oPjh0GCJP0=1A83483C';


        $plugin_settings    = array(
            'plugin_file'       => $path_to_root_file,
            'plugin_slug'       => $plugin_slug,
            'bb_host'           => 'https://api.bitbucket.org',
            'bb_download_host'  => 'http://bitbucket.org',
            'bb_owner'          => $bb_owner,
            'bb_password'       => $bb_password,
            'bb_project_name'   => $bb_project_name,
            'bb_repo_name'      => $bb_repo_name,
            'bb_app_token'      => $bb_app_token
        );


        if ( is_admin() ){
            new Bitbucket_WP_Plugin_Updater( $plugin_settings );
        }
    }
}

new Oc_Woo_Shipping_Plugin_Updater();