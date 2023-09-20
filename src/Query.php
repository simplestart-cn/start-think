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
/**
 * 自定义查询基类
 * Class Query
 * @package start
 */
class Query extends \think\db\Query
{

    /**
     * 快速查询，支持操作符查询及关联表查询
     * @access public
     * @param  mixed  $condition 查询条件
     * @param  string $index     索引（唯一）
     * @return $this
     *
     * condition 结构支持
     * $condition = 1;
     * $condition = [
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
    public function filter($condition, $index = null)
    {
        if (empty($condition)) {
            return $this;
        }
        if ($condition instanceof Closure){
            if ($index) {
                $this->options['filter'][$index] = $condition;
            } else {
                $this->options['filter'][] = $condition;
            }
            return $this;
        }
        $query = $this;     // 查询对象
        $filter = [];       // 查询条件
        $hasModel = [];     // 已关联模型
        $hasWhere = false;  // 是否关联查询
        $hasWhereOr = [];   // 关联OR查询
        $hasWhereAnd = [];  // 关联AND查询

        
        if (!is_array($condition)) {
            return $query->where($condition);
        } else if (count($condition) > 0) {
            // 数据字典
            $tableFields = $this->getTableFields();
            // 分割查询
            foreach ($condition as $key => $value) {
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
                                $query = $query->model->hasWhere($model, []);
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
                    $relateTable = $this->model->$model()->getName();
                    if (in_array($model, $hasModel)) {
                        $query = $this->parseFilter($query, $condition, $relateTable);
                    } else {
                        array_push($hasModel, $model);
                        $query = $query->model->hasWhere($model, $this->parseFilter($query, $condition, $relateTable));
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
                                    $relateTable = $that->model->$model()->getName();
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
        if(is_array($value)){
            $opera = 'in';
        }
        if(stripos($key, '@') !== false){
            list($key, $opera) = explode('@', $key);
        }
        if ($opera === 'like' || $opera === 'not like') {
            $value = stripos($value, '%') === false ? '%' . $value . '%' : $value;
        }
        return $logic === 'AND' ? $query->where($table . $key, $opera, $value) : $query->whereOr($table . $key, $opera, $value);   
    }
}
