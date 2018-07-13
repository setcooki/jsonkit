<?php

namespace Setcooki\JsonKit\Reflection;

/**
 * Class Reflection
 * @package Setcooki\JsonKit\Reflection
 */
abstract class Reflection
{
    /**
     * factory method acts as shortcut method for Reflection factory methods:
     * classFactory, methodFactory, propertyFactory, constantFactory called like:
     * Reflection::factory('class'). The function can be overloaded with arguments
     * that will be passed to the factory method
     *
     * @param string $what expects the factory name as described above
     * @return mixed|null according to factory method
     * @throws \ReflectionException
     */
    final public static function factory($what)
    {
        $c = strtolower(trim($what, 'factory')) . 'Factory';
        $m = new \ReflectionMethod(__CLASS__, $c);
        return $m->invokeArgs(null, array_slice(func_get_args(), 1));
    }


    /**
     * class factory creates a class and initiates it passing additional args
     * to class constructor.
     *
     * @param string $class expects class name
     * @return null|object class instance
     * @throws \ReflectionException
     */
    final public static function classFactory($class)
    {
        $cls = null;
        $arg = null;
        $tmp = null;

        $_class = (is_object($class)) ? get_class($class) : $class;
        if(class_exists($class, true))
        {
            if(!is_object($class))
            {
                $class = (string)$class;
            }
            $arg = func_get_args();
            $cls = new \ReflectionClass($class);
            if(!$cls->isAbstract())
            {
                if(sizeof($arg) > 1)
                {
                    if(sizeof($arg) === 2 && is_array($arg[1]))
                    {
                        $tmp = $arg[1];
                    }else{
                        $tmp = array_slice($arg, 1);
                    }
                    if(!empty($tmp))
                    {
                        return $cls->newInstanceArgs($tmp);
                    }else{
                        return $cls->newInstance();
                    }
                }else{
                    return $cls->newInstance();
                }
            }else{
                throw new \ReflectionException(sprintf(_("Class: %s can not be instantiated since class is abstract"), $_class));
            }
        }else{
            throw new \ReflectionException(sprintf(_("Class: %s does not exist"), $_class));
        }
    }


    /**
     * method factory will call method of passed class which can be an instance of class or
     * class name as string. the function can be overloaded with additional arguments which will
     * be passed to invoke arguments on reflected method created for class or instance of class.
     * the function will return the classes methods return value
     *
     * @param string|object $class expects class name as string or instance of class
     * @param string $method expects the method factory to call
     * @return mixed|null
     * @throws \ReflectionException
     */
    final public static function methodFactory($class, $method)
    {
        $mtd = null;
        $arg = null;
        $tmp = null;

        $_class = (is_object($class)) ? get_class($class) : $class;
        if(method_exists($class, $method))
        {
            if(!is_object($class))
            {
                $class = (string)$class;
            }
            $arg = func_get_args();
            $mtd = new \ReflectionMethod($class ,$method);
            if(!$mtd->isProtected())
            {
                if(sizeof($arg) > 2)
                {
                    $tmp = array_slice($arg, 2);
                    if($mtd->isStatic())
                    {
                        return $mtd->invokeArgs(null, $tmp);
                    }else if(is_object($class)){
                        return $mtd->invokeArgs($class, $tmp);
                    }else{
                        return $mtd->invokeArgs(new $class(), $tmp);
                    }
                }else{
                    if($mtd->isStatic())
                    {
                        return $mtd->invoke(null);
                    }else if(is_object($class)){
                        return $mtd->invoke($class);
                    }else{
                        return $mtd->invoke(new $class());
                    }
                }
            }else{
                throw new \ReflectionException(sprintf(_("Method: %s of class: %s can not be invoked since method is protected"), $method, $_class));
            }
        }else{
            throw new \ReflectionException(sprintf(_("Class: %s or method: %s does not exist"), $_class, $method));
        }
    }


    /**
     * property factory set/get properties on passed class which can either be a instance of class
     * or class name as string. pass the property name in the second parameter and set the third
     * parameter to a the value to set to blank to read the property value.
     *
     * @param string|object $class expects the class name as string or class instance
     * @param string $property expects the property name
     * @param null|mixed $value expects if set a value to set or none to get the value
     * @return mixed|null
     * @throws \ReflectionException
     */
    final public static function propertyFactory($class, $property, $value = 'NIL')
    {
        $prop = null;
        $obj = null;
        $array = null;
        $return = null;

        $_class = (is_object($class)) ? get_class($class) : $class;
        $property = trim($property);
        $jailbreak = (func_num_args() === 4 && (bool)func_get_arg(3)) ? true : false;
        if(!is_object($class))
        {
            $class = (string)$class;
        }
        $obj = new \ReflectionClass($class);
        if($obj->hasProperty($property))
        {
            $prop = $obj->getProperty($property);
            if($prop->isStatic())
            {
                $props = $obj->getStaticProperties();
                if(array_key_exists($property, $props))
                {
                    if($value !== 'NIL')
                    {
                        if($prop->isProtected() && $jailbreak)
                        {
                            $prop->setAccessible(true);
                            $prop->setValue($class, $value);
                            $prop->setAccessible(false);
                        }else{
                            $obj->setStaticPropertyValue($property, $value);
                        }
                    }else{
                        return $props[$property];
                    }
                }else{
                    throw new \ReflectionException(sprintf(_("Static property: %s does not exist in class: %s"), $property, $_class));
                }
            }else{
                if(is_object($class))
                {
                    if($value !== 'NIL')
                    {
                        if($prop->isProtected() && $jailbreak)
                        {
                            $prop->setAccessible(true);
                            $prop->setValue($class, $value);
                            $prop->setAccessible(false);
                        }else{
                            $prop->setValue($class, $value);
                        }
                    }else{
                        if($prop->isProtected() && $jailbreak)
                        {
                            $prop->setAccessible(true);
                            $value = $prop->getValue($class);
                            $prop->setAccessible(false);
                            $return = $value;
                        }else{
                            $return = $prop->getValue($class);
                        }
                    }
                    $obj = null;
                    $prop = null;
                    return $return;
                }else{
                    throw new \ReflectionException(sprintf(_("Unable to set/get non static property for class: %s passed as string"), $_class));
                }
            }
        }else{
            throw new \ReflectionException(sprintf(_("Class: %s or property: %s does not exist"), $_class, $property));
        }

        return null;
    }


    /**
     * retrieve constant value or constant values for class. pass the class name as string or instance
     * of class in first parameter. expects either no value to return all constants or the constant
     * name to return the value of that constant. when passing a constant to get value for will look
     * for constant regardless of upper/lower case.
     *
     * @param string|object $class expects class name as string or instance of class
     * @param null $const expects optional constant name or no value to get all constants
     * @return null|string|array
     * @throws \ReflectionException
     */
    final public static function constantFactory($class = null, $const = null)
    {
        $_class = (is_object($class)) ? get_class($class) : $class;
        if(!is_object($class))
        {
            $class = (string)$class;
        }
        $obj = new \ReflectionClass($class);
        if($const !== null)
        {
            $const = trim((string)$const);
            if($obj->hasConstant(strtolower($const)) || $obj->hasConstant(strtoupper($const)) )
            {
                if($obj->hasConstant(strtolower($const)))
                {
                    return $obj->getConstant(strtolower($const));
                }
                if($obj->hasConstant(strtoupper($const)))
                {
                    return $obj->getConstant(strtoupper($const));
                }
            }else{
                throw new \ReflectionException(sprintf(_("Constant: %s does not exist in class: %s"), $const, $_class));
            }
        }else{
            return $obj->getConstants();
        }
        return null;
    }


    /**
     * checks if a property exists and is public regardless of being static or non static
     *
     * @param string|object $class expects class name as string or instance of class
     * @param string $property expects the property name to check for
     * @return bool
     */
    final public static function isPublicProperty($class, $property)
    {
        try
        {
            $obj = new \ReflectionClass($class);
            if($obj->hasProperty($property))
            {
                $prop = $obj->getProperty($property);
                return (bool)$prop->isPublic();
            }
        }
        catch(\ReflectionException $e){}
        return false;
    }


    /**
     * checks if a property exists and is static regardless of being public or protected
     *
     * @param string|object $class expects class name as string or instance of class
     * @param string $property expects the property name to check for
     * @return bool
     */
    final public static function isStaticProperty($class, $property)
    {
        try
        {
            $obj = new \ReflectionClass($class);
            if($obj->hasProperty($property))
            {
                $prop = $obj->getProperty($property);
                return (bool)$prop->isStatic();
            }
        }
        catch(\ReflectionException $e){}
        return false;
    }


    /**
     * checks whether a class method exists or not. if the secondary parameter $public is set
     * to true also checks for if the method is public or not.
     *
     * @param string|object $class expects class name as string or instance of class
     * @param string $method expects the name of the method to check for
     * @param bool $public expects boolean value whether to check if method must be public or not
     * @return bool
     */
    final public static function hasMethod($class, $method, $public = true)
    {
        try
        {
            if(!is_object($class))
            {
                $class = trim((string)$class);
            }
            $obj = new \ReflectionMethod($class, trim((string)$method));
            if((bool)$public && !$obj->isPublic())
            {
                return false;
            }
            return true;
        }
        catch(\ReflectionException $e){}
        return false;
    }


    /**
     * checks whether a class property exists or not. if the secondary parameter $public is set
     * to true also checks for if the property is public or not.
     *
     * @param string|object $class expects class name as string or instance of class
     * @param string $property expects property to check for
     * @param bool $public expects boolean value whether to check if property must be public or not
     * @return bool
     */
    final public static function hasProperty($class, $property, $public = true)
    {
        try
        {
            if(!is_object($class))
            {
                $class = trim((string)$class);
            }
            $obj = new \ReflectionProperty($class, trim((string)$property));
            if((bool)$public && !$obj->isPublic())
            {
                return false;
            }
            return true;
        }
        catch(\ReflectionException $e){}
        return false;
    }


    /**
     * check whether a class constant exists or not. if the secondary parameter $strict is set
     * to true will check case sensitive, if false not.
     *
     * @param string|object $class expects class name as string or instance of class
     * @param string $constant expects name of constant to check
     * @param bool $strict expects a boolean value whether to check case sensitive or case insensitive
     * @return bool
     */
    final public static function hasConstant($class, $constant, $strict = false)
    {
        try
        {
            if(!is_object($class))
            {
                $class = trim((string)$class);
            }
            $obj = new \ReflectionClass($class);
            if((bool)$strict)
            {
                if((bool)$obj->hasConstant(strtolower((string)$constant)) || (bool)$obj->hasConstant(strtoupper((string)$constant)))
                {
                    return true;
                }
            }else{
                if((bool)$obj->hasConstant((string)$constant))
                {
                    return true;
                }
            }
        }
        catch(\ReflectionException $e){}
        return false;
    }


    /**
     * merge array properties of a subclass by iterating through all parent classes
     * looking for the array property of the same name return merge value as array.
     * this function will leave all properties untouched which are not of type array
     *
     * @param string|object $object expects instance of class or class name as string
     * @param string $property expects the property name as string
     * @param null|mixed $default expects default return value if property does not exist
     * @return array|mixed|null
     * @throws \ReflectionException|\Exception
     */
    final public static function mergeProperty($object, $property, $default = null)
    {
        $p = null;
        $tmp = array();

        if(Reflection::hasProperty($object, $property))
        {
            $p = Reflection::propertyFactory($object, $property);
            if(is_array($p))
            {
                $tmp = $p;
            }else{
                return $p;
            }
        }
        if(get_parent_class($object) !== false)
        {
            $class = $object;
            while(($class = get_parent_class($class)))
            {
                if(jsonkit_property_exists($class, $property))
                {
                    $p = Reflection::propertyFactory($class, $property);
                    if(is_array($p))
                    {
                        $tmp = array_merge($tmp, $p);
                    }
                }
            }
        }
        return (!empty($tmp)) ? $tmp : jsonkit_default($default);
    }
}