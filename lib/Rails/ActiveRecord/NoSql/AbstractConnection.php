<?php
namespace Rails\ActiveRecord\NoSql;

abstract class AbstractConnection
{
    protected $resource;
    
    protected $dbName;
    
    abstract public function __construct(array $config = []);
    
    abstract public function insert($tableName, array $attributes, array $options = []);
    
    abstract public function select($tableName, array $criteria, array $queryOptions, array $options = []);
    
    abstract public function update($tableName, array $criteria, array $data, array $options = []);
    
    abstract public function delete($tableName, array $criteria, array $options = []);
    
    abstract public function queryToArray(Query\Select $query);
    
    /**
     * Returns an array whose first value is the parsed query, usable for the connection,
     * and second value are the query options returned by extractQueryOptions().
     *
     * @return array
     */
    public function parseQuery(Query\Select $query)
    {
        return [$this->queryToArray($query), $this->extractQueryOptions($query)];
    }
    
    public function resource()
    {
        return $this->resource;
    }
    
    public function selectDb($dbName)
    {
    }
    
    public function createDb($dbName)
    {
    }
    
    protected function extractQueryOptions($query)
    {
        return [
            'limit'  => $query->getLimit(),
            'offset' => $query->getOffset(),
            'order'  => $query->getOrder(),
            'page'   => $query->getPage()
        ];
    }
}
