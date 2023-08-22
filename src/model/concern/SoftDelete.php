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

declare (strict_types = 1);

namespace start\model\concern;

use start\Model;
use start\Query;

/**
 * 数据软删除
 * @mixin Model
 * @method $this withTrashed()
 * @method $this onlyTrashed()
 */
trait SoftDelete
{
    /**
     * 判断当前实例是否被软删除
     * @access public
     * @return bool
     */
    public function trashed(): bool
    {
        $field = $this->getDeleteTimeField();

        if ($this->softDelete && !empty($this->getOrigin($field))) {
            return true;
        }

        return false;
    }

    /**
     * 全局查询软删除数据
     * @param BaseQuery $query
     * @return void
     */
    public function scopeWithTrashed(Query $query)
    {
        $query->removeOption('soft_delete');
    }

    /**
     * 仅查询软删除数据
     * @param BaseQuery $query
     * @return void
     */
    public function scopeOnlyTrashed(Query $query)
    {
        if ($this->softDelete) {
            $field = $this->getDeleteTimeField(true);
            $query->useSoftDelete($field, $this->getWithTrashedExp());
        }
    }

    /**
     * 获取软删除数据的查询条件
     * @access protected
     * @return array
     */
    protected function getWithTrashedExp(): array
    {
        return is_null($this->defaultSoftDelete) ? ['notnull', ''] : ['<>', $this->defaultSoftDelete];
    }

    /**
     * 获取软删除字段
     * @access protected
     * @param bool $read 是否查询操作 写操作的时候会自动去掉表别名
     * @return string|false
     */
    protected function getDeleteTimeField(bool $read = false)
    {
        $name = property_exists($this, 'deleteTime') && isset($this->deleteTime) ? $this->deleteTime : 'delete_time';
        
        if (false === $name) {
            return false;
        }

        if (false === strpos($name, '.')) {
            $name = '__TABLE__.' . $name;
        }

        if (!$read && strpos($name, '.')) {
            $array = explode('.', $name);
            $name = array_pop($array);
        }

        return $name;
    }

    /**
     * 查询的时候默认排除软删除数据
     * @access protected
     * @param Query $query
     * @return void
     */
    protected function withNoTrashed(Query $query): void
    {
        $field = $this->getDeleteTimeField(true);
        if ($this->softDelete &&  $field) {
            $condition = is_null($this->defaultSoftDelete) ? ['null', ''] : ['=', $this->defaultSoftDelete];
            $query->useSoftDelete($field, $condition);
        }
    }
}
