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

use Closure;
use think\Container;

/**
 * 自定义模型基类
 * Class Model
 * @package start
 */
class Model extends \think\Model
{

    use model\concern\SoftDelete;

    /**
     * 模型名称
     * @var string
     */
    protected $name;

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
     * @var array
     */
    protected $order = [];

    /**
     * 使用全局查询
     * @var boolean
     */
    public $useScope = true;

    /**
     * 全局查询范围
     * @var array
     */
    protected $globalScope = [];

    /**
     * 启用软删除
     *
     * @var boolean
     */
    protected $softDelete = false;

    /**
     * 软删除字段
     * @var string
     */
    protected $deleteTime = 'delete_time';

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
     * 获取列表数据
     * @param     array                         $filter   [description]
     * @param     array                         $order    [description]
     * @param     array                         $with     [description]
     * @return    \think\Collection                       [description]
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
     * @return    \think\Collection                       [description]
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
     * @return    \start\Model                            [description]
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
     * 获取当前模型的数据库查询对象
     * @access public
     * @param array $scope 设置不使用的全局查询范围
     * @return Query
     */
    public function db($scope = []): Query
    {
        
        /** @var Query $query */
        $query = self::$db->connect($this->connection)
            ->name($this->name . $this->suffix)
            ->pk($this->pk);

        if (!empty($this->table)) {
            $query->table($this->table . $this->suffix);
        }

        $query->model($this)
            ->json($this->json, $this->jsonAssoc)
            ->setFieldType(array_merge($this->schema, $this->jsonType));

        // 根据数据表字段自动软删除
        $fields = $query->getTableFields();
        $deleteFiled = $this->getDeleteTimeField();
        if (in_array($deleteFiled, $fields)){
            $this->softDelete = true;
        }
        if( $this->softDelete && (!property_exists($this, 'withTrashed') || !$this->withTrashed)) {
            $this->withNoTrashed($query);
        }
        
        // 全局作用域
        $request = request();
        if ($this->useScope) {
            // 模型全局查询
            if(is_array($scope)){
                $globalScope = array_diff($this->globalScope, $scope);
                $query->scope($globalScope);
            }
            // 请求全局查询
            $globalQuery = $request->globalQuery ?? [];
            if(!empty($globalQuery) && $request->useScope){
                foreach ($globalQuery as $name => $callback) {
                    if(is_array($scope) && in_array($name, $scope)){
                        continue;
                    }
                    if($callback instanceof Closure ){
                        call_user_func($callback, $query);
                    }
                }
            }
        }
        
        return $query;
    }

    /**
     * 更新数据
     * 注：修复TP6.0.2开启全局查询时没有添加自动加上主键查询条件的问题
     * @param array  $data       数据数组
     * @param mixed  $where      更新条件
     * @param array  $allowField 允许字段
     * @param string $suffix     数据表后缀
     * @return \think\Model
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
     * 删除当前的记录
     * @access public
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->isExists() || $this->isEmpty() || false === $this->trigger('BeforeDelete')) {
            return false;
        }

        $force = $this->isForce();
        if ($this->softDelete && !$force) {
            // 软删除
            $name  = $this->getDeleteTimeField();
            $this->set($name, $this->autoWriteTimestamp());

            $this->exists()->withEvent(false)->save();

            $this->withEvent(true);
        } else {
            // 读取更新条件
            $where = $this->getWhere();

            // 删除当前模型数据
            $this->db()
                ->where($where)
                ->removeOption('soft_delete')
                ->delete();

            $this->lazySave(false);
        }

        // 关联删除
        if (!empty($this->relationWrite)) {
            $this->autoRelationDelete($force);
        }

        $this->trigger('AfterDelete');

        $this->exists(false);

        return true;
    }

    /**
     * 删除记录
     * @access public
     * @param mixed $data 主键列表 支持闭包查询条件
     * @param bool $force 是否强制删除
     * @return bool
     */
    public static function destroy($data, bool $force = false): bool
    {
        // 传入空值（包括空字符串和空数组）的时候不会做任何的数据删除操作，但传入0则是有效的
        if (empty($data) && 0 !== $data) {
            return false;
        }
        $model = (new static());

        $query = $model->db(false);

        // 仅当强制删除时包含软删除数据
        if ($force) {
            $query->removeOption('soft_delete');
        }

        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [&$query]);
            $data = null;
        } elseif (is_null($data)) {
            return false;
        }

        $resultSet = $query->select($data);

        foreach ($resultSet as $result) {
            /** @var Model $result */
            $result->force($force)->delete();
        }

        return true;
    }

    /**
     * 自动删除数据
     * @return boolean
     */
    public function remove()
    {
        if(!$this->softDelete){
            return $this->force()->delete();
        }
        return $this->delete();
    }

    /**
     * 恢复被软删除记录
     * @access public
     * @param array $where 更新条件
     * @return bool
     */
    public function restore($where = []): bool
    {
        if (!$this->softDelete || false === $this->trigger('BeforeRestore')) {
            return false;
        }

        if (empty($where)) {
            $pk = $this->getPk();
            if (is_string($pk)) {
                $where[] = [$pk, '=', $this->getData($pk)];
            }
        }

        // 恢复删除
        $name = $this->getDeleteTimeField();
        $this->db(false)
            ->where($where)
            ->useSoftDelete($name, $this->getWithTrashedExp())
            ->update([$name => $this->defaultSoftDelete]);

        $this->trigger('AfterRestore');

        return true;
    }

    /**
     * 关闭全局查询
     *
     * @param array $scope
     * @return \start\Model
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
 
}
