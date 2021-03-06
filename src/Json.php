<?php

namespace Setcooki\JsonKit;

/**
 * Class Json
 * common json tools
 * @package Setcooki\JsonKit
 */
abstract class Json
{
    /**
     * encode any object to json encoded string
     *
     * @see json_encode
     * @param mixed $value expects data to encode to json
     * @param int $options expects optional options
     * @throws \BadFunctionCallException
     * @return string
     */
    public static function encode($value, $options = 0)
    {
        if(function_exists('json_encode'))
        {
            return json_encode($value, $options);
        }else{
            throw new \BadFunctionCallException(_("Function json_encode not supported by system"));
        }
    }


    /**
     * decode a json string
     *
     * @see json_decode
     * @param string $json expects json string to decode
     * @param bool $assoc expects value whether to force objects into associative arrays
     * @param int $depth expects max recursion depth
     * @param int $options bitmask options
     * @return mixed
     * @throws \BadFunctionCallException
     */
    public static function decode($json, $assoc = false, $depth = 512, $options = 0)
    {
        if(function_exists('json_decode'))
        {
            if(version_compare(PHP_VERSION, '5.4.0', '>='))
            {
                return json_decode($json, $assoc, $depth, $options);
            }else{
                return json_decode($json, $assoc, $depth);
            }
        }else{
            throw new \BadFunctionCallException(_("Function json_decode not supported by system"));
        }
    }


    /**
     * load a json string or json file and decode it. will throw error if
     *
     * @see json_decode
     * @param string $json expects json string or json file to decode
     * @param bool $assoc expects value whether to force objects into associative arrays
     * @param int $depth expects max recursion depth
     * @param int $options bitmask options
     * @return mixed
     * @throws \UnexpectedValueException
     */
    public static function load($json, $assoc = false, $depth = 512, $options = 0)
    {
        if(is_string($json))
        {
            if(is_file($json))
            {
                $json = trim((string)file_get_contents($json));
            }
            if(static::isJson($json))
            {
                return static::decode($json, $assoc, $depth, $options);
            }
        }
        throw new \UnexpectedValueException(_("Input argument 1 is not a decodable json value"));
    }


    /**
     * check if a string is a json encoded string or not
     *
     * @param string $json expects the string to check for
     * @return bool
     */
    public static function isJson($json)
    {
        if(!empty($json) && is_string($json))
        {
            if(function_exists('json_last_error'))
            {
                @static::decode($json);
                return (json_last_error() === JSON_ERROR_NONE);
            }else{
                return (@json_decode($json) !== NULL && (is_object(json_decode($json, false) || is_array(json_decode($json, true))))) ? true : false;
            }
        }
        return false;
    }


    /**
     * dump a json object or already encoded json string to output stream with appropriate content type header
     *
     * @param string|mixed $data expects either a already encoded json string or data to json convert
     * @return void
     */
    public static function dump($data)
    {
        header('Content-type: application/json');
        if(!static::isJson($data))
        {
            echo json_encode($data);
        }else{
            echo $data;
        }
    }


    /**
     * prettify/transform a json string to be human readable
     *
     * @param string $json expects the json string to be transformed
     * @return string
     */
    public static function prettify($json)
    {
        $result = '';
        $level = 0;
        $prev_char = '';
        $in_quotes = false;
        $ends_line_level = null;
        $json_length = strlen($json);

        for($i = 0; $i < $json_length; $i++)
        {
            $char = $json[$i];
            $new_line_level = null;
            $post = "";
            if($ends_line_level !== null)
            {
                $new_line_level = $ends_line_level;
                $ends_line_level = null;
            }
            if( $char === '"' && $prev_char != '\\')
            {
                $in_quotes = !$in_quotes;
            }else if(!$in_quotes){
                switch($char)
                {
                    case '}': case ']':
                        $level--;
                        $ends_line_level = NULL;
                        $new_line_level = $level;
                        break;
                    case '{': case '[':
                        $level++;
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = " ";
                        break;

                    case " ": case "\t": case "\n": case "\r":
                        $char = "";
                        $ends_line_level = $new_line_level;
                        $new_line_level = NULL;
                        break;
                }
            }
            if( $new_line_level !== null)
            {
                $result .= "\n".str_repeat("\t", $new_line_level);
            }
            $result .= $char.$post;
            $prev_char = $char;
        }
        return $result;
    }


    /**
     * get the last error from json operation if last error can be obtained. will return null
     * if there is not error or the function is not supported
     *
     * @return null|string
     */
    public static function error()
    {
        if(function_exists('json_last_error'))
        {
            switch(json_last_error())
            {
                case JSON_ERROR_DEPTH:
                    return 'maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    return 'underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    return 'unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    return 'syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    return 'malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    return null;
            }
        }
        return null;
    }


    /**
     * convert any value to default json conform php object
     *
     * @param mixed $mixed expects any value
     * @return mixed
     */
    public static function convert($mixed)
    {
        return static::decode(static::encode($mixed), false);
    }
}