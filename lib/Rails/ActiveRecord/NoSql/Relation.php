<?php
namespace Rails\ActiveRecord\NoSql;

class Relation extends Query\Select implements \IteratorAggregate
{
    protected $connection;
    
    protected $loaded  = false;
    
    protected $records = [];
    
    public function __construct(AbstractConnection $connection)
    {
        $this->connection = $connection;
    }
    
    public function getIterator()
    {
        if (!$this->loaded) {
            $this->load();
        }
        return new \ArrayObject($this->records);
    }
    
    public function first($limit = 1)
    {
        $this->load();
        if ($this->records) {
            if ($limit == 1) {
                return current($this->records);
            } else {
                /**
                 * Mongo will return an array whose keys are the _id of the document.
                 * Thus the array_values().
                 */
                return array_slice(array_values($this->records), 0, $limit);
            }
        }
    }
    
    public function take($limit = 1)
    {
        $this->limit($limit);
        $this->load();
        return $this->records;
    }
    
    /**
     * @throw Exception\RuntimeException
     */
    public function paginate($page = null, $perPage = null)
    {
        if ($page) {
            $this->page($page);
        }
        if ($perPage) {
            $this->perPage($perPage);
        }
        if (!$this->page || !$this->limit) {
            throw new Exception\RuntimeException(
                "Both page and perPage must be defined in order to paginate."
            );
        }
        $this->load();
        return $this->records;
    }
    
    public function load(array $options = [])
    {
        if (!$this->loaded) {
            list($criteria, $queryOptions) = $this->connection->parseQuery($this);
            $this->records = $this->connection->select($this->getFrom(), $criteria, $queryOptions, $options);
            $this->loaded  = true;
        }
        return $this;
    }
    
    public function reset()
    {
        $this->records = [];
        $this->loaded  = false;
        return $this;
    }
}
