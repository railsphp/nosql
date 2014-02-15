<?php
namespace Rails\ActiveRecord\NoSql;

class ModelRelation extends Relation
{
    protected $modelClass;
    
    public function __construct(AbstractConnection $connection, $modelClass)
    {
        parent::__construct($connection);
        $this->modelClass = $modelClass;
    }
    
    public function getIterator()
    {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->records;
    }
    
    public function first($limit = 1)
    {
        $this->load();
        if ($this->records->any()) {
            if ($limit == 1) {
                return current($this->records->toArray());
            } else {
                return $this->records->slice(0, $limit);
            }
        }
    }
    
    public function load(array $options = [])
    {
        parent::load($options);
        
        $models = [];
        if ($this->records) {
            $modelClass = $this->modelClass;
            foreach (array_values($this->records) as $attributes) {
                $models[] = new $modelClass($attributes, false);
            }
        }
        
        $this->records = new \Rails\ActiveRecord\Collection($models);
        return $this;
    }
}
