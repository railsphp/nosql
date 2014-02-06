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
    
    protected $isNewRecord = true;
    
    protected $isDestroyed = false;
    
    protected $attributes = [];
    
    protected $loadedAssociations = [];
    
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
        $cn     = get_called_class();
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
    
    public function __set($prop, $value)
    {
        $this->attributes[$prop] = $value;
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
            if ($ret = $this->createRecord()) {
                $this->isNewRecord = false;
            }
            return $ret;
        } else {
            return $this->updateRecord();
        }
    }
    
    public function destroy()
    {
        if ($this->destroyRecord()) {
            $this->isDestroyed = true;
            return true;
        }
        return false;
    }
    
    public function isPersisted()
    {
        return !($this->isNewRecord || $this->isDestroyed);
    }
    
    protected function associations()
    {
        return [];
    }
    
    protected function getAssociation($assocName)
    {
        if (!array_key_exists($assocName, $this->loadedAssociations)) {
            $assoc  = null;
            $assocs = $this->normalizeAssociations();
            foreach ($assocs as $kind => $params) {
                foreach ($params as $attrName => $options) {
                    if ($attrName == $assocName) {
                        $assoc = $this->loadAssociation($kind, $attrName, $options);
                    }
                }
            }
            $this->loadedAssociations[$assocName] = $assoc;
        }
        return $this->loadedAssociations[$assocName];
    }
    
    /**
     * Logic regarding associations loading.
     */
    protected function loadAssociation($type, $attrName, array $options)
    {
    }
    
    protected function createRecord()
    {
        return self::connection()->insert(static::tableName(), $this->attributes);
    }
    
    protected function updateRecord()
    {
        return self::connection()->update(static::tableName(), ['id' => $this->id()], $this->attributes());
    }
    
    /**
     * @return bool
     */
    protected function destroyRecord()
    {
        return self::connection()->delete(static::tableName(), ['id' => $this->id()]);
    }
    
    protected function normalizeAssociations()
    {
        $normalized = [];
        foreach ($this->associations() as $kind => $params) {
            $normalized[$kind] = [];
            foreach ($params as $attrName => $options) {
                if (is_int($attrName)) {
                    $attrName = $options;
                    $options  = [];
                } elseif (!is_array($options)) {
                    throw new Exception\RuntimeException(
                        sprintf(
                            'Associations options must be array, %s passed',
                            gettype($options)
                        )
                    );
                }
                $normalized[$kind][$attrName] = $options;
            }
        }
        return $normalized;
    }
}
