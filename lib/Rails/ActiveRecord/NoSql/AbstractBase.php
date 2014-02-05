<?php
namespace Rails\ActiveRecord\NoSql;

abstract class AbstractBase
{
    static protected $connection;
    
    static private $RELATION_METHODS = [
        'order', 'offset', 'limit', 'where', 'whereNot', 'not', 
        'greaterThan', 'lowerThan', 'equalOrGreaterThan', 'equalOrLowerThan',
        'between', 'like', 'alike'
    ];
    
    protected $attributes = [];
    
    private $isNewRecord = true;
    
    static public function services()
    {
        return \Rails::services();
    }
    
    static public function connection()
    {
        return static::$connection;
    }
    
    static public function setConnection(AbstractConnection $connection)
    {
        static::$connection = $connection;
    }
    
    static public function __callStatic($method, $params)
    {
        if (in_array($method, self::$RELATION_METHODS)) {
            return self::getRelation($method, $params);
        }
        throw new \Rails\Exception\BadMethodCallException(
            sprintf("Called to unknown static method %s::%s", get_called_class(), $method)
        );
    }
    
    static public function tableName()
    {
        $cn = str_replace('\\', '_', get_called_class());
        $inf = self::services()->get('inflector');
        return $tableName = $inf->underscore($inf->pluralize($cn));
    }
    
    static public function create(array $params)
    {
        $cn = get_called_class();
        $record = new $cn($params);
        $record->save();
    }
    
    static public function find($id)
    {
        $query = self::getRelation('where', [['id' => $id]]);
        $record = $query->first();
        if (!$record) {
            throw new \Rails\ActiveRecord\Exception\RecordNotFoundException(
                sprintf("Couldn't find record with id %s", $id)
            );
        }
        return $record;
    }
    
    static protected function getRelation($initMethod, array $params)
    {
        $relation = new ModelRelation(static::connection(), get_called_class());
        call_user_func_array([$relation, $initMethod], $params);
        $relation->from(static::tableName());
        return $relation;
    }
    
    public function __construct(array $attributes = [])
    {
        if ($attributes) {
            $this->assignAttributes($attributes);
        }
    }
    
    public function attributes()
    {
        return $this->attributes;
    }
    
    public function assignAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }
    
    public function save()
    {
        if ($this->isNewRecord) {
            $this->createRecord();
        } else {
            
        }
    }
    
    private function createRecord()
    {
        self::connection()->insert(static::tableName(), $this->attributes);
        $this->isNewRecord = false;
    }
}
