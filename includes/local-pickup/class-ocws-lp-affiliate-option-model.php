<?php

defined( 'ABSPATH' ) || exit;

class OCWS_LP_Affiliate_Option_Model {

    public $option_name = '';
    public $option_value = '';
    public $use_default = true;
    public $default = '';

    public function __construct($name, $value, $use_default=true, $default='') {

        $this->option_name = $name;
        $this->option_value = $value;
        $this->use_default = !!$use_default;
        $this->default = $default;
    }

    public function __serialize() {
        return [
            'option_name' => $this->option_name,
            'option_value' => $this->option_value,
            'use_default' => $this->use_default,
            'default' => $this->default,
        ];
    }
}