<?php
if (!defined('LEGALFILES_DIR')) {
    define('LEGALFILES_DIR', basename(__DIR__));
}

Object::useCustomClass('DateField', 'BetterDateField', true);