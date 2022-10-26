<?php

// +----------------------------------------------------------------------
// | Simplestart Think
// +----------------------------------------------------------------------
// | 版权所有: https://www.simplestart.cn copyright 2020
// +----------------------------------------------------------------------
// | 开源协议: https://www.apache.org/licenses/LICENSE-2.0.txt
// +----------------------------------------------------------------------
// | 仓库地址: https://github.com/simplestart-cn/start-think
// +----------------------------------------------------------------------

namespace start;

use think\Container;
use think\facade\Cache;
use think\db\BaseQuery as Query;

/**
 * 自定义模型基类
 * Class Model
 * @package start
 */
class Model extends \think\Model
{
    /**
     * 使用全局查询
     *
     * @var boolean
     */
    public $useScope = true;

    /**
     * 关联
     * @var array
     */
    protected $with = [];

    /**
     * 查询
     * @var array
     */
    protected $where = [];

    /**
     * 排序
     */
    protected $order = [];

    /**
     * 数据库配置
     * @var string
     */
    protected $connection;

    /**
     * 模型名称
     * @var string
     */
    protected $name;

    /**
     * 主键值
     * @var string
     */
    protected $key;

    /**
     * 数据表名称
     * @var string
     */
    protected $table;

    /**
     * 数据表字段信息 留空则自动获取
     * @var array
     */
    protected $schema = [];

    /**
     * JSON数据表字段
     * @var array
     */
    protected $json = [];

    /**
     * JSON数据表字段类型
     * @var array
     */
    protected $jsonType = [];

    /**
     * JSON数据取出是否需要转换为数组
     * @var bool
     */
    protected $jsonAssoc = false;

    /**
     * 数据表后缀
     * @var string
     */
    protected $suffix;

    /**
     * 架构函数
     * @access public
     * @param array $data 数据
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        // 固定name属性为模型名(解决TP关联查询alias别名问题)
        if (!empty($this->name)) {
            if (empty($this->table)) {
                $this->table = $this->name;
            }
            $name       = str_replace('\\', '/', static::class);
            $this->name = basename($name);
        }
        // 执行初始化操作
        $this->initialize();
    }

    // 模型初始化
    protected static function init()
    {
        self::instance()->initialize();
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        return $this;
    }

    /**
     * 静态实例对象
     * @param array $args
     * @return static
     */
    public static function instance(...$args)
    {
        return Container::getInstance()->make(static::class, $args);
    }

    /**
     * 获取所有数据
     * @param     array                         $filter   [description]
     * @param     array                         $order     [description]
     * @param     array                         $with    [description]
     * @return    [type]                                  [description]
     */
    function list($filter = [], $order = [], $with = null)
    {
        $with  = is_array($with) ? $with : $this->with;
        $order = !empty($order) ? $order : $this->order;
        $where = is_array($filter) ? array_merge($this->where, $filter) : $filter;
        return $this
            ->filter($where)
            ->with($with)
            ->order($order)
            ->select();
    }

    /**
     * 获取分页数据
     * @param     array                         $filter   [description]
     * @param     array                         $order    [description]
     * @param     array                         $with     [description]
     * @param     array                         $paging   [description]
     * @return    [type]                                  [description]
     */
    public function page($filter = [], $order = [], $with = null, $paging = [])
    {
        $with  = is_array($with) ? $with : $this->with;
        $order = !empty($order) ? $order : $this->order;
        $where = is_array($filter) ? array_merge($this->where, $filter) : $filter;
        if (!is_array($paging)) {
            $paging = ['page' => (int) $paging];
        }
        if (!isset($paging['page'])) {
            $paging['page'] = input('page', 1, 'trim');
        }
        if (!isset($paging['per_page'])) {
            $paging['list_rows'] = input('per_page', 30, 'trim');
        }
        return $this
            ->filter($where)
            ->with($with)
            ->order($order)
            ->paginate($paging, false);
    }

    /**
     * 获取详情
     * @param     array                         $filter   [description]
     * @param     array                         $with     [description]
     * @return    [type]                                  [description]
     */
    public function info($filter, $with = null)
    {
        $with  = is_array($with) ? $with : $this->with;
        $where = is_array($filter) ? array_merge($this->where, $filter) : $filter;
        if (!is_array($filter)) {
            return $this->with($with)->find($where);
        } else {
            return $this->filter($where)->with($with)->find();
        }
    }

    /**
     * 软删除
     */
    public function remove()
    {
        if (isset($this->is_deleted)) {
            return $this->save(['is_deleted' => 1]);
        } else {
            return $this->delete();
        }
    }

    /**
     * 更新数据
     * 注：修复TP6.0.2开启全局查询时没有添加自动加上主键查询条件的问题
     * @access public
     * @param array  $data       数据数组
     * @param mixed  $where      更新条件
     * @param array  $allowField 允许字段
     * @param string $suffix     数据表后缀
     * @return static
     */
    public static function update(array $data, $where = [], array $allowField = [], string $suffix = '')
    {
        $model = new static($data);
        $pk    = $model->getPk();
        if (!isset($data[$pk]) || empty($data[$pk])) {
            throw_error("$pk can not empty");
        }
        $result = $model->find($data[$pk]);
        if (empty($result)) {
            throw_error($model->name . ' not found');
        }

        if (!empty($allowField)) {
            $result->allowField($allowField);
        }

        if (!empty($where)) {
            $result->setUpdateWhere($where);
        }

        if (!empty($suffix)) {
            $result->setSuffix($suffix);
        }

        $result->exists(true)->save($data);

        return $result;
    }

    /**
     * 关闭全局查询
     * 修复tp6的大问题
     *
     * @param array $scope
     * @return this
     */
    public function withoutScope(array $scope = null)
    {
        if (is_array($scope)) {
            $this->globalScope = array_diff($this->globalScope, $scope);
        }
        if (empty($this->globalScope) || $scope == null) {
            $this->useScope = false;
        }
        return $this;
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param array $scope 设置不使用的全局查询范围
     * @return Query
     */
    public function db($scope = []): Query
    {
        /** @var Query $query */
        $query = parent::$db->connect($this->connection)
            ->name($this->name . $this->suffix)
            ->pk($this->pk);

        if (!empty($this->table)) {
            $query->table($this->table . $this->suffix);
        }

        $query->model($this)
            ->json($this->json, $this->jsonAssoc)
            ->setFieldType(array_merge($this->schema, $this->jsonType));

        // 软删除
        if (property_exists($this, 'withTrashed') && !$this->withTrashed) {
            $this->withNoTrashed($query);
        }
        // 全局作用域(修复关联查询作用域问题,修复存在主键条件时依然使用全局查询的问题)
        if ($this->useScope && is_array($this->globalScope) && is_array($scope)) {
            $globalScope = array_diff($this->globalScope, $scope);
            $where = $this->getWhere();
            $wherePk = false;
            if (!empty($where) && is_array($where)) {
                foreach ($where as $item) {
                    if (is_string($this->pk)) {
                        if (in_array($this->pk, $item)) {
                            $wherePk = true;
                        }
                    } else if (is_array($this->pk) && count($item) > 0) {
                        if (in_array($item[0], $this->pk)) {
                            $wherePk = true;
                        }
                    }
                }
            }
            if (!$wherePk) {
                $query->scope($globalScope);
            }
        }

        // 返回当前模型的数据库查询对象
        return $query;
    }

    /**
     * 全局查询
     * 实现db对象链式调用
     * @param [type] $query
     * @param array $input
     * @return void
     */
    public function scopeFilter($query, $input = [])
    {
        return $this->filter($input, $query);
    }

    /**
     * 条件查询，支持操作符查询及关联表查询
     * @param  array  $input [description]
     * @return [type]         [description]
     *
     * input 结构支持
     * $input = 1;
     * $input = [
     *     'key1' => 1,
     *     'key2' => [1,2,3],
     *     'key3' => ['!=', 1],
     *     'key4' => ['in', [1,2,3]],
     *     'key5@!='   => 1,
     *     'key6@like' => "%$keyword%",
     *     'with.key1' => [1,2,3],
     *     'with.key2' => ['like', "%$string%"]
     *     'key1|key2' => value,
     *     'with1.key1|with2.key2' => value,
     *     'with1.key1|key2' => 
     * ];
     */
    public function filter($input = [], $query = null)
    {
        $query = $query ?? $this;  // 查询对象(Query)
        $filter = [];
        $hasModel = [];     // 已关联模型
        $hasWhere = false;  // 是否关联查询
        $hasWhereOr = [];   // 关联OR查询
        $hasWhereAnd = [];  // 关联AND查询
        $options = $query->getOptions();
        
        if (!$this->useScope) {
            $static = new static();
            $static->useScope = false;
            $query =  $static->db(null);
        }
        if (empty($input)) {
            return $query;
        }
        if (!is_array($input)) {
            return $query->where($input);
        } else if (count($input) > 0) {
            // 数据字典
            $table = $this->getTable();
            $tableFields = Cache::get($table . '_fields');
            if (empty($tableFields) || env('APP_DEBUG')) {
                $tableFields = $this->getTableFields();
                Cache::set($table . '_fields', $tableFields);
            }
            // 分割查询
            foreach ($input as $key => $value) {
                // 过滤空字段
                if($value === '' || $value === null){
                    continue;
                }
                // 过滤非表字段
                if (!in_array($key, $tableFields) && stripos($key, '|') === false && stripos($key, '@') === false && stripos($key, '.') === false) {
                    continue;
                }
                // 关联查询
                if (stripos($key, '|') !== false && stripos($key, '.') !== false) {
                    $orFields = explode('|', $key);
                    $orCondition = array();
                    foreach ($orFields as $orField) {
                        if (stripos($orField, '.') !== false) {
                            list($model, $field) = explode('.', $orField);
                            !isset($orCondition[$model]) ? $orCondition[$model] = [] : '';
                            $orCondition[$model][$field] = $value;
                            if (!in_array($model, $hasModel)) {
                                $query = $query->hasWhere($model, []);
                                array_push($hasModel, $model);
                            }
                        } else {
                            !isset($orCondition['this']) ? $orCondition['this'] = [] : '';
                            $orCondition['this'][$orField]  = $value;
                        }
                    }
                    $hasWhereOr[] = $orCondition;
                    continue;
                } else if (stripos($key, '.') !== false) {
                    list($model, $field) = explode('.', $key);
                    !isset($hasWhereAnd[$model]) ? $hasWhereAnd[$model] = [] : '';
                    $hasWhereAnd[$model][$field] = $value;
                    continue;
                }
                $filter[$key] = $value;
            }

            // 关联AND查询
            if (!empty($hasWhereAnd)) {
                $hasWhere = true;
                foreach ($hasWhereAnd as $model => $condition) {
                    $relateTable = $this->$model()->getName();
                    if (in_array($model, $hasModel)) {
                        $query = $this->parseFilter($query, $condition, $relateTable);
                    } else {
                        array_push($hasModel, $model);
                        $query = $query->hasWhere($model, $this->parseFilter($query, $condition, $relateTable));
                    }
                }
            }
            
            // 关联OR查询
            if (!empty($hasWhereOr)) {
                $that = $this;
                $hasWhere = true;
                foreach ($hasWhereOr as $relation) {
                    $query = $query->where(function ($query) use ($that, $relation) {
                        foreach ($relation as $model => $condition) {
                            $query = $query->whereOr(function ($query) use ($that, $model, $condition) {
                                if ($model === 'this') {
                                    $query = $that->parseFilter($query, $condition, $that->getName(), "OR");
                                } else {
                                    $relateTable = $that->$model()->getName();
                                    $query = $that->parseFilter($query, $condition, $relateTable, 'OR');
                                }
                            });
                        }
                    });
                }
            }       
            // 设置别名
            if ($hasWhere) {
                $query = $query->alias($this->getName());
            }
            // 主表查询
            if (is_null($query)) {
                $query = $this->parseFilter($this, $filter, $hasWhere ? $this->getName() : '');
            } else {
                $query = $this->parseFilter($query, $filter, $hasWhere ? $this->getName() : '');
            }
            return $query ?: $this;
        }
    }

    /**
     * 解析查询语句，支持操作符查询及关联表查询
     * @param  [type] $query     [description]
     * @param  array  $condition [description]
     * @param  string $table     [description]
     *  @param string $logic     查询逻辑 AND OR
     * @return [type]            [description]
     */
    private function parseFilter($query, array $condition = [], $table = '', $logic = 'AND')
    {

        $operator = ['=', '<>', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in', 'between', 'not between', 'null', 'not null', 'exists', 'not exists', 'regexp', 'not regexp'];
        if (!empty($table) && stripos($table, '.') === false) {
            $table .= '.';
        }
        foreach ($condition as $key => $value) {
            // 空字段过滤
            if (empty($value) && !is_numeric($value)) {
                continue;
            }
            if(stripos($key, '|') !== false){
                $keys = explode('|', $key);
                $query = $logic === 'AND' ? $query->where(function($query) use ($table, $keys, $value, $operator){
                    foreach ($keys as $k) {
                        // 兼容<1.0.7版本
                        if (is_array($value) && count($value) > 1 && in_array(strtolower($value[0]), $operator)) {
                            $k = $k .'@'. $value[0];
                            $value = $value[1];
                        }
                        $query = $this->parseFilterItem($query, $table, $k, $value, "OR");
                    }
                }) : $query->whereOr(function($query) use ($table, $keys, $value, $operator){
                    foreach ($keys as $k) {
                        // 兼容<1.0.7版本
                        if (is_array($value) && count($value) > 1 && in_array(strtolower($value[0]), $operator)) {
                            $k = $k .'@'. $value[0];
                            $value = $value[1];
                        }
                        $query = $this->parseFilterItem($query, $table, $k, $value, "OR");
                    }
                });
            } else if(is_array($value)){
                // 兼容<1.0.7版本
                if (count($value) > 1 && in_array(strtolower($value[0]), $operator)) {
                    $key = $key .'@'. $value[0];
                    $value = $value[1];
                }
                $query = $this->parseFilterItem($query, $table, $key, $value, $logic);
            } else {
                $query = $this->parseFilterItem($query, $table, $key, $value, $logic);            
            }
        }
        return $query;
    }

    /**
     * 解析单个项目
     * @param object $query
     * @param string $table
     * @param string $key
     * @param string $value
     * @param string $logic
     * @return object
     */
    private function parseFilterItem($query, $table='', $key, $value, $logic = 'AND')
    {
        $opera = '=';
        if(stripos($key, '@') !== false){
            list($key, $opera) = explode('@', $key);
        }
        if ($opera === 'like' || $opera === 'not like') {
            $value = stripos($value, '%') === false ? '%' . $value . '%' : $value;
        }
        return $logic === 'AND' ? $query->where($table . $key, $opera, $value) : $query->whereOr($table . $key, $opera, $value);   
    }
}
