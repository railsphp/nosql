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
    
    protected function loadAssociation($kind, $attrName, array $options)
    {
        if (!isset($options['className'])) {
            $remoteClass = $this->resolveAssociationClass($attrName);
        } else {
            $remoteClass = $options['className'];
        }
        
        $infl = self::services()->get('inflector');
        
        $foreignKey = $infl->singularize(static::tableName()) . '_id';
        
        switch ($kind) {
            case 'hasMany':
                if (isset($this->attributes[$attrName]) && is_array($this->attributes[$attrName])) {
                    /**
                     * If the attribute is present and it is an array, it is assumed
                     * it's an array of references.
                     * Missing records are ignored.
                     */
                    $assoc = new \Rails\ActiveRecord\Collection();
                    foreach ($this->attributes[$attrName] as $ref) {
                        if ($record = $remoteClass::where(['_id' => (int)$ref['$id']])->first()) {
                            $assoc[] = $record;
                        }
                    }
                } else {
                    /**
                     * Fetch the models from the associated collection.
                     * Note that the documents must have a {referenced_table_name}_id
                     * key (like "user_id") holding a reference to this record.
                     */
                    $assoc = $remoteClass::where([
                        $foreignKey . '.$id' => $this->id()
                    ])->records();
                }
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
                    $assoc = new $remoteClass($refData);
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
        return $this->runCallbacks('create', function() {
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
        });
    }
    
    protected function destroyRecord()
    {
        return $this->runCallbacks('destroy', function() {
            return static::connection()->delete(static::tableName(), ['_id' => $this->id()], ['justOne' => true]);
        });
    }
    
    protected function updateRecord()
    {
        return $this->runCallbacks('update', function() {
            return static::connection()->update(static::tableName(), ['_id' => $this->id()], $this->attributes());
        });
    }
    
    /**
     * $name must be a camelized string.
     */
    protected function resolveAssociationClass($name)
    {
        $name = self::services()->get('inflector')->singularize($name);
        $parts = explode('\\', get_called_class());
        array_pop($parts);
        $parts[] = ucfirst($name);
        return implode('\\', $parts);
    }
}
