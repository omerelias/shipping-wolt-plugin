<?php

defined( 'ABSPATH' ) || exit;

class OCWS_LP_General_Option_Model {

    public $option_name = '';
    public $option_value = '';
    public $default = '';

    public function __construct($name, $value, $default='') {

        $this->option_name = $name;
        $this->option_value = $value;
        $this->default = $default;
    }

    public function __serialize() {
        return [
            'option_name' => $this->option_name,
            'option_value' => $this->option_value,
            'default' => $this->default,
        ];
    }
}