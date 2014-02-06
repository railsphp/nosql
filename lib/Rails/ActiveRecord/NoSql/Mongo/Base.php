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
    
    public function attributes()
    {
        $attributes = $this->attributes;
        foreach ($this->embeddedAssociations() as $attrName => $options) {
            if (is_int($attrName)) {
                $attrName = $options;
                $options  = [];
            }
            if (isset($this->attributes[$attrName]) && $this->attributes[$attrName] instanceof self) {
                $attributes[$attrName] = $attributes[$attrName]->attributes();
            }
        }
        return $attributes;
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
    
    protected function embeddedAssociations()
    {
        return [];
    }
    
    protected function getAssociation($assocName)
    {

    }
    
    protected function loadAssociation($kind, $attrName, array $options)
    {
        if (!isset($options['className'])) {
            $assocClass = $this->resolveAssociationClass($attrName);
        } else {
            $assocClass = $options['className'];
        }
        $infl = self::services()->get('inflector');
        
        switch ($kind) {
            case 'hasMany':
                $ref = \MongoDBRef::create(
                    static::connection()->database()->selectCollection(static::tableName()),
                    $this->id()
                );
                $many = \MongoDBRef::get($ref);
                // $remoteClass = $options['className'];
                // $query = $remoteClass::where([
                // $many = [];
                // foreach ($this->$attrName as $ref) {
                    // \MongoDBRef::get(static::connection()->database(), $attrName);
                // }
                break;
            
            case 'belongsTo':
                $refAttr = $infl->underscore($attrName) . '_id';
                
                if (!isset($this->attributes[$refAttr])) {
                    return null;
                } elseif (!is_array($this->attributes[$refAttr])) {
                    throw new Exception\InvalidArgumentException(
                        sprintf(
                            "Invalid value in attribute '%s' for class '%s' for belongsTo association",
                            $refAttr,
                            get_called_class()
                        )
                    );
                }
                
                $refData = \MongoDBRef::get(static::connection()->database(), $this->attributes[$refAttr]);
                if ($refData) {
                    $assoc = new $assocClass($refData);
                    $assoc->isNewRecord = false;
                }
                break;
        }
        
        return $assoc;
    }
    
    /**
     * @throw Exception\RuntimeException
     */
    protected function createRecord()
    {
        $attributes = $this->attributes();
        $infl = self::services()->get('inflector');
        
        $associations = $this->normalizeAssociations();
        if (isset($associations['belongsTo'])) {
            foreach ($associations['belongsTo'] as $attrName => $options) {
                $refAttr = $infl->underscore($attrName) . '_id';
                
                if (isset($this->attributes[$refAttr])) {
                    switch (true) {
                        case (
                            is_scalar($this->attributes[$refAttr]) &&
                            ctype_digit((string)$this->attributes[$refAttr])
                        ) :
                            # String or int; referencing id.
                            if (!isset($options['className'])) {
                                $options['className'] = $this->resolveAssociationClass($attrName);
                            }
                            $remoteClass = $options['className'];
                            $remoteColl  = $remoteClass::tableName();
                            $ref = \MongoDBRef::create($remoteColl, (int)$this->attributes[$refAttr], static::connection()->dbName());
                            $this->attributes[$refAttr] = $ref;
                            break;
                        
                        case is_array($this->attributes[$refAttr]):
                            # It's assumed the reference was already created.
                            break;
                        
                        default:
                            throw new Exception\RuntimeException(
                                srptinf(
                                    "Association attribute '%s' must be either numeric or array, '%s' passed",
                                    $refAttr,
                                    gettype($this->attributes[$refAttr])
                                )
                            );
                            break;
                    }
                } else {
                    $this->attributes[$attrName] = null;
                }
            }
        }
        return static::connection()->insert(static::tableName(), $this->attributes);
    }
    
    protected function destroyRecord()
    {
        return static::connection()->delete(static::tableName(), ['_id' => $this->id()], ['justOne' => true]);
    }
    
    protected function updateRecord()
    {
        return static::connection()->update(static::tableName(), ['_id' => $this->id()], $this->attributes());
    }
    
    /**
     * $name must be a camelized string.
     */
    protected function resolveAssociationClass($name)
    {
        $parts = explode('\\', get_called_class());
        array_pop($parts);
        $parts[] = ucfirst($name);
        return implode('\\', $parts);
    }
}
