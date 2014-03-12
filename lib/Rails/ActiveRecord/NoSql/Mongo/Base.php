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
    
    static public function connection()
    {
        return static::$connection->setModelClass(get_called_class());
    }
    
    public function __construct(array $attributes = [], $isNewRecord = true)
    {
        $this->isNewRecord = (bool)$isNewRecord;
        
        if (!$this->isNewRecord) {
            self::castFromDate($attributes);
        }
        
        if ($attributes) {
            $this->assignAttributes($attributes);
        }
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
                        $infl->singularize(static::tableName()) . '.$id' => $this->id()
                    ]);
                }
                break;
            
            case 'belongsTo':
                if (!isset($this->attributes[$attrName])) {
                    return null;
                } elseif (!is_array($this->attributes[$attrName])) {
                    throw new Exception\InvalidArgumentException(
                        sprintf(
                            "Belongs-to attribute value %s::$%s must be MongoDBRef (array), %s passed",
                            get_called_class(),
                            $attrName,
                            gettype($attrName)
                        )
                    );
                }
                
                $refData = \MongoDBRef::get(static::connection()->database(), $this->attributes[$attrName]);
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
            $infl = self::services()->get('inflector');
            
            $associations = $this->normalizeAssociations();
            
            if (isset($associations['belongsTo'])) {
                /**
                 * Example:
                 * A reference for model "User" will be saved in the "user" attribute.
                 * It is check if the "user_id" attribute exists. If it does, the reference
                 * is created out of that id and stored in the "user" attribute.
                 * If "user_id" doesn't exist, it is check if the "user" attribute exists and
                 * is an array. If so, it's assumed the reference was already created.
                 * If all this fails, an exception is thrown.
                 */
                foreach ($associations['belongsTo'] as $attrName => $options) {
                    $refAttr  = $infl->underscore($attrName);
                    $refAttrId = $refAttr . '_id';
                    
                    if (isset($this->attributes[$refAttrId])) {
                        switch (true) {
                            case (
                                is_int($this->attributes[$refAttrId]) ||
                                is_string($this->attributes[$refAttrId])
                            ) :
                                # String or int; referencing id.
                                if (!isset($options['className'])) {
                                    $options['className'] = $this->resolveAssociationClass($attrName);
                                }
                                $remoteClass = $options['className'];
                                $remoteColl  = $remoteClass::tableName();
                                $ref = \MongoDBRef::create($remoteColl, (int)$this->attributes[$refAttrId], static::connection()->dbName());
                                $this->attributes[$refAttr] = $ref;
                                break;
                            
                            case isset($this->attributes[$refAttr]) && is_array($this->attributes[$refAttr]):
                                # It's assumed the reference was already created.
                                break;
                            
                            default:
                                throw new Exception\RuntimeException(
                                    srptinf(
                                        "Failed to create association: neither attribute '%s' and '%s' exist or didn't match conditions",
                                        $refAttrId,
                                        $refAttr
                                    )
                                );
                                break;
                        }
                    } else {
                        $this->attributes[$attrName] = null;
                    }
                }
            }
            
            self::castToDate($this->attributes, true);
            $resp = static::connection()->insert(static::tableName(), $this->attributes);
            self::castFromDate($this->attributes);
            return $resp;
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
            $attributes = $this->attributes;
            $this->castToDate($attributes);
            return static::connection()->update(static::tableName(), ['_id' => $this->id()], $attributes);
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
    
    static public function castFromDate(array &$attributes)
    {
        $dateAttributes = self::allDateFields();
        foreach (array_intersect_key($attributes, array_fill_keys($dateAttributes, null)) as $name => $value) {
            if ($value instanceof \MongoDate) {
                $attributes[$name] = date('Y-m-d H:i:s', $value->sec);
            }
        }
    }
    
    static public function castToDate(array &$attributes, $foo = false)
    {
        $dateAttributes = self::allDateFields();
        
        foreach (array_intersect_key($attributes, array_fill_keys($dateAttributes, null)) as $attrName => $value) {
            if ($value) {
                if (!$value instanceof \MongoDate) {
                    if (!is_int($value)) {
                        $value = strtotime($value);
                        if ($value === false) {
                            throw new Exception\InvalidArgumentException(
                                sprintf(
                                    "Invalid value passed to date field %s::%s (%s)",
                                    get_called_class(),
                                    $attrName,
                                    (is_scalar($value) ? $value : gettype($value))
                                )
                            );
                        }
                    }
                    $attributes[$attrName] = new \MongoDate($value);
                }
            }
        }
    }
    
    static public function dateFields()
    {
        return [];
    }
    
    static public function allDateFields()
    {
        return array_merge(self::dateAttributes(), static::dateFields());
    }
}
