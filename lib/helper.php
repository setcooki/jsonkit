<?php

if(!function_exists('jsonkit_init_options'))
{
    /**
     * @param $options
     * @param $object
     */
    function jsonkit_init_options($options, $object)
    {
        jsonkit_set_options($options, $object);
    }
}

if(!function_exists('jsonkit_has_option'))
{
    /**
     * @param $option
     * @param $object
     * @param bool $return
     * @return bool
     */
    function jsonkit_has_option($option, $object, $return = false)
    {
        $class = get_class($object);

        if(property_exists($object, 'options') && array_key_exists($object->options, $option))
        {
            return ((bool)$return) ? $object->options[$option] : true;
        }else if(property_exists($class, 'options') && array_key_exists($class::$options, $option)){
            return ((bool)$return) ? $class::$options[$option] : true;
        }
        return false;
    }
}

if(!function_exists('jsonkit_is_option'))
{
    /**
     * option shortcut function for jsonkit_has_option this will use jsonkit_has_option with third parameter strict = true
     * so option value has to be a valid value other then null, false, empty array. see jsonkit_has_option
     *
     * @see jsonkit_has_option
     * @param null|string $key expects the options key name to check
     * @param null|object|string|array $mixed expects value according to explanation above
     * @return bool
     */
    function jsonkit_is_option($option, &$object)
    {
        try
        {
            if(jsonkit_can_options($object))
            {
                $options = (array)\Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options');
                return ((array_key_exists($option, $options) && jsonkit_is_value($options[$option])) ? true : false);
            }
        }
        catch(\ReflectionException $e) {}
        return false;
    }
}

if(!function_exists('jsonkit_get_option'))
{
    /**
     * @param $option
     * @param $object
     * @param bool $default
     * @return mixed
     * @throws Exception
     */
    function jsonkit_get_option($option, $object, $default = false)
    {
        try
        {
            if(jsonkit_can_options($object))
            {
                $options = (array)\Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options');
                return (array_key_exists($option, $options)) ? $options[$option] : jsonkit_default($default);
            }
        }
        catch(\ReflectionException $e) {}
        return jsonkit_default($default);
    }
}

if(!function_exists('jsonkit_get_options'))
{
    /**
     * @param $object
     * @param array $default
     * @return array|mixed
     * @throws Exception
     */
    function jsonkit_get_options(&$object, $default = array())
    {
        try
        {
            if(jsonkit_can_options($object))
            {
                return (array)\Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options');
            }
        }
        catch(\ReflectionException $e) {}
        return jsonkit_default($default);
    }
}


if(!function_exists('jsonkit_set_option'))
{
    /**
     * @param $option
     * @param null $value
     * @param $object
     * @return mixed|null
     */
    function jsonkit_set_option($option, $value = null, &$object)
    {
        try
        {
            if(jsonkit_can_options($object))
            {
                $options = (array)\Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options');
                $options[$option] = $value;
                return \Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options', $options, true);
            }
        }
        catch(\ReflectionException $e) {}
        return null;
    }
}


if(!function_exists('jsonkit_set_options'))
{
    /**
     * @param $options
     * @param $object
     * @return mixed|null
     */
    function jsonkit_set_options($options, &$object)
    {
        $options = (array)$options;
        try
        {
            if(jsonkit_can_options($object))
            {
                $_options = (array)\Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options');
                $_options = array_merge($_options, $options);
                return \Setcooki\JsonKit\Reflection\Reflection::propertyFactory($object, 'options', $_options, true);
            }
        }
        catch(\ReflectionException $e) {}
        return null;
    }
}

if(!function_exists('jsonkit_can_options'))
{
    /**
     * @param null $class
     * @return bool
     */
    function jsonkit_can_options($class)
    {
        if(is_object($class))
        {
            $class = get_class($class);
        }
        return (bool)property_exists((string)$class, 'options');
    }
}

if(!function_exists('jsonkit_unset_option'))
{
    /**
     * @param $option
     * @param $object
     * @param null $default
     * @throws ReflectionException
     */
    function jsonkit_unset_option($option, $object, $default = null)
    {
        $class = get_class($object);

        if(jsonkit_has_option($option, $object))
        {
            if(property_exists($object, 'options'))
            {
                unset($object->options[$option]);
            }else if(property_exists($class, 'options')){
                $options = (array)\Setcooki\JsonKit\Reflection\Reflection::propertyFactory($class, 'options');
                if(array_key_exists($option, $options))
                {
                    unset($options[$option]);
                }
                \Setcooki\JsonKit\Reflection\Reflection::propertyFactory($class, 'options', $options, true);
            }
        }
    }
}

if(!function_exists('jsonkit_array_to_object'))
{
    /**
     * will convert array to std object recursive and preserving arrays with numeric indices
     *
     * @param array|mixed $value expects the (array) value to convert to object
     * @return object|mixed
     */
    function jsonkit_array_to_object($value)
    {
        if(is_array($value))
        {
            if(array_keys($value) === range(0, count($value) - 1))
            {
                return (array)array_map(__FUNCTION__, $value);
            }else{
                return (object)array_map(__FUNCTION__, $value);
            }
        }else{
            return $value;
        }
    }
}

if(!function_exists('jsonkit_object_to_array'))
{
    /**
     * will convert std object to array recursive
     *
     * @param object|mixed $value expects the std object to convert to array
     * @return array
     */
    function jsonkit_object_to_array($value)
    {
        if(is_object($value))
        {
            $value = get_object_vars($value);
        }
        if(is_array($value))
        {
       	    return array_map(__FUNCTION__, $value);
        }else{
       		return $value;
       	}
    }
}

if(!function_exists('jsonkit_array_get'))
{
    /**
     * get array value from array or array if key is not set or return
     * default value if key is not found. this function can deal with dot notation
     * to get get value from multidimensional associative array like config.database.pass
     * if no key is passed in second parameter will return first parameter directly. returns default value if key is not
     * found. default value also can be an exception which is thrown then
     *
     * @param array $array expects array to get key for
     * @param null|mixed $key expects key for value to get
     * @param null|mixed|Exception $default expects optional default value if value could not be retrieved by key
     * @return array|mixed|null
     * @throws Exception
     */
    function jsonkit_array_get(Array $array, $key = null, $default = null)
    {
        if($key !== null)
        {
            if(array_key_exists($key, $array))
            {
                return $array[$key];
            }
            foreach(explode('.', trim($key, '.')) as $k => $v)
            {
                if(!is_array($array) || !array_key_exists($v, $array))
                {
                    return jsonkit_default($default);
                }
                $array = $array[$v];
            }
            return $array;
        }else{
            return $array;
        }
    }
}

if(!function_exists('jsonkit_array_set'))
{
    /**
     * set value to array by reference. if second parameter key is not set
     * overwrites first parameter with value in third parameter. if key is set
     * will be added to array. key can be in dot notation e.g. config.database.user
     * creating the required dimensions to store the value in. most likly will
     * return changed array or value passed in third parameter
     *
     * @param array $array expects array to set value to
     * @param null|mixed $key expects key to set value for in array
     * @param null|mixed $value expects value for key to store in array
     * @return mixed|array|null
     */
    function jsonkit_array_set(Array &$array, $key = null, $value = null)
    {
        if($key === null)
        {
            return $array = $value;
        }
        if(strpos($key, '.') === false)
        {
            return $array[$key] = $value;
        }
        $keys = explode('.', trim($key, '.'));
        while(count($keys) > 1)
        {
            $key = array_shift($keys);
            if(!isset($array[$key]) || !is_array($array[$key]))
            {
                $array[$key] = array();
            }
            $array =& $array[$key];
        }
        $array[array_shift($keys)] = $value;
        return null;
    }
}

if(!function_exists('jsonkit_array_unset'))
{
    /**
     * unset key from array passed by reference. if the second parameter key is not
     * set will unset the complete array in first parameter. key can be in dot notation
     * e.g. config.database.user. function will iterate through array to look
     * for the right dimension to unset key at
     *
     * @param array $array expects the array to unset $key from
     * @param null|mixed $key expects optional key to unset
     * @return void
     */
    function jsonkit_array_unset(Array &$array, $key = null)
    {
        if($key === null)
        {
            $array = array();
        }else{
            if(array_key_exists($key, $array))
            {
                unset($array[$key]);
            }else{
                $keys = explode('.', trim($key, '.'));
                while(count($keys) > 1)
                {
                    $key = array_shift($keys);
                    if(!isset($array[$key]) or ! is_array($array[$key]))
                    {
                        return;
                  	}
                    $array =& $array[$key];
                }
                unset($array[array_shift($keys)]);
            }
        }
    }
}


if(!function_exists('jsonkit_array_merge'))
{
    /**
     * implementation of array_merge with preserves numeric keys and will not reorder these keys starting with
     * index 0. call this function with as much arrays needed
     *
     * @return array
     */
    function jsonkit_array_merge()
    {
        $tmp = array();

        foreach(func_get_args() as $a)
        {
            if(is_array($a))
            {
                foreach($a as $k => $v)
                {
                    $tmp[$k] = $v;
                }
            }
        }
        return $tmp;
    }
}

if(!function_exists('jsonkit_array_isset'))
{
    /**
     * @param array $array
     * @param null $key
     * @param bool $strict
     * @return bool
     */
    function jsonkit_array_isset(Array $array, $key = null, $strict = false)
    {
        if($key === null)
        {
            return (!empty($array)) ? true : false;
        }
        if(array_key_exists($key, $array))
        {
            if((bool)$strict)
            {
                return (jsonkit_is_value($array[$key])) ? true : false;
            }else{
                return true;
            }
        }
        foreach(explode('.', trim($key, '.')) as $k => $v)
        {
            if(!is_array($array) || !array_key_exists($v, $array))
            {
                return false;
            }
            $array = $array[$v];
        }
        if((bool)$strict)
        {
            return (jsonkit_is_value($array)) ? true : false;
        }else{
            return true;
        }
    }
}

if(!function_exists('jsonkit_is_value'))
{
    /**
     * check if a passed value is anything else but a null value, boolean false, or
     * empty array|string = all values which are not values or empty values returning false
     *
     * @param null|mixed $mixed expects value to check
     * @return bool
     */
    function jsonkit_is_value($mixed = null)
    {
        if(is_null($mixed))
        {
            return false;
        }
        if(is_bool($mixed) && $mixed === false)
        {
            return false;
        }
        if(is_array($mixed) && empty($mixed))
        {
            return false;
        }
        if(is_string($mixed) && $mixed === '')
        {
            return false;
        }
        return true;
    }
}


if(!function_exists('jsonkit_type'))
{
    /**
     * returns data type value as string of variable passed in first parameter.
     * if the second parameter is to true will convert string values that are
     * actually wrong casted into its proper data type - usually used on
     * database results or $_GET parameters e.g.
     *
     * @param null|mixed $value expects the variable to test
     * @param boolean $convert expects boolean value for converting string
     * @return null|string
     */
    function jsonkit_type($value = null, $convert = false)
    {
        if(is_string($value) && (bool)$convert)
        {
            if(is_numeric($value))
            {
                if((float)$value != (int)$value){
                    $value = (float)$value;
                }else{
                    $value = (int)$value;
               }
            }else{
                if($value === 'true' || $value === 'false')
                {
                    $value = (bool)$value;
                }
            }
        }

        if(is_object($value)){
            return 'object';
        }
        if(is_array($value)){
            return 'array';
        }
        if(is_resource($value)){
            return 'resource';
        }
        if(is_callable($value)){
            return 'callable';
        }
        if(is_file($value)){
            return 'file';
        }
        if(is_int($value)){
            return 'integer';
        }
        if(is_float($value)){
            return 'float';
        }
        if(is_bool($value)){
            return 'boolean';
        }
        if(is_null($value)){
            return 'null';
        }
        if(is_string($value)){
            return 'string';
        }
        return null;
    }
}

if(!function_exists('jsonkit_is_value'))
{
    /**
     * check if a passed value is anything else but a null value, boolean false, or
     * empty array|string = all values which are not values or empty values returning false
     *
     * @param null|mixed $mixed expects value to check
     * @return bool
     */
    function jsonkit_is_value($mixed = null)
    {
        if(is_null($mixed))
        {
            return false;
        }
        if(is_bool($mixed) && $mixed === false)
        {
            return false;
        }
        if(is_array($mixed) && empty($mixed))
        {
            return false;
        }
        if(is_string($mixed) && $mixed === '')
        {
            return false;
        }
        return true;
    }
}


if(!function_exists('jsonkit_default'))
{
    /**
     * default function that is supposed to act where class methods use default return value arguments which can have
     * different functions. the normal behaviour is return the default value once method logic fails. if you pass a callback
     * or exception as default value returning these value does not make sense - instead exception is thrown and callback
     * is called
     *
     * @param mixed $value expects the value to return or action to perform
     * @return mixed
     * @throws Exception
     */
    function jsonkit_default($value)
    {
        if(is_callable($value) || (is_string($value) && function_exists($value)))
        {
            return call_user_func($value);
        }else if($value instanceof Exception) {
            throw $value;
        }else if($value === 'exit'){
            exit(0);
        }
        return $value;
    }
}

if(!function_exists('jsonkit_property_exists'))
{
    /**
     * php < 5.3.0 compatible function of property exist
     *
     * @param string|object $class expects class to test
     * @param string $property expects property to test
     * @return bool
     * @throws ReflectionException
     */
    function jsonkit_property_exists($class, $property)
    {
        if(version_compare(PHP_VERSION, '5.3.0', '>='))
        {
            return property_exists($class, $property);
        }else{
            try
            {
                $class = new \ReflectionClass($class);
                return $class->hasProperty($property);
            }
            catch(ReflectionException $e){}
        }
        return false;
    }
}


if(!function_exists('jsonkit_regex_delimit'))
{
    /**
     * removes regex pattern delimiters including modifiers from pattern so the passed pattern can be placed inside php
     * regex function with already existing delimiters. the second argument will also allow for trimming of any chars and
     * beginning and end of pattern usually meta characters like ^$
     *
     * @param string $pattern expects the pattern to remove delimiters from
     * @param string $trim expects optional trim values
     * @return string
     */
    function jsonkit_regex_delimit($pattern, $trim = '')
    {
        $pattern = preg_replace('=^([^\s\w\\\]{1})([^\\1]*)\\1(?:[imsxeADSUXJu]*)?$=i', '\\2', trim((string)$pattern));
        $pattern = trim($pattern, " " .trim($trim));
        return $pattern;
    }
}