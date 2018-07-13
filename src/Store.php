<?php

namespace Setcooki\JsonKit;

use Setcooki\JsonKit\Exception\EncryptionException;
use Setcooki\JsonKit\Exception\InvalidFileException;
use Setcooki\JsonKit\Exception\InvalidOverrideException;
use Setcooki\JsonKit\Exception\TypeMissmatchException;
use Setcooki\JsonKit\Exception\EmptyResultException;
use Setcooki\JsonKit\Exception\UnsupportedAlgoException;

/**
 * Class Store
 * @package Setcooki\JsonKit
 */
class Store
{
    /**
     * base64 algo for encode/decode functions
     *
     * @const BASE64
     */
    const BASE64                    = 'BASE64';

    /**
     * json algo for encode/decode functions
     *
     * @const JSON
     */
    const JSON                      = 'JSON';

    /**
     * rot13 algo for encode/decode functions
     *
     * @const ROT13
     */
    const ROT13                     = 'ROT13';

    /**
     * unix to unix algo for encode/decode functions
     *
     * @const UU
     */
    const UU                        = 'UU';

    /**
     * defines the stores encryption key if encryption is used. NOTE: the key can also be passed when importing/exporting
     * only - only pass key as class option if class constructor is used to import object since key otherwise wont be present.
     * also key is not stored actually as class option but copied to internal key variable so key will be lost when
     * serialising store
     *
     * @const ENCRYPTION_KEY
     */
    const ENCRYPTION_KEY            = 'UTIL_STD_STORE_ENCRYPTION_KEY';

    /**
     * defines the stores encryption cipher if encryption is used. the cipher must be a string value valid against php´s
     * mcrypt_get_cipher_name() function and defaults to MCRYPT_RIJNDAEL_256 constant
     *
     * @const ENCRYPTION_CIPHER
     */
    const ENCRYPTION_CIPHER         = 'UTIL_STD_STORE_ENCRYPTION_CIPHER';

    /**
     * defines the stores encryption algo if encryption is used. the algo must be a string value valid against php´s hash_algos()
     * function and defaults to md5
     *
     * @const ENCRYPTION_ALGO
     */
    const ENCRYPTION_ALGO           = 'UTIL_STD_STORE_ENCRYPTION_ALGO';

    /**
     * defines the stores encryption cipher mode if encryption is used. the mode must be a string value valid against php´s
     * mcrypt_list_modes function and defaults to cbc
     *
     * @const ENCRYPTION_MODE
     */
    const ENCRYPTION_MODE           = 'UTIL_STD_STORE_ENCRYPTION_MODE';

    /**
     * defines a custom encryption callback if encryption is used. the callback will receive the stores object, key and mode
     * and needs to return the encrypted or decrypted object. this way stores internal encryption methods can be completely
     * bypassed
     *
     * @const ENCRYPTION_CALLBACK
     */
    const ENCRYPTION_CALLBACK       = 'UTIL_STD_STORE_ENCRYPTION_CALLBACK';


    /**
     * contains the std object either constructed by class or passed/loaded in constructor
     *
     * @var mixed|null
     */
    public $object = null;

    /**
     * contains result when using Store::query mode
     *
     * @var mixed|null
     */
    private $_result = null;

    /**
     * internal result mode pointer once query mode is used will set this variable to boolean false and if exiting query
     * mode will be set to boolean true again
     *
     * @var bool
     */
    private $_init = true;

    /**
     * contains the stores encryption key if encryption is used and key needs to be stored in store itself instead of
     * passing key to export/import methods
     *
     * @var mixed|null
     */
    private $_key = null;

    /**
     * contains dynamic path when using class overloading for getter/setter methods
     *
     * @var array
     */
    protected $_path = array();

    /**
     * contains the file name pointer passed as first possible class constructor value
     *
     * @var null|string
     */
    protected $_file = null;

    /**
     * options default value array containing all class option default values
     *
     * @var array
     */
    public $options = array
    (
        self::ENCRYPTION_CIPHER     => 'rijndael-256',
        self::ENCRYPTION_ALGO       => 'md5',
        self::ENCRYPTION_MODE       => 'cbc'
    );


    /**
     * class constructor can receive the following as first argument:
     * - null (do not initialise with object)
     * - object expects std object to build store from
     * - array expects array to build store from
     * - string which is a serialized std object
     * - file name pointer that exists (will load object from file)
     * - file name pointer that does not exists but will be used as save location
     * will throw exception if any of the above values fails to validate. NOTE: return values from certain methods
     * are returning a reference to the object of the store. the reference can be manipulated outside the class if
     * the methods are called like $ref =& $store->get('//path');
     *
     * @param null|mixed $mixed expects one of the above value options
     * @param null|mixed $options expects optional class instance options
     * @throws \Exception
     */
    public function __construct($mixed = null, $options = null)
    {
        jsonkit_init_options($options, $this);

        if(jsonkit_has_option(self::ENCRYPTION_KEY, $this))
        {
            $this->_key = jsonkit_get_option(self::ENCRYPTION_KEY, $this);
            jsonkit_unset_option(self::ENCRYPTION_KEY, $this);
        }
        if($mixed !== null)
        {
            $this->import($mixed, $this->_key);
        }
    }


    /**
     * static method to create a class instance
     *
     * @see Store::__constructor
     * @param null|mixed $mixed expects one of the above value options
     * @param null|mixed $options expects optional class instance options
     * @return Store
     */
    public static function create($mixed = null, $options = null)
    {
        $class = get_called_class();
        return new $class($mixed, $options);
    }


    /**
     * legacy function to get result from query mode and init or reset result variable holder to perform new queries on object.
     * this function should NOT be used anymore - use Store::get instead to break query mode and return result.
     * NOTE: in prior implementation the init function was not returning the result but instead class instance - please
     * change your implementation accordingly if you use this method
     *
     * @return mixed
     */
    public function &init()
    {
        $this->_init = true;
        return $this->_result;
    }


    /**
     * retrieve or set object if not done so in constructor or add method. this function may be used it the constructor
     * is used to set the target save file path
     *
     * @param null|mixed $object expects optional object value which should be an array or object
     * @return null|object
     */
    public function &object($object = null)
    {
        if($object !== null)
        {
            $this->init();
            if(is_object($object))
            {
                $this->object =& $object;
            }else{
                $this->object = jsonkit_array_to_object((array)$object);
            }
        }
        return $this->object;
    }


    /**
     * iterate through stores object by calling callback on each object item starting from first item of object and iterating
     * through all other items or key => value pairs until end of object. the callback will receive the following arguments:
     * 0 = the item key
     * 1 = the item value (with reference)
     * 2 = the path to the item
     * 3 = a reference to the store instance
     * make sure callback will process arguments accordingly. if callback returns boolean false will stop iterating immediately
     * so callback will also not be called again. the fourth argument, the reference to instance, can be used to manipulate
     * the store directly in your callback. also the value passed in second argument is a reference changing the value
     * will change the value in the store. remember to use & reference operator to access all variable as reference like:
     *
     * <code>
     *      function my_callback($key, &$value, $path, &$store){}
     * </code>
     *
     * @param callback $callback expects valid callback
     * @return void
     * @throws \BadFunctionCallException
     */
    public function iterate($callback)
    {
        if(is_callable($callback))
        {
            try
            {
                $t =& $this;
                $func = function(&$object, $path = '/') use (&$func, &$t, $callback)
                {
                    foreach($object as $k => &$v)
                    {
                        if(is_object($v) || is_array($v))
                        {
                            $func($v, "/" . trim($path, ' /') . "/$k");
                        }
                        if(call_user_func_array($callback, array($k, &$v, "/" . trim($path, ' /') . "/$k", $t)) === false)
                        {
                            throw new \Exception("Stopping iteration");
                        }
                    }
                };
                $func($this->object());
            }
            catch(\Exception $e){}
        }else{
            throw new \BadFunctionCallException(_("Passed callback in first argument is not a valid callback"));
        }
    }


    /**
     * query the object with path and query parameters and get/set or manipulate result with other store functions. the
     * query method does not return the result - it returns reference to class instance to construct complex queries. in
     * order to get the result use ::get method which also will reset the result object so a new query can be started from
     * object and not from result from previous queries. see Store::find for expected arguments. use like
     *
     * <code>
     * Store::create($object)
              ->query('/firstElement', array("id=1000"))
              ->query('.', array('title=foo'))
              ->set|get();
     * </code>
     *
     * @see Query::find
     * @param string $path expects the query path
     * @param null|string|array $query expect optional query conditions with or without logical connectors
     * @param null|string|int|array $filter expects optional result filter flags
     * @return $this
     * @throws \Exception
     */
    public function query($path, $query = null, $filter = null)
    {
        if($this->_init === true)
        {
            $this->_result =& Query::find($this->object, $path, $query, $filter);
            $this->_init = false;
        }else{
            $this->_result =& Query::find($this->_result, $path, $query, $filter);
        }
        return $this;
    }


    /**
     * get object stored in path, get result from query when in query mode or get object by path. if the first argument
     * is null will either return the object in store or when in query mode the result of query. if the first argument
     * is not null is expecting a path like '/root/item1' to retrieve objects at path
     *
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @return mixed
     * @throws \Exception
     */
    public function &get($mixed = null)
    {
        if($mixed === null)
        {
            if(is_null($this->_result))
            {
                return $this->object;
            }else{
                return $this->init();
            }
        }else{
            return static::_get($this->object, $mixed);
        }
    }


    /**
     * legacy method this returns and resets the query result - see Store::init. this method is deprecated
     *
     * @deprecated since version 1.0
     * @see Store::init
     * @return mixed
     */
    public function &rewind()
    {
        return $this->init();
    }


    /**
     * test if path passed in first argument returns a valid return value which is not the default value from query class
     * or an exception thrown from query class for queries that do not find anything. if the first argument is null will
     * test if the store has a result stored from query mode or not.
     *
     * @param null|string|array $mixed expects either null or query path as string or array
     * @return bool
     * @throws \Exception
     */
    public function has($mixed = null)
    {
        $default = Query::$options[Query::DEFAULT_VALUE];

        if($mixed === null)
        {
            return (!is_null($this->_result)) ? true : false;
        }else{
            try
            {
                return ($this->get($mixed) !== $default) ? true : false;
            }
            catch(EmptyResultException $e){}
        }
        return false;
    }


    /**
     * set or extend the stores object with a value in second argument by path/query in first argument. see
     * Store::_set for more information on how to set values with path/query. the set method will overwrite
     * existing values at path/query and even extend the object if path/query is not set. this method is the preferred way
     * to manipulate the store objects values. the third argument is legacy only it can be used to set a value at path/query
     * which is an array and position will address the index on where to set the value to. the following values are recognized:
     *
     * - 0|first = set value at first position of array
     * - -1|last = set value at last position of array
     * - [1-?] = a number that is not -1 to set the value at a specific index position of array
     *
     * the preferred method to set a value at array index prior defined by third argument is to use the path/query argument
     * directly like:
     *
     * <code>
     *  $store->set('/store/array/0', $value);
     *  $store->set('/store/array/-1', $value);
     * </code>
     *
     * @see Store::_set
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param mixed $value expects the value to set at path/query
     * @param null|string|int $position expects the optional position (legacy)
     * @return $this
     * @throws \Exception
     */
    public function set($mixed = null, $value, $position = null)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, $value, 2, $position, false);
        }else{
            static::_set($this->_result, $mixed, $value, 2, $position, false);
        }
        return $this;
    }


    /**
     * add object/array or key => value pair to store if nothing is set yet at path. this method is intended to fill or
     * create the object without using the class constructor capabilities usually in a scenario where multiple objects or
     * key => value pairs are received from other functionality to furnish the store prior to manipulation. NOTE: if any
     * value, also NULL, is found under path will not overwrite the value already set at path! if the path does not exist
     * will create the needed child elements just like set method - no exception is thrown
     *
     * @see Store::_set
     * @param string $path expects the path where to add the value
     * @param string|mixed $mixed expects either object/array or key/item name
     * @param string|mixed $value expects a value which is not __NIL__ when setting key => value pairs
     * @return $this
     * @throws \Exception
     */
    public function add($path, $mixed, $value = '__NIL__')
    {
        if($this->object === null)
        {
            $this->object = new \stdClass();
        }
        if((is_object($mixed) || is_array($mixed)) && $value === '__NIL__')
        {
            $mixed = (object)$mixed;
        }else{
            if($path === '/' || $path === null)
            {
                $path = "/$mixed"; $mixed = $value;
            }else{
                $mixed = (object)array($mixed => $value);
            }
        }
        if($this->_init)
        {
            static::_set($this->object, $path, $mixed, 3, -1, false);
        }else{
            static::_set($this->_result, $path, $mixed, 3, -1, false);
        }
        return $this;
    }


    /**
     * replace a value at path or path/query in first argument either typesafe making sure the set value is of the same
     * type as value found at path/query or if set to false overwriting it regardless of data type. NOTE: if nothing is
     * found at path/query will throw exception if underlying query class has exceptions set as default return! - the
     * stores object will never be extended by query/path and value!

     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param mixed $value expects value to replace at path/query
     * @param bool $typesafe expects type safe boolean value
     * @return $this
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function replace($mixed = null, $value, $typesafe = false)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, $value, -1, null, $typesafe);
        }else{
            static::_set($this->_result, $mixed, $value, -1, null, $typesafe);
        }
        return $this;
    }


    /**
     * append value in second argument at path or path/query in first argument. this method is intended to be used to
     * append values to arrays at path/query. this function will always append the value no exceptions are thrown
     *
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param mixed $value expects the value to append at path/query
     * @return $this
     * @throws \Exception
     */
    public function append($mixed = null, $value)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, $value, true, -1, false);
        }else{
            static::_set($this->_result, $mixed, $value, true, -1, false);
        }
        return $this;
    }


    /**
     * prepend value in second argument at path or path/query in first argument. this method is intended to be used to
     * prepend values to arrays at path/query. this function will always prepend the value no exceptions are thrown
     *
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param mixed $value expects the value to prepend at path/query
     * @return $this
     * @throws \Exception
     */
    public function prepend($mixed = null, $value)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, $value, true, 0, false);
        }else{
            static::_set($this->_result, $mixed, $value, true, 0, false);
        }
        return $this;
    }


    /**
     * inject value at position of array found at path. this function is intended to work with arrays but will work also
     * for objects just like Store::set method does. the difference to set() method is that object found at
     * path is an array and index or position does already exist will not overwrite the value but will inject a new item
     * at index/position and move all other items up changing also the numeric keys! the index or position can either be
     * passed as third argument or used directly in path like '/root/array/1'
     *
     * @see Store::set
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param mixed $value expects the value to prepend at path/query
     * @param null|string|int $position expects the optional position or index value
     * @return $this
     * @throws \Exception
     */
    public function inject($mixed = null, $value, $position = null)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, $value, 4, $position, false);
        }else{
            static::_set($this->_result, $mixed, $value, 4, $position, false);
        }
        return $this;
    }


    /**
     * remove any value found at path or path/query. if path/query does not exist throws exception if underlying query
     * class has exceptions enabled as default return behaviour
     *
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @return $this
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function remove($mixed = null)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, '__REMOVE__', false, null, false);
        }else{
            static::_set($this->_result, $mixed, '__REMOVE__', false, null, false);
        }
        return $this;
    }


    /**
     * reset the value to NULL found at path or path/query of first argument. if path/query does not exist throws exception
     * if underlying query class has exceptions enabled as default return behaviour
     *
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @return $this
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function reset($mixed = null)
    {
        if($this->_init)
        {
            static::_set($this->object, $mixed, '__RESET__', false, null, false);
        }else{
            static::_set($this->_result, $mixed, '__RESET__', false, null, false);
        }
        return $this;
    }


    /**
     * copy value found at path or path/query of first argument to value found at path or path/query in second argument.
     * if source path of first argument does not exist will throw exception if underlying query class has exceptions enabled
     * as default return behaviour. if the target path for second argument does not exist will create/extend the path
     *
     * @param string|array $mixed1 expects path as string or path/query as array
     * @param string|array $mixed2 expects path as string or path/query as array
     * @return void
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function copy($mixed1, $mixed2)
    {
        static::_set($this->object, $mixed2, static::_get($this->object, $mixed1), true, null, false);
    }


    /**
     * this function is experimental and should be used only for merging simple objects and array. merge value found at
     * path/query in first argument with value found at path/query in second argument removing anything at query/path of
     * first argument. this function works typesafe - its only possible to merge objects of same data type. the functions
     * works best for merging arrays and one-dimensional objects. keep in mind - this function is experimental
     *
     * @param string|array $mixed1 expects path as string or path/query as array
     * @param string|array $mixed2 expects path as string or path/query as array
     * @throws \Exception
     * @return void
     */
    public function merge($mixed1, $mixed2)
    {
        $_mixed1 = static::_get($this->object, $mixed1);
        $mixed2 =& static::_get($this->object, $mixed2);

        if(jsonkit_type($_mixed1) === jsonkit_type($mixed2))
        {
            if(is_array($mixed2))
            {
                $mixed2 = array_merge((array)$mixed2, (array)$_mixed1);
            }else{
                $mixed2 = (object)array_merge((array)$mixed2, (array)$_mixed1);
            }
            $this->remove($mixed1);
        }else{
            throw new TypeMissmatchException(_("Merging values at paths only allowed for values of same type"));
        }
    }


    /**
     * serialize function. if no value is passed in first argument will return the stores object serialized. if the first
     * argument is a query path indicated by trailing '/' will serialize the value found at path or throw exception or
     * return default value if path does not exist. if the first argument is not a path will return the passed value
     * serialized
     *
     * @param null|string $mixed expects value or path to serialize
     * @return string
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function serialize($mixed = null)
    {
        if($mixed !== null)
        {
            if(is_string($mixed) && substr($mixed, 0, 1) === '/')
            {
                $result =& static::_get($this->object, $mixed, false);
                $result = serialize($result);
                return $result;
            }else{
                return serialize($mixed);
            }
        }else{
            return serialize($this->object);
        }
    }


    /**
     * unserialize function. if value of first argument is a path indicated by trailing '/' will try to unserialize and
     * return value found at path. if nothing is found at path will throw exception or return default value. if value for
     * first argument is not a path will try to unserialize value and return it
     *
     * @param string $mixed expects path or value to unserialize
     * @return mixed
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function &unserialize($mixed)
    {
        if(is_string($mixed) && substr($mixed, 0, 1) === '/')
        {
            return static::_get($this->object, $mixed, true);
        }else{
            if($mixed == serialize(false) || @unserialize($mixed) !== false)
            {
                $mixed = unserialize($mixed);
            }
        }
        return $mixed;
    }


    /**
     * static getter function to retrieve objects at path/query in second argument from object in first argument. the
     * second argument be be either a path or array with extended query arguments used by Query class e.g.:
     * <code>
     *  $store->get($object, '/book/0'); //get first book
     *  $store->get($object, array('/book', array('id=1'); //get book that has the id = 1
     * </code>
     * see Query::find for detailed query arguments/conditions and query language. if nothing is found with
     * path/query will return default return value as defined in static Query class option or throw
     * EmptyResultException. the get function will return the result or the found object as reference if used like:
     * $result =& $store->get('/book/0'); - manipulation of $result also will manipulate the object in store! the third
     * argument is deprecated and will try to unserialize the result if set to boolean true
     *
     * @see Query::find
     * @see Query::retrieve
     * @param object $object expects the object to query
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param bool $unserialize expects boolean value for auto unserialization if used
     * @return mixed
     * @throws \Exception
     * @throws EmptyResultException
     */
    public static function &_get(&$object, $mixed, $unserialize = false)
    {
        $class = get_called_class();
        $return = null;

        if(is_array($mixed) && sizeof($mixed) >= 2)
        {
            if(array_key_exists(2, $mixed))
            {
                $mixed[2] = strtolower(trim((string)$mixed[2]));
            }else{
                $mixed[2] = null;
            }
            $return =& Query::find($object, $mixed[0], (array)$mixed[1], $mixed[2]);
        }else{
            $return =& Query::retrieve($object, $mixed);
        }
        if((bool)$unserialize === true)
        {
            return $return = $class::decode($return);
        }else{
            return $return;
        }
    }


    /**
     * internal object setter function for adding/setting or writting to stores object and also performing all other
     * manipulation commands like remove, unset, etc on object received in first argument. the second argument expects
     * either a path or query argument as manipulation target inside the object - see e.g. Store::_get for
     * path examples and Query for path/query conditions and syntax. the third argument expects the value
     * to set at second argument or a valid manipulation command like:
     * - __REMOVE__ = remove anything that is found at path/query including path itself
     * - __RESET__ = reset to php´s null type value at path/query
     * - __SERIALIZE__ = serialize value found at path/query
     * the fourth argument extend flag defines the extend mode which are as followed:
     * - false = do not extend the object when nothing is found at path/query instead throw exception or return default value
     * - -1 = see false
     * - 0 = see false
     * - 1 = append/prepend and never overwrite value found at path/query
     * - 2 = set or overwrite at path/query regardless of result or if nothing is found
     * - 3 = add/extend only - add only if nothing is found
     * - 4 = inject at index when result is array moving all other object up/down and if not array treat as 3
     * the fifth argument can be used to pass the position where value should be set if object found at path/query is array
     * which can be a numeric value defining position of array with numeric keys (-1 means last) or string which can be
     * legacy values "first" or "last". NOTE: that position only has affect on arrays. the last argument explicitly turns
     * on type safe operation. if anything is found at path/query and value is about to be overwritten will check type
     * of original value against new value and throw exception if types do not match
     *
     * @see Query::find
     * @see Query::retrieve
     * @param object $object expects the object to set values to
     * @param null|string|array $mixed expects either null for connected query call, string for path or array for path/query
     * @param null|mixed $value expects the value to set at second argument
     * @param bool $extend expects optional extend value as explained above
     * @param null|string|int $position expects optional set position as explained in Query::set
     * @param bool $typesafe expects optional typesafe value as explained above
     * @return mixed
     * @throws EmptyResultException
     * @throws \Exception
     */
    protected static function _set(&$object, $mixed = null, $value = null, $extend = null, $position = null, $typesafe = false)
    {
        $key        = null;
        $path       = null;
        $class      = get_called_class();
        $parent     = null;
        $extend     = (int)$extend;
        $typesafe   = (bool)$typesafe;
        $default    = Query::$options[Query::DEFAULT_VALUE];
        $exception  = Query::$options[Query::THROW_EXCEPTION];

        //normalize path
        if(is_array($mixed) && array_key_exists(0, $mixed)){
            $path = '/' . ltrim(rtrim((string)$mixed[0], './* '), ' /');
        }else{
            $path = '/' . ltrim(rtrim((string)$mixed, './* '), ' /');
        }

        //get last part of path as key and shorten path
        if(substr_count($path, '/') >= 2)
        {
            $key = trim(substr($path, strrpos($path, '/') + 1), ' /');
            $path = substr($path, 0, strrpos($path, '/'));
        }else{
            $key = (($path !== '/') ? trim($path, ' /') : null);
            $path = '/';
        }

        //retrieve/find by path
        try
        {

            if(is_array($mixed) && sizeof($mixed) >= 2)
            {
                if(array_key_exists(2, $mixed))
                {
                    $mixed[2] = strtolower(trim((string)$mixed[2]));
                }else{
                    $mixed[2] = 'first';
                }
                $result =& Query::find($object, $path, $mixed[1], $mixed[2], $parent);
            }else if(is_array($mixed)){
                $result =& Query::retrieve($object, $path, false, $parent);
            }else{
                $result =& Query::retrieve($object, $path, false, $parent);
            }
        }
        catch(EmptyResultException $e)
        {
            $result = $default;
        }

        //if result found under base path
        if($result !== $default)
        {
            //reconstruct queried object
            if(is_array($result) && array_key_exists($key, $result))
            {
                $parent =& $result[$key];
            }else if(is_object($result) && isset($result->$key)){
                $parent =& $result->$key;
            }else{
                $parent = null;
            }

            //normalize when doing inject and position is set in path
            if($extend === 4 && (is_int($key) || ctype_digit($key)))
            {
                $position = (int)$key;
                $parent =& $result;
            }

            //return default or throw exception if nothing found for path/query and extend is negative
            if($parent === null && in_array($extend, array(-1, 0, false), true))
            {
                if($exception){
                    throw new EmptyResultException(_("No result found for query/path"));
                }else{
                    return jsonkit_default($default);
                }
            }

            //don´t overwrite result if found at path and extend mode = 3 (add)
            if($parent !== null && $extend === 3)
            {
                return null;
            }

            //remove at path
            if($value === '__REMOVE__')
            {
                static::uset($result, $key);
            //reset at path
            }else if($value === '__RESET__'){
                if(!is_null($parent)) { static::uset($parent, null); } else { $result = null; }
            //serialize at path
            }else if($value === '__SERIALIZE__'){
                if(!is_null($parent)) { $result = $class::encode($parent); }
            //set/inject at path
            }else{
                //path/key found in object
                if(!is_null($parent))
                {
                    if(is_array($parent) && $position !== null)
                    {
                        //set = overwrite at index
                        if($extend === 2)
                        {
                            if($position === -1 || $position === 'last')
                            {
                                $parent[sizeof($parent) - 1] = $value;
                            }else if($position === 0 || $position === 'first'){
                                $parent[0] = $value;
                            }else{
                                $parent[$position] = $value;
                            }
                        //inject = append/prepend/inject at index
                        }else{
                            if($position === -1 || $position === 'last')
                            {
                                array_push($parent, $value);
                            }else if($position === 0 || $position === 'first'){
                                array_unshift($parent, $value);
                            }else if(is_int($position) || ctype_digit($position)){
                                if((int)$position < sizeof($parent))
                                {
                                    array_splice($parent, (int)$position, 0, array($value));
                                }else{
                                    array_push($parent, $value);
                                }
                            }
                        }
                    }else{
                        if($typesafe && jsonkit_type($parent) !== jsonkit_type($value))
                        {
                            throw new InvalidOverrideException(_("Value found at path can not be overwritten due to typesafe protection"), 1702101);
                        }
                        $parent = $value;
                    }
                //path/key not found so adding new key to object
                }else{
                    if(is_null($key))
                    {
                        $result = $value;
                    }else{
                        if(is_array($result))
                        {
                            $result[((is_int($key) || ctype_digit($key)) ? (int)$key : $key)] = $value;
                        }else{
                            $result->$key = $value;
                        }
                    }
                }
            }
        }else{
            if(in_array($extend, array(true, 1, 2, 3, 4), true))
            {
                static::extend($object, $mixed, $value);
            }else{
                if($exception){
                    throw new EmptyResultException(_("No result found for query/path"));
                }else{
                    return jsonkit_default($default);
                }
            }
        }
        return $object;
    }


    /**
     * extend object (array|object) passed in first argument by path and value passed in second and third argument. extend
     * will create new child elements from path parts according to the paths depth just like jsonkit_array_set() function
     * does. where jsonkit_array_set() only works for array this function works with array and object and mixed structures.
     * the main difference between set/add method is that extend method will never throw exceptions but always create the
     * necessary child items for path regardless of current object structure. use this function standalone with any object
     * and value. the object in first argument is passed as reference but also extended object will be returned by this
     * function
     *
     * @param object|array $object expects object or array to extend
     * @param string $path expects either a path indicated by '/' or a key name for value
     * @param null|mixed $value expects the value to set at path
     * @return mixed
     */
    public static function extend(&$object, $path, $value = null)
    {
        if(is_array($object) || is_object($object))
        {
            if(strpos($path, '/') === false)
            {
                return (is_array($object)) ? $object[$path] = $value : $object->$path = $value;
            }
            $keys = explode('/', trim($path, '/.* '));
            $j = 0;
            for($i = 0; $i < sizeof($keys) - 1; $i++)
            {
                $key = (is_int($keys[$i]) || ctype_digit($keys[$i])) ? (int)$keys[$i] : (string)$keys[$i];
                $prev = (array_key_exists(($j - 1), $keys)) ? $keys[($j - 1)] : null;
                $next = (array_key_exists(($j + 1), $keys)) ? $keys[($j + 1)] : null;

                if(is_array($object))
                {
                    if(!array_key_exists($key, $object) || (!is_object($object[$key]) && !is_array($object[$key])))
                    {
                        $object[$key] = (is_int($next) || ctype_digit($next)) ? array() : new \stdClass();
                    }
                    $object =& $object[$key];
                }else{
                    if(!isset($object->{$key}) || (!is_object($object->{$key}) && !is_array($object->{$key})))
                    {
                        $object->{$key} = (is_int($next) || ctype_digit($next)) ? array() : new \stdClass();
                    }
                    $object =& $object->{$key};
                }
                $j++;
            }
            $last = $keys[sizeof($keys) -1 ];
            if(is_array($object))
            {
                $object[((is_int($last) || ctype_digit($last)) ? (int)$last : (string)$last)] = $value;
            }else{
                $object->{(string)$last} = $value;
            }
        }
        return $object;
    }


    /**
     * internal function to unset object or object keys where first argument can be array or object and second argument
     * can be a key to unset object at key´s position
     *
     * @param mixed $object expects object to unset
     * @param null|string $key expects the key to unset
     * @return void
     */
    protected static function uset(&$object, $key = null)
    {
        if(is_null($key))
        {
            $object = null;
        }else if(is_object($object) && isset($object->{$key})){
            unset($object->{$key});
        }else if(is_array($object)){
            if(array_key_exists($key, $object)){
                unset($object[$key]);
            }else if(array_key_exists((int)$key, $object)){
                unset($object[(int)$key]);
            }
        }
    }


    /**
     * decode value passed in first argument with algorithm passed in second argument which when empty will default to
     * trying to return value unserialized if value is serialized. see class constants for available algorithm
     *
     * @param mixed $value expects the value to decode
     * @param null|string $algo expects optional algorithm
     * @return mixed
     * @throws \Exception
     */
    public static function decode($value, $algo = 'JSON')
    {
        $class = get_called_class();

        if($algo !== null)
        {
            switch(strtoupper(trim((string)$algo)))
            {
                case self::BASE64:
                    $value = base64_decode($value);
                    if(static::serialized($value))
                    {
                        $value = $class::decode($value);
                    }
                    return $value;
                case self::JSON:
                    return json_decode($value);
                case self::ROT13:
                    $value = str_rot13($value);
                    if(static::serialized($value))
                    {
                        $value = $class::decode($value);
                    }
                    return $value;
                case self::UU:
                    $value = convert_uudecode($value);
                    if(static::serialized($value))
                    {
                        $value = $class::decode($value);
                    }
                    return $value;
                default:
                    throw new UnsupportedAlgoException(sprintf(_("Decode algorithm: %s is not supported"), $algo));
            }
        }else{
            if(is_string($value) && static::serialized($value))
            {
                return unserialize($value);
            }else{
                return $value;
            }
        }
    }


    /**
     * encode value passed in first argument with algorithm passed in second argument which when empty will default to
     * trying to return value serialized. if algo will expect strings only will serialize value first if value in first
     * argument is not a string. see class constants for available algorithm
     *
     * @param mixed $value expects value to encode
     * @param null|string $algo expects optional algorithm
     * @return string
     * @throws \Exception
     */
    public static function encode($value, $algo = 'JSON')
    {
        if($algo !== null)
        {
            switch(strtoupper(trim((string)$algo)))
            {
                case self::BASE64:
                    if(!is_string($value))
                    {
                        $value = serialize($value);
                    }
                    return base64_encode($value);
                case self::JSON:
                    return json_encode($value);
                case self::ROT13:
                    if(!is_string($value))
                    {
                        $value = serialize($value);
                    }
                    return str_rot13($value);
                case self::UU:
                    if(!is_string($value))
                    {
                        $value = serialize($value);
                    }
                    return convert_uuencode($value);
                default:
                    throw new UnsupportedAlgoException(sprintf(_("Encode algorithm: %s is not supported"), $algo));
            }
        }else{
            if($value !== false)
            {
                return serialize($value);
            }else{
                return $value;
            }
        }
    }


    /**
     * encrypt object passed in first argument using key, cipher, algo and mode passed as optional arguments. the second
     * argument key is the encryption password which is expected to be a string or integer value. the third argument can
     * be used to define a default return value when !== null will return the default value instead of throwing exception
     * thrown from Store::crypt method. use default value, e.g. false is recommended, if encryption is used
     * outside if json store context. the cipher in fourth argument must be a php´s mycrypt valid value that usually comes
     * from using php MCRYPT_ constants. the fifth parameter algorithm must be a valid string value that passes php´s
     * hash_algos() function. the sixth argument mode defines the cipher mode which must be valid against php´s
     * mcrypt_list_modes(). see Store::crypt for more info since all logic is handled in this method
     *
     * @see Store::crypt
     * @param mixed $object expects object to encrypt
     * @param mixed $key expects the key/password
     * @param mixed $default expects default return value which is not null
     * @param string $cipher expects the encryption cipher
     * @param string $algo expects the encryption algo
     * @param string $mode expects the encryption cipher mode
     * @return string
     * @throws \Exception
     */
    public static function encrypt($object, $key, $default = null, $cipher = null, $algo = null, $mode = null)
    {
        try
        {
            return static::crypt($object, $key, $cipher, $algo, $mode, true);
        }
        catch(\Exception $e)
        {
            if(!is_null($default))
            {
                return $default;
            }else{
                throw $e;
            }
        }
    }


    /**
     * decrypt object passed in first argument using key, cipher, algo and mode mode passed in other arguments. see
     * Store::encrypt for more info regarding expected values and Store::crypt for explanation
     * and expected results
     *
     * @see Store::crypt
     * @see Store::encrypt
     * @param mixed $object expects object to encrypt
     * @param mixed $key expects the key/password
     * @param mixed $default expects default return value which is not null
     * @param string $cipher expects the encryption cipher
     * @param string $algo expects the encryption algo
     * @param string $mode expects the encryption cipher mode
     * @return mixed
     * @throws \Exception
     */
    public static function decrypt($object, $key, $default = null, $cipher = null, $algo = null, $mode = null)
    {
        try
        {
            return static::crypt($object, $key, $cipher, $algo, $mode, false);
        }
        catch(\Exception $e)
        {
            if(!is_null($default))
            {
                return $default;
            }else{
                throw $e;
            }
        }
    }


    /**
     * internal en/decrypt function. see Store::encrypt for explanation of expected values. the last argument
     * is for internal use only and determines if calling this method will encrypt or decrypt the object passed in first
     * argument. if any of the passed arguments are not valid will throw exception. also will throw exception if encryption
     * or decryption methods fail or key is not valid or wrong. NOTE that any value passed to encryption will be serialized
     * before the actual encryption
     *
     * @see Store::encrypt
     * @param mixed $object expects object to encrypt
     * @param mixed $key expects the key/password
     * @param string $cipher expects the encryption cipher
     * @param string $algo expects the encryption algo
     * @param string $mode expects the encryption cipher mode
     * @param bool $encrypt expected boolean value to determine whether to encrypt or decrypt object
     * @return mixed
     * @throws \Exception
     */
    protected static function crypt($object, $key, $cipher = null, $algo = null, $mode = null, $encrypt = true)
    {
        if(function_exists('mcrypt_encrypt'))
        {
            if($cipher === null)
            {
                $cipher = MCRYPT_RIJNDAEL_256;
            }else{
                $cipher = strtolower(trim((string)$cipher));
            }
            if($algo === null)
            {
                $algo = 'md5';
            }else{
                $algo = strtolower(trim((string)$algo));
            }
            if($mode === null)
            {
                $mode = MCRYPT_MODE_CBC;
            }else{
                $mode = strtolower(trim((string)$mode));
            }
            if(mcrypt_get_cipher_name($cipher) === false)
            {
                throw new UnsupportedAlgoException(sprintf(_("Cipher: %s is not supported"), $cipher));
            }
            if(!in_array($algo, hash_algos()))
            {
                throw new UnsupportedAlgoException(sprintf(_("Algo: %s is not supported"), $algo));
            }
            if(!in_array($mode, mcrypt_list_modes()))
            {
                throw new UnsupportedAlgoException(sprintf(_("Mode: %s is not supported"), $mode));
            }
            if((bool)$encrypt)
            {
                $object = mcrypt_encrypt($cipher, hash($algo, $key, false), serialize($object), $mode, hash($algo, hash($algo, $key, false), false));
                if($object !== false)
                {
                    return base64_encode($object);
                }else{
                    throw new \HttpInvalidParamException(_("Unable to encrypt object due to invalid parameters"));
                }
            }else{
                $object = mcrypt_decrypt($cipher, hash($algo, $key, false), base64_decode($object), $mode, hash($algo, hash($algo, $key, false), false));
                if($object !== false)
                {
                    $object = rtrim($object, "\0");
                    if(static::serialized($object))
                    {
                        return unserialize($object);
                    }else{
                        throw new EncryptionException(_("Unable to decrypt object with key"));
                    }
                }else{
                    throw new EncryptionException(_("Unable to decrypt object due to invalid parameters"));
                }
            }
        }else{
            if((bool)$encrypt)
            {
                return base64_encode(serialize($object));
            }else{
                return unserialize(base64_decode($object));
            }
        }
    }


    /**
     * function to test if a value is serialized with php´s serialize() function or not returning boolean value
     *
     * @param mixed $value expects the value to test
     * @return bool
     */
    public static function serialized($value)
    {
        return ($value == serialize(false) || @unserialize($value) !== false);
    }


    /**
     * dump/print stores object to screen
     *
     * @return void
     */
    public function dump()
    {
        Json::dump($this->_object);
    }


    /**
     * save object in store. if file is set from passing file pointer in constructor will save serialized (encrypted) object
     * to file location and return boolean true on success and throw exception on failure. if no file has been specified
     * will return the object encoded and/or encrypted as string. if first argument key is not set will save or return the
     * object serialized. if first argument is set will save or return the object encrypted and key/password protected
     *
     * @param null|mixed $key expects key/password when using encryption
     * @return bool|string
     * @throws \Exception
     */
    public function save($key = null)
    {
        $class = get_called_class();

        if($this->_file !== null)
        {
            return $this->export($this->_file, $key);
        }else{
            if($key !== null)
            {
                return $this->export(null, $key);
            }else{
                return $class::encode($this->object);
            }
        }
    }


    /**
     * shortcut and legacy function that is identical to Store::export. this method is considered to be
     * deprecated
     *
     * @see Store::export
     * @deprecated since 1.0
     * @param null|mixed $mixed expects the save to target
     * @param null|mixed $key expects key/password when using encryption
     * @return bool|mixed
     * @throws \Exception
     */
    public function saveTo($mixed = null, $key = null)
    {
        return $this->export($mixed, $key);
    }


    /**
     * save object in store to target specified in first argument. if the stores object is of type object or array will
     * serialise object before saving. if the second argument key is set will encrypt the object before saving either by
     * internal encryption method or encryption callback if set as class option. the first can be of the following:
     * - null which will return object only
     * - resource which will save to passed resource handle if resource type is supported
     * - callback will pass storable object to callback passed as target
     * - string file location to store object to file
     * anything else or unsupported resource types will throw exception. if save action is successful will return boolean
     * true. only if first argument is null will return saveable object directly
     *
     * @param null|mixed $mixed expects the save to target
     * @param null|mixed $key expects key/password when using encryption
     * @return bool|mixed
     * @throws \Exception
     */
    public function export($mixed, $key = null)
    {
        $class = get_called_class();

        if($key === null && $this->_key !== null)
        {
            $key = $this->_key;
        }
        if($key !== null)
        {
            if(jsonkit_has_option(self::ENCRYPTION_CALLBACK, $this))
            {
                $object = call_user_func_array(jsonkit_get_option(self::ENCRYPTION_CALLBACK, $this), array($this->object, $key, 'encrypt'));
            }else{
                $object = static::encrypt($this->object, $key, null, jsonkit_get_option(self::ENCRYPTION_CIPHER, $this), jsonkit_get_option(self::ENCRYPTION_ALGO, $this), jsonkit_get_option(self::ENCRYPTION_MODE, $this));
            }
        }else{
            $object = (is_array($this->object) || is_object($this->object)) ? $class::encode($this->object) : $this->object;
        }
        if(is_null($mixed)){
            return $object;
        }else if(is_resource($mixed)){
            switch(get_resource_type($mixed))
            {
                case 'stream':
                    if(!fwrite($mixed, $object))
                    {
                        throw new InvalidFileException(_("Unable to save to stream"));
                    }
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf(_("Resource type: %s not supported"), get_resource_type($mixed)));
            }
        }else if(is_callable($mixed)){
            call_user_func_array($mixed, array($object, $key, $this));
        }else{
            if(!file_put_contents((string)$mixed, $object, LOCK_EX))
            {
                throw new InvalidFileException(sprintf(_("Unable to save to file: %s"), (string)$mixed));
            }
        }
        unset($object);
        return true;
    }


    /**
     * import object from source which can be of the following type:
     * - object assigns object to store
     * - array assigns and converts array to object to store
     * - existing file will open and decode object stored in file and store file location to export again
     * - serialized string representation of object
     * - not existing file location will not import anything but only set file location for exports
     * anything else will throw exception. if the second argument key is set will try to decrypt the object with key/password
     * either with internal decryption method ot decryption callback if set as class option. will return the imported
     * object that is stored in class
     *
     * @param mixed $mixed expects import source
     * @param null|mixed $key expects key/password when using decryption
     * @return mixed
     * @throws \Exception
     */
    public function import($mixed, $key = null)
    {
        $class = get_called_class();

        if(Json::isJson($mixed))
        {
            $mixed = static::decode($mixed, 'JSON');
        }
        if($key === null && $this->_key !== null)
        {
            $key = $this->_key;
        }
        if(is_object($mixed))
        {
            $this->object =& $mixed;
        }else if(is_array($mixed)){
            if(array_keys($mixed) === range(0, count($mixed) - 1))
            {
                $this->object =& $mixed;
            }else{
                $this->object = (object)$mixed;
            }
        }else{
            if(is_file($mixed))
            {
                $this->_file = $mixed;
                if(($mixed = file_get_contents($mixed)) !== false)
                {
                    $this->object = $class::decode($mixed);
                }else{
                    throw new InvalidFileException(sprintf(_("Unable to import from file: %s"), $mixed));
                }
            }else{
                if(is_string($mixed) && static::serialized($mixed))
                {
                    $this->object = $class::decode($mixed);
                }else if(is_string($mixed) && strpos($mixed, '.') !== false && is_writeable(dirname($mixed))){
                    $this->_file = $mixed;
                }else{
                    throw new \InvalidArgumentException(_("Import parameter is not a valid object, serialized object or file path/dir"));
                }
            }
        }
        if($key !== null)
        {
            if(jsonkit_has_option(self::ENCRYPTION_CALLBACK, $this))
            {
                $this->object = call_user_func_array(jsonkit_get_option(self::ENCRYPTION_CALLBACK, $this), array($this->object, $key, 'decrypt'));
            }else{
                $this->object = static::decrypt($this->object, $key, null, jsonkit_get_option(self::ENCRYPTION_CIPHER, $this), jsonkit_get_option(self::ENCRYPTION_ALGO, $this), jsonkit_get_option(self::ENCRYPTION_MODE, $this));
            }
        }
        return $this->object;
    }


    /**
     * set path to work with Store::exec. see Store::exec for more explanation
     *
     * @see Store::exec
     * @param string $path expects path element one for each depth
     * @param null|int $index expects optional index if path element is array
     * @return $this
     */
    public function path($path, $index = null)
    {
        if($index !== null)
        {
            array_push($this->_path, array(trim((string)$path), (int)$index));
        }else{
            array_push($this->_path, array(trim((string)$path), null));
        }
        return $this;
    }


    /**
     * magically set/get values in conjunction with using either magic method __call overloading instance with method names
     * not found which will be translated to object path, e.g.
     * <code>
     *  $store->store()->book()->exec(); // get childs at /store/book
     *  $store->store()->book(0)->exec(); // get childs at /store/book/0
     *  $store->store()->book(0)->exec(null); //set value at /store/book/0
     * </code>
     * the same will also work when using the path function like:
     * <code>
     *  $store->path('store')->path('book')->exec(); // get childs at /store/book
     *  $store->path('store')->path('book', 0)->exec(); // get childs at /store/book/0
     *  $store->path('store')->path('book', 0)->exec(null); //set value at /store/book/0
     * </code>
     * the method expects that at least one path element exists. internally set/get methods are used so if path does not
     * exists may return default return value or throw EmptyResultException accoring to  Query static class
     * options
     *
     * @param string $value
     * @return mixed
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function &exec($value = '__NIL__')
    {
        $tmp = array();
        $return = null;

        if(!empty($this->_path))
        {
            foreach($this->_path as $p)
            {
                $tmp[] = $p[0];
                if(array_key_exists(1, $p) && !is_null($p[1]))
                {
                    $tmp[] = (int)$p[1];
                }
            }
            if($value !== '__NIL__')
            {
                $this->set(implode('/', $tmp), $value);
            }else{
                $return =& $this->get(implode('/', $tmp));
            }
        }
        $this->_path = array();
        return $return;
    }


    /**
     * magic method __call overloading class only in conjunction with Store::exec. see Store::exec
     * for more explanation
     *
     * @see Store::exec
     * @param string $name expects the path element
     * @param array $args expects optional argument which is expected to by array index if path element is array
     * @return $this
     */
    public function __call($name, Array $args)
    {
        if(!empty($args))
        {
            array_push($this->_path, array($name, (int)$args[0]));
        }else{
            array_push($this->_path, array($name, null));
        }
        return $this;
    }


    /**
     * overloading class by setting properties which do not exist will result in setting value in second parameter at path
     * or name set in first argument. this also works as connected overloading e.g.
     * <code>
     *  $store->store = null;
     *  $store->store->name = 'store'
     *  $store->store->books[0] = null //will only work if books exists and is array!
     * </code>
     * all above examples work thereby enabling dynamic setting. NOTE: that overloading will only work for object key =>
     * value pairs and arrays which are set (see last code example). this function may fail due to its experimental character
     *
     * @param string $name expects the key name for value
     * @param mixed $value expects the value to set for key name
     * @return void
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        $this->set("/$name", $value);
    }


    /**
     * overloading class by getting property which does not exist will use property name as path and try to get value at
     * path using internal get method. if anything is found at first argument/path will return object/value if not will
     * either throw exception or return default return value according to Query static class options
     *
     * @param string $name expects property/path to get
     * @return mixed
     * @throws EmptyResultException
     * @throws \Exception
     */
    public function &__get($name)
    {
        return $this->get("/$name");
    }


    /**
     * overloading class by using isset() or empty() on non existing class property will use class property as path and
     * check for existing of any value at path that is not default return value from Query class
     *
     * @param string $name expects property/path to get
     * @return bool
     * @throws \Exception
     */
    public function __isset($name)
    {
        try
        {
            if($this->get("/$name") !== Query::$options[Query::DEFAULT_VALUE])
            {
                return true;
            }
        }
        catch(EmptyResultException $e){}
        return false;
    }


    /**
     * class to string conversion returns encoded/serialized object
     *
     * @return string
     */
    public function __toString()
    {
        $class  = get_called_class();

        return $class::encode($this->object);
    }


    /**
     * when cloning object reset instance properties using wakeup method
     *
     * @see Store::__wakeup
     * @return void
     */
    public function __clone()
    {
        $this->object = clone $this->object;
        $this->__wakeup();
    }


    /**
     * if instance is serialized save only the following variables and discard the rest
     *
     * @return array
     */
    public function __sleep()
    {
        return array('object', 'options', '_file');
    }


    /**
     * if instance is deserialized reset the following variables
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->_key = null;
        $this->_init = true;
        $this->_path = array();
        $this->_result = null;
    }


    /**
     * on class constructor clear cache
     */
    public function __destruct()
    {
        @clearstatcache();
    }
}