<?php
namespace Rails\ActiveRecord\NoSql\Mongo;

use Rails\ActiveRecord\NoSql\Query;

class Connection extends \Rails\ActiveRecord\NoSql\AbstractConnection
{
    protected $database;
    
    protected $query;
    
    public function __construct(array $config = [])
    {
        if (!isset($config['server'])) {
            $config['server'] = null;
        }
        if (!isset($config['options'])) {
            $config['options'] = [];
        }
        $this->resource = new \MongoClient($config['server'], $config['options']);
    }
    
    public function selectDb($dbName)
    {
        $this->database = $this->resource->selectDb($dbName);
    }
    
    public function select(Query\Select $query)
    {
        $this->query = $query->getWhere();
        
        foreach ($query->getWhereNot() as $key => $value) {
            $this->addToQuery($key, ['$ne' => $value]);
        }
        foreach ($query->getNot() as $key => $value) {
            $this->addToQuery($key, ['$not' => $value]);
        }
        foreach ($query->getGreaterThan() as $key => $value) {
            $this->addToQuery($key, ['$gt' => $value]);
        }
        foreach ($query->getLowerThan() as $key => $value) {
            $this->addToQuery($key, ['$lt' => $value]);
        }
        foreach ($query->getEqualOrGreaterThan() as $key => $value) {
            $this->addToQuery($key, ['$gte' => $value]);
        }
        foreach ($query->getEqualOrLowerThan() as $key => $value) {
            $this->addToQuery($key, ['$lte' => $value]);
        }
        foreach ($query->getBetween() as $key => $value) {
            $this->addToQuery($key, ['$gte' => $value[0], '$lte' => $value[1]]);
        }
        foreach ($query->getLike() as $key => $value) {
            $this->query[$key] = new \MongoRegex($value);
        }
        foreach ($query->getAlike() as $key => $value) {
            $this->query[$key] = ['$not' => new \MongoRegex($value)];
        }
        
        $collection = new \MongoCollection($this->database, $query->getFrom());
        $cursor     = $collection->find($this->query);
        
        foreach (array_reverse($query->getOrder()) as $order) {
            $cursor->sort($order);
        }
        
        if ($query->getPage() && $query->getLimit()) {
            if ($skip = ($query->getPage() - 1) * $query->getLimit()) {
                $cursor->skip($skip);
            }
        } elseif ($query->getOffset()) {
            $cursor->skip($query->getOffset());
        }
        if ($query->getLimit()) {
            $cursor->limit($query->getLimit());
        }
        
        $this->query = null;
        
        return iterator_to_array($cursor);
    }
    
    public function insert($tableName, array $attributes, array $options = [])
    {
        $collection = $this->database->selectCollection($tableName);
        return $collection->insert($attributes, $options);
    }
    
    public function update()
    {
    }
    
    public function delete()
    {
    }
    
    protected function addToQuery($key, array $params)
    {
        if (!isset($this->query[$key])) {
            $this->query[$key] = [];
        }
        $this->query[$key] = array_merge($this->query[$key], $params);
    }
}
