<?php
namespace Rails\ActiveRecord\NoSql\Query;

class Select
{
    protected $from;
    
    protected $offset;
    
    protected $limit;
    
    protected $order       = [];
    
    protected $where       = [];
    
    protected $whereNot    = [];
    
    protected $not         = [];
    
    protected $greaterThan = [];
    
    protected $lowerThan   = [];
    
    protected $between     = [];
    
    protected $like        = [];
    
    protected $alike       = [];
    
    protected $equalOrLowerThan   = [];
    
    protected $equalOrGreaterThan = [];
    
    protected $page;
    
    public function from($params)
    {
        $this->from = $params;
        return $this;
    }
    
    /**
     * @var array|string $params
     */
    public function order($params)
    {
        $this->order = array_merge($this->order, (array)$params);
        return $this;
    }
    
    public function offset($params)
    {
        $this->offset = $params;
        return $this;
    }
    
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }
    
    public function where(array $params)
    {
        $this->where = array_merge($this->whereNot, $params);
        return $this;
    }
    
    /**
     * This equals to value != other_value.
     */
    public function whereNot(array $params)
    {
        $this->whereNot = array_merge($this->whereNot, $params);
        return $this;
    }
    
    /**
     * This equals to value NOT > 9.
     */
    public function not(array $params)
    {
        $this->not = array_merge($this->not, $params);
        return $this;
    }
    
    public function greaterThan(array $params)
    {
        $this->greaterThan = array_merge($this->greaterThan, $params);
        return $this;
    }
    
    public function lowerThan(array $params)
    {
        $this->lowerThan = array_merge($this->lowerThan, $params);
        return $this;
    }
    
    public function equalOrGreaterThan(array $params)
    {
        $this->equalOrGreaterThan = array_merge($this->equalOrGreaterThan, $params);
        return $this;
    }
    
    public function equalOrLowerThan(array $params)
    {
        $this->equalOrLowerThan = array_merge($this->equalOrLowerThan, $params);
        return $this;
    }
    
    public function between(array $params)
    {
        $this->between = array_merge($this->between, $params);
        return $this;
    }
    
    public function like(array $params)
    {
        $this->like = array_merge($this->like, $params);
        return $this;
    }
    
    public function alike(array $params)
    {
        $this->alike = array_merge($this->alike, $params);
        return $this;
    }
    
    public function page($page)
    {
        $this->page = $page;
    }
    
    /**
     * Alias of limit().
     */
    public function perPage($limit)
    {
        $this->limit = $limit;
    }
    
    public function getFrom()
    {
        return $this->from;
    }
    
    public function getOrder()
    {
        return $this->order;
    }
    
    public function getOffset()
    {
        return $this->offset;
    }
    
    public function getLimit()
    {
        return $this->limit;
    }
    
    public function getWhere()
    {
        return $this->where;
    }
    
    public function getWhereNot()
    {
        return $this->whereNot;
    }
    
    public function getNot()
    {
        return $this->not;
    }
    
    public function getGreaterThan()
    {
        return $this->greaterThan;
    }
    
    public function getLowerThan()
    {
        return $this->lowerThan;
    }
    
    public function getEqualOrGreaterThan()
    {
        return $this->equalOrGreaterThan;
    }
    
    public function getEqualOrLowerThan()
    {
        return $this->equalOrLowerThan;
    }
    
    public function getBetween()
    {
        return $this->between;
    }
    
    public function getLike()
    {
        return $this->like;
    }
    
    public function getAlike()
    {
        return $this->alike;
    }
    
    public function getPage()
    {
        return $this->page;
    }
    
    /**
     * Alias of getLimit().
     */
    public function getPerPage()
    {
        return $this->limit;
    }
}
