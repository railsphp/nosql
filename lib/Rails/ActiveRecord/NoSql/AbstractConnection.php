<?php
namespace Rails\ActiveRecord\NoSql;

abstract class AbstractConnection
{
    protected $resource;
    
    abstract public function __construct(array $config = []);
    
    abstract public function insert($tableName, array $attributes, array $options = []);
    
    abstract public function select(Query\Select $query);
    
    abstract public function update();
    
    abstract public function delete();
    
    public function resource()
    {
        return $this->resource;
    }
    
    public function selectDb($dbName)
    {
        return true;
    }
    
    public function createDb($dbName)
    {
        return true;
    }
}
