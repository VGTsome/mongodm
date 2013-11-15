<?php

namespace Purekid\Mongodm;

use Purekid\Mongodm\Model;

/**
 * Mongodm - A PHP Mongodb ORM
 *
 * @package  Mongodm
 * @author   Michael Gan <gc1108960@gmail.com>
 * @link     http://github.com/purekid
 */
class Collection  implements \IteratorAggregate,\ArrayAccess, \Countable
{

    private $_items = array();
    private $_items_id = array();

    /**
     * Make a collection from a arrry of Model
     *
     * @param  array $models
     */
    public function __construct($models = array())
    {

        $items = array();

        $i = 0;
        foreach($models as $model){
            if(! ($model instanceof Model)) continue;
            if($model->exists){
                $id = (string) $model->getId();
            }else if($model->getIsEmbed()){
                $id = $i++;
                $model->setTempId($id);
            }

            $items[$id] = $model;
        }

        $this->_items = $items;

    }

    /**
     * Add a model item or model array or ModelSet to this set
     *
     * @param  mixed $items
     * @return $this
     */
    public function add($items)
    {
        if($items && $items instanceof \Purekid\Mongodm\Model){
            $id = (string) $items->getId();
            $this->_items[$id] = $items;
        }else if(is_array($items)){
            foreach($items as $obj){
                if($obj instanceof \Purekid\Mongodm\Model){
                    $this->add($obj);
                }
            }
        }else if($items instanceof self){
            $this->add($items->toArray());
        }
        return $this;

    }

    /**
     * Get item by numeric index or MongoId
     *
     * @param  array $models
     * @return \Purekid\Mongodm\Model
     */
    public function get($index = 0 )
    {

        if(is_int($index)){
            if($index + 1 > $this->count()){
                return null;
            }else{
                return	current(array_slice ($this->_items , $index , 1)) ;
            }
        }else{

            if($index instanceof \MongoId){
                $index = (string) $index;
            }

            if($this->has($index)){
                return $this->_items[$index];
            }
        }

        return null;
    }

    /**
     * Remove a record from the collection
     *
     * @param int|MongoID|Model
     * @return boolean
     */
    public function remove($param)
    {
        if($param instanceof Model ){
            $param = $param->getId();
        }

        $item = $this->get($param);
        if($item){
            $id = (string) $item->getId();
            if($this->_items[$id]){
                unset($this->_items[$id]);
            }
        }
        return true;

    }

    /**
     * Slice the underlying collection array.
     *
     * @param  int   $offset
     * @param  int   $length
     * @param  bool  $preserveKeys
     * @return \Purekid\Mongodm\Collection
     */
    public function slice($offset, $length = null, $preserveKeys = false)
    {
        return new static(array_slice($this->_items, $offset, $length, $preserveKeys));
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param  int  $limit
     * @return \Purekid\Mongodm\Collection
     */
    public function take($limit = null)
    {
        if ($limit < 0) return $this->slice($limit, abs($limit));

        return $this->slice(0, $limit);
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->_items);
    }

    /**
     * Determine if a record exists in the collection
     *
     * @param int|MongoID|object
     * @return boolean
     */
    public function has( $param )
    {

        if($param instanceof \MongoId){
            $id = (string) $param;
        }else if($param instanceof Model){
            $id = (string) $param->getId() ;
        }else if(is_string($param)){
            $id = $param;
        }
        if( isset($id) && isset($this->_items[$id]) ){
            return true;
        }
        return false;

    }

    /**
     * Run a map over the collection using the given Closure and return a new collection
     * @param Closure $callback
     * @return \Purekid\Mongodm\Collection
     */
    public function map(\Closure $callback)
    {
        return new static(array_map($callback, $this->_items));
    }

    /**
     * Filter the collection using the given Closure and return a new collection
     * @param Closure $callback
     * @return \Purekid\Mongodm\Collection
     */
    public function filter(\Closure $callback)
    {
        return new static(array_filter($this->_items, $callback));
    }


    /**
     * Sort the collection using the given Closure
     * @param Closure $callback
     * @param boolean $asc
     * @return \Purekid\Mongodm\Collection
     */
    public function sortBy(\Closure $callback , $asc = false)
    {
        $results = array();

        foreach ($this->_items as $key => $value)
        {
            $results[$key] = $callback($value);
        }

        if($asc){
            asort($results);
        }else{
            arsort($results);
        }

        foreach (array_keys($results) as $key)
        {
            $results[$key] = $this->_items[$key];
        }

        $this->_items = $results;

        return $this;
    }

    /**
     * Reverse items order.
     *
     * @return \Purekid\Mongodm\Collection
     */
    public function reverse()
    {

        $this->_items =  array_reverse($this->_items);
        return $this;

    }


    /**
     * Make a collection from a arrry of Model
     *
     * @param  array $models
     * @return \Purekid\Mongodm\Collection
     */
    static function make($models)
    {

        return new self($models);

    }

    public function first()
    {
        return current($this->_items);
    }

    public function last()
    {
        return array_pop($this->_items);
    }

    /**
     * Execute a callback over each item.
     *
     * @param  Closure  $callback
     * @return \Purekid\Mongodm\Collection
     */
    public function each(\Closure $callback)
    {
        array_map($callback, $this->_items);

        return $this;
    }

    public function count()
    {
        return count($this->_items);
    }

    /**
     * Export all items to a Array
     * @param boolean $is_numeric_index
     * @return array
     */
    public function toArray( $is_numeric_index = true ,$itemToArray = false)
    {

        $array = array();
        foreach($this->_items as $item){
            if(!$is_numeric_index){
                $id = (string) $item->getId();
                if($itemToArray){
                    $item = $item->toArray();
                }
                $array[$id] = $item;
            }else{
                if($itemToArray){
                    $item = $item->toArray();
                }
                $array[] = $item;
            }
        }
        return $array;

    }

    /**
     * Export all items to a Array with embed style ( without _type,_id)
     * @return array
     */
    public function toEmbedsArray()
    {

        $array = array();
        foreach($this->_items as $item){
            $item = $item->toArray(array('_type','_id'));
            $array[] = $item;
        }
        return $array;

    }


    public function getIterator()
    {
        return new \ArrayIterator($this->_items);
    }

    /**
     * make a  MongoRefs array of items
     *
     * @param  mixed $items
     * @return $this
     */
    public function makeRef()
    {

        $data = array();
        foreach($this->_items as $item){
            $data[] = $item->makeRef();
        }
        return $data;

    }

    public function offsetExists($key)
    {
        if(is_integer($key) && $key + 1 <= $this->count()){
            return true;
        }
        return $this->has($key);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('cannot change the set by using []');
    }

    public function offsetUnset($index)
    {
        $this->remove($index);
    }

}