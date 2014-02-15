<?php
namespace Rails\ActiveRecord\NoSql\Mongo;

use Rails\ActiveRecord\NoSql\Query;

class Connection extends \Rails\ActiveRecord\NoSql\AbstractConnection
{
    protected $database;
    
    public function __construct(array $config = [])
    {
        if (!isset($config['server'])) {
            $config['server'] = null;
        }
        if (!isset($config['options'])) {
            $config['options'] = [];
        }
        $this->resource = new \MongoClient($config['server'], $config['options']);
        
        if (isset($config['dbname'])) {
            $this->selectDb($config['dbname']);
        }
    }
    
    public function selectDb($dbName)
    {
        $this->database = $this->resource->selectDb($dbName);
        $this->dbName   = $dbName;
    }
    
    public function database()
    {
        return $this->database;
    }
    
    public function dbName()
    {
        return $this->dbName;
    }
    
    public function select($tableName, array $criteria, array $queryOptions = [], array $options = [])
    {
        if (!isset($options['fields'])) {
            $options['fields'] = [];
        }
        
        $collection = $this->database->selectCollection($tableName);
        $cursor     = $collection->find($criteria, $options['fields']);
        
        foreach (array_reverse($queryOptions['order']) as $order) {
            $cursor->sort($order);
        }
        
        if ($queryOptions['page'] && $queryOptions['limit']) {
            if ($skip = ($queryOptions['page'] - 1) * $queryOptions['limit']) {
                $cursor->skip($skip);
            }
        } elseif ($queryOptions['offset']) {
            $cursor->skip($queryOptions['offset']);
        }
        if ($queryOptions['limit']) {
            $cursor->limit($queryOptions['limit']);
        }
        
        return iterator_to_array($cursor);
    }
    
    public function insert($tableName, array $attributes, array $options = [])
    {
        $collection = $this->database->selectCollection($tableName);
        # TODO: Handle 'w' option if present.
        return $collection->insert($attributes, $options);
    }
    
    public function update($tableName, array $criteria, array $data, array $options = [])
    {
        $collection = $this->database->selectCollection($tableName);
        # TODO: Handle 'w' option if present.
        return $collection->save($data, $options);
    }
    
    public function delete($tableName, array $criteria, array $options = [])
    {
        $collection = $this->database->selectCollection($tableName);
        # TODO: Handle 'w' option if present.
        return $collection->remove($criteria, $options);
    }
    
    public function queryToArray(Query\Select $query)
    {
        $parsed = $query->getWhere();
        
        /**
         * Automatically convert "id" to "_id" in
         * queries like `$query->where(['id' => $id])`.
         */
        if (isset($parsed['id'])) {
            $parsed['_id'] = (int)$parsed['id'];
            unset($parsed['id']);
        }
        
        foreach ($query->getWhereNot() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$ne' => $value]);
        }
        foreach ($query->getNot() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$not' => $value]);
        }
        foreach ($query->getGreaterThan() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$gt' => $value]);
        }
        foreach ($query->getLowerThan() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$lt' => $value]);
        }
        foreach ($query->getEqualOrGreaterThan() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$gte' => $value]);
        }
        foreach ($query->getEqualOrLowerThan() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$lte' => $value]);
        }
        foreach ($query->getBetween() as $key => $value) {
            $this->addToQuery($parsed, $key, ['$gte' => $value[0], '$lte' => $value[1]]);
        }
        foreach ($query->getLike() as $key => $value) {
            $parsed[$key] = new \MongoRegex($value);
        }
        foreach ($query->getAlike() as $key => $value) {
            $parsed[$key] = ['$not' => new \MongoRegex($value)];
        }
        
        return $parsed;
    }
    
    protected function addToQuery(&$query, $key, array $params)
    {
        if (!isset($query[$key])) {
            $query[$key] = [];
        }
        $query[$key] = array_merge($query[$key], $params);
    }
}
