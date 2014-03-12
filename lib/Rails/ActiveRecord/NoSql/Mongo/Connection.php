<?php
namespace Rails\ActiveRecord\NoSql\Mongo;

use Rails\ActiveRecord\NoSql\Query;

class Connection extends \Rails\ActiveRecord\NoSql\AbstractConnection
{
    protected $database;
    
    protected $modelClass;
    
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
    
    public function setModelClass($modelClass)
    {
        $this->modelClass = $modelClass;
        return $this;
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
        
        $cursor->sort($queryOptions['order']);
        
        $totalRecords = $cursor->count();
        $page    = null;
        $perPage = null;
        
        if ($queryOptions['page'] && $queryOptions['limit']) {
            $page    = $queryOptions['page'];
            $perPage = $queryOptions['limit'];
            if ($skip = ($queryOptions['page'] - 1) * $queryOptions['limit']) {
                $cursor->skip($skip);
            }
        } elseif ($queryOptions['offset']) {
            $cursor->skip($queryOptions['offset']);
        }
        if ($queryOptions['limit']) {
            $cursor->limit($queryOptions['limit']);
        }
        
        return [
            iterator_to_array($cursor),
            $totalRecords,
            $page,
            $perPage
        ];
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
        $modelClass = $this->modelClass;
        $parsed = $query->getWhere();
        $modelClass::castToDate($parsed);
        
        /**
         * Automatically convert "id" to "_id" in
         * queries like `$query->where(['id' => $id])`.
         */
        if (isset($parsed['id'])) {
            $parsed['_id'] = (int)$parsed['id'];
            unset($parsed['id']);
        }
        
        $data = $query->getWhereNot();
        $modelClass::castToDate($data);
        foreach ($data as $key => $value) {
            $this->addToQuery($parsed, $key, ['$ne' => $value]);
        }
        
        $data = $query->getNot();
        $modelClass::castToDate($data);
        foreach ($data as $key => $value) {
            $this->addToQuery($parsed, $key, ['$not' => $value]);
        }
        
        $data = $query->getGreaterThan();
        $modelClass::castToDate($data);
        foreach ($data as $key => $value) {
            $this->addToQuery($parsed, $key, ['$gt' => $value]);
        }
        
        $data = $query->getLowerThan();
        $modelClass::castToDate($data);
        foreach ($data as $key => $value) {
            $this->addToQuery($parsed, $key, ['$lt' => $value]);
        }
        
        $data = $query->getGreaterThanOrEqualTo();
        $modelClass::castToDate($data);
        foreach ($data as $key => $value) {
            $this->addToQuery($parsed, $key, ['$gte' => $value]);
        }
        
        $data = $query->getlowerThanOrEqualTo();
        $modelClass::castToDate($data);
        foreach ($data as $key => $value) {
            $this->addToQuery($parsed, $key, ['$lte' => $value]);
        }
        
        $data = $query->getWhereNot();
        $modelClass::castToDate($data);
        foreach ($query->getBetween() as $key => $value) {
            $pairFirst = [$key => $value[0]];
            $modelClass::castToDate($pairFirst);
            $pairSecond = [$key => $value[1]];
            $modelClass::castToDate($pairSecond);
            $this->addToQuery($parsed, $key, ['$gte' => $pairFirst[$key], '$lte' => $pairSecond[$key]]);
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
