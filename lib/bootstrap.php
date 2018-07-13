<?php

require_once dirname(__FILE__) . '/../lib/helper.php';

if(!function_exists('_'))
{
    /**
     * @param $message
     * @return mixed
     */
    function _($message){
        return $message;
    }
}