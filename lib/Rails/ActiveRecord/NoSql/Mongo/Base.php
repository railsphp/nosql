<?php
namespace Rails\ActiveRecord\NoSql\Mongo;

abstract class Base extends \Rails\ActiveRecord\NoSql\AbstractBase
{
    static protected $connection;
    
    # Override because of _id.
    static public function find($id)
    {
        if (!$id instanceof \MongoId) {
            $id = (int)$id;
        }
        $query = self::getRelation('where', [['_id' => $id]]);
        $record = $query->first();
        if (!$record) {
            throw new \Rails\ActiveRecord\Exception\RecordNotFoundException(
                sprintf("Couldn't find record with id %s", $id)
            );
        }
        return $record;
    }
    
    public function assignAttributes(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            if ($name == '_id') {
                if (!$value instanceof \MongoId) {
                    $value = (int)$value;
                }
            }
            $this->attributes[$name] = $value;
        }
        return $this;
    }
    
    public function id()
    {
        if (isset($this->attributes['_id'])) {
            if ($this->attributes['_id'] instanceof \MongoId) {
                return $this->attributes['_id']->__toString();
            } else {
                return (int)$this->attributes['_id'];
            }
        }
    }
}
