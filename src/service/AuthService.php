<?php

// +----------------------------------------------------------------------
// | Simplestart Think
// +----------------------------------------------------------------------
// | 版权所有: http://www.simplestart.cn copyright 2020
// +----------------------------------------------------------------------
// | 开源协议: https://www.apache.org/licenses/LICENSE-2.0.txt
// +----------------------------------------------------------------------
// | 仓库地址: https://github.com/simplestart-cn/start-think
// +----------------------------------------------------------------------

namespace start\service;

use start\Service;
use start\AppService;
use start\extend\DataExtend;

/**
 * 系统权限服务
 * Class AuthService
 * @package app\service
 */
class AuthService extends Service
{

    public $model = 'start\model\Auth';
    
    /**
     * 构建权限
     * 并保留后台可编辑字段
     * @return [type] [description]
     */
    public function building($app = '')
    {
        // 注解权限
        $nodes    = NodeService::instance()->getAll($app, true);
        $authNode = array();
        foreach ($nodes as $item) {
            if($item['issuper'] || $item['isadmin'] || $item['islogin']){
                continue;
            }
            $auth              = $item['isauth'];
            $temp              = array();
            $temp['app']       = empty($item['app']) ? $app : $item['app'];
            $temp['name']      = str_replace('/', '_', $item['node']);
            $temp['icon']      = $auth['icon'] ?? '';
            $temp['sort']      = $auth['sort'] ?? 100;
            $temp['title']     = $auth['title'] ?? $item['title'];
            $temp['status']    = $auth['status'] ?? 1;
            $temp['params']    = $auth['params'] ?? '';
            $temp['node']      = $item['node'];
            $temp['is_super']  = $auth['is_super'] ?? $item['issuper'];
            $temp['is_admin']  = $auth['is_admin'] ?? $item['isadmin'];
            $temp['is_open']   = $auth['is_open'] ?? $item['isopen'];
            $temp['is_auth']   = $auth['is_auth'] ?? (boolean)$auth;
            $temp['is_menu']   = $auth['is_menu'] ?? $item['ismenu'];
            $temp['parent']    = $auth['parent'] ?? $item['parent'];
            $temp['path']      = $auth['path'] ?? '/' . str_replace('_', '/', $item['node']);
            $temp['view']      = $auth['view'] ?? ($item['isview'] ? '/'.str_replace('_', '/', $item['node']) : '');
            $temp['redirect']  = $auth['redirect'] ?? '';
            $temp['hidden']    = $auth['hidden'] ?? 0;
            $temp['cache']     = $auth['cache'] ?? 1;
            $authNode[$item['node']] = $temp;
        }

        // 自定义权限
        $appInfo = AppService::getPackInfo($app);
        if ($appInfo) {
            $authNode[$app]['icon']  = $appInfo['icon'] ?? '';
            $authNode[$app]['title'] = $appInfo['title'] ?? $appInfo['name'];
            if (isset($appInfo['auth'])) {
                foreach ($appInfo['auth'] as &$extend) {$extend['app'] = $app;}
                $authExtend = array_combine(array_column($appInfo['auth'], 'node'), array_values($appInfo['auth']));
                $authNode   = array_merge($authNode, $authExtend);
            }
        }
        // 无权限节点
        if(empty($authNode) || (count($authNode) == 1 && !isset($authNode[0]['node']))){
            return true;
        }
        // 格式化权限
        $dbNodes   = $this->model->select()->hidden(['create_time','update_time'])->toArray();
        $dbNodes   = array_combine(array_column($dbNodes, 'node'), array_values($dbNodes));
        $dbKeys  = array_combine(array_column($dbNodes, 'id'), array_values($dbNodes));
        foreach ($authNode as &$auth) {
            if(!isset($auth['node'])) continue;
            if(isset($dbNodes[$auth['node']])){
                $auth['id'] = $dbNodes[$auth['node']]['id'];
            }
            if(!empty($auth['parent']) && isset($dbNodes[$auth['parent']])){
                $parent         = $dbNodes[$auth['parent']];
                $auth['pid']    = $parent['id'];
                $auth['parent'] = $parent['node'];
                if(!isset($authNode[$parent['node']])){
                    if($parent['pid'] && isset($dbKeys[$parent['pid']])){
                        $parent['parent'] = $dbKeys[$parent['pid']]['node'];
                    }
                    $authNode[$parent['node']] = $parent;
                }
            }
        }
        // 保存权限
        $tree = DataExtend::arr2tree($authNode, 'node', 'parent', 'children');
        $auths = $this->saveBuilding($tree, 0);
        return $auths;
    }

    /**
     * 更新权限信息
     * @param  [type] $nodes [description]
     * @return [type]        [description]
     */
    private function saveBuilding($nodes = [], $pid = 0)
    {
        $auths = array();
        foreach ($nodes as $key => &$data) {
            if ($pid === 0 && empty($data['children'])) {
                continue;
            }
            $temp        = $data;
            $temp['pid'] = $pid;
            unset($temp['parent']);
            unset($temp['children']);
            if(isset($temp['create_time'])){
                unset($temp['create_time']);
            }
            if(isset($temp['update_time'])){
                unset($temp['update_time']);
            }
            if (isset($temp['id'])) {
                $model = $this->model->find($temp['id']);
                $model->where(['id' => $temp['id']])->save($temp);
            } else {
                $model = new $this->model;
                $model->save($temp);
            }
            if ($model->id && isset($data['children']) && count($data['children']) > 0) {
                $temp['children'] = $this->saveBuilding($data['children'], $model->id);
            }
            $auths[] = $temp;
        }
        return $auths;
    }

}
