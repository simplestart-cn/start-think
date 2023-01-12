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

use PhpZip\ZipFile;
use PhpZip\Exception\ZipException;
use start\extend\HttpExtend;
use start\service\AuthService;
use start\service\ConfigService;

/**
 * App管理器
 */
class AppManager extends Service
{

    public $model = 'start\model\App';

    /**
     * 代码地址
     * @var string
     */
    protected $api;

    /**
     * 项目根目录
     * @var string
     */
    protected $path;

    /**
     * 当前版本号
     * @var string
     */
    protected $version;

    /**
     * 文件规则
     * @var array
     */
    protected $rules = [];

    /**
     * 忽略规则
     * @var array
     */
    protected $ignore = [];

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        // 服务地址
        $this->api = $this->app->config->get('cms.api');
        // 框架目录
        $this->path = strtr(root_path(), '\\', '/');
        // 框架版本
        $this->version = $this->app->config->get('cms.version');
        if (empty($this->version)) {
            $this->version = 'last';
        }
        return $this;
    }

    /**
     * 获取应用列表
     * @param  array  $filter [description]
     * @param  array  $order  [description]
     * @return [type]         [description]
     */
    public static function getPage($filter = [], $order = [], $with = null)
    {
        $origin     = array();
        $installed  = self::getInstalled();
        $downloaded = self::getDownloaded();
        foreach ($downloaded as $name => $app) {
            if (!isset($app['version'])) {
                throw_error('app.json error: ' . $app['name']);
            }
            if (isset($installed[$name])) {
                $last              = $installed[$name];
                $last['installed'] = 1;
                if ($last['version'] !== $app['version']) {
                    // 可更新的
                    $last['updateable']   = 1;
                    $last['last_version'] = $app['version'];
                } else {
                    $last['updateable'] = 0;
                }
                $installed[$name] = $last;
            } else {
                // 未安装的
                $app['installed']  = 0;
                $app['updateable'] = 0;
                $installed[$name]  = $app;
            }
        }
        $origin = array_values($installed);
        // 暂时模拟分页
        return array(
            'current_page' => 1,
            'data'         => $origin,
            'last_page'    => 1,
            'per_page'     => count($origin),
            'total'        => count($origin),
        );
    }

    /**
     * 更新信息
     * @param  array  $input [description]
     * @return [type]        [description]
     */
    public static function update($input = [])
    {
        $model = self::getInfo(['name' => $input['name']]);
        if (!$model) {
            throw_error(lang('app_not_installed'));
        }
        return $model->save($input);
    }

    /**
     * 升级配置
     * @param  array  $name  [description]
     * @return boolea        [description]
     */
    public static function upgradeConfig($name)
    {
        $app = self::getPackInfo($name);
        if (isset($app['config']) && count($app['config'])) {
            $config = $app['config'];
            foreach ($config as &$conf) {
                $conf['app']        = strtolower($conf['app'] ?? $app['name']);
                $conf['app_title']  = strtolower($conf['app_title'] ?? $app['title']);
                $where['app']       = $conf['app'];
                $where['field']     = $conf['field'];
                $model = ConfigService::getInfo($where);
                if ($model && $model->id) {
                    unset($conf['value']);
                    $model->save($conf);
                } else {
                    ConfigService::create($conf);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 下载(待完成)
     * @param  [type] $app [description]
     * @return [type]      [description]
     */
    public static function download($name, $version)
    {
        $service = self::instance();
        $tempDir = self::getBackupDir();
        $tmpFile = $tempDir . $name . ".zip";
        try {
            $api = $service->api . '/appstore/download';
            $params = [
                'app' => $name,
                'app_version' => $version,
                'cms_version' => $service->version,
            ];
            $options = [
                'timeout'         => 30,
                'connect_timeout' => 30,
                'verify'          => false,
                'http_errors'     => false,
                'headers'         => [
                    'X-REQUESTED-WITH' => 'XMLHttpRequest',
                    'Referer'          => dirname(request()->root(true))
                ]
            ];
            $response = HttpExtend::get($api, $params, $options);
            $response = json_decode($response, true);
            if($response['code'] !== 0){
                throw_error($response['msg']);
            }
            $content = $response['data'];
        } catch (\Exception $e) {
            throw_error($e->getMessage());
        }

        if ($write = fopen($tmpFile, 'w')) {
            fwrite($write, $content);
            fclose($write);
            return $tmpFile;
        }
        throw_error(lang('No permission to write temporary files'));
    }

    /**
     * 安装
     * @param  [type] $name [description]
     * @return [type]      [description]
     */
    public static function install($name)
    {
        $app = self::getPackInfo($name);
        $path = base_path() . $name;
        foreach ($app as $key => $value) {
            if (stripos($key, '-') !== false) {
                $app[str_replace('-', '_', $key)] = $value;
                unset($app[$key]);
            }
        }
        if (self::getInfo(['name' => $name])) {
            throw_error(lang('app_already_exist'));
        }
        $model = self::model();
        self::startTrans();
        try {
            // 执行安装脚本
            $installer = $path . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install.php';
            if (file_exists($installer)) {
                require_once $installer;
            }
            // 添加默认配置
            if (isset($app['config']) && count($app['config'])) {
                $config = $app['config'];
                foreach ($config as &$conf) {
                    $conf['app']       = strtolower($conf['app'] ?? $app['name']);
                    $conf['app_title'] = strtolower($conf['app_title'] ?? $app['title']);
                }
                ConfigService::model()->saveAll($config);
            }
            // 构建权限菜单
            AuthService::instance()->building($app['name']);
            // 添加应用记录
            if ($name != 'core') {
                $model->save($app);
            }
            self::startCommit();
            return $model;
        } catch (Exception $e) {
            self::startRollback();
            throw_error($e->getMessage());
            return false;
        }
    }

    /**
     * 升级(待完成)
     * @param  [type] $app [description]
     * @return [type]      [description]
     */
    public static function upgrade($name, $version)
    {
        $app = self::getInfo(['name' => $name]);
        $path = base_path() . $name . DIRECTORY_SEPARATOR;
        if ($app['status']) {
            throw_error(lang('app_is_runing'));
        }
        // 下载应用
        $tmpFile = self::download($name, $version);
        // 备份应用
        self::backup($name);
        // 删除旧版
        self::_removeFolder($path);
        try {
            // 解压应用文件
            self::unpack($name);
            // 执行升级脚本
            $upgrader = $path . 'installer' . DIRECTORY_SEPARATOR . 'upgrade.php';
            if (file_exists($upgrader)) {
                require_once $upgrader;
            }
            // 升级配置信息
            self::upgradeConfig($name);
            // 刷新权限菜单
            AuthService::instance()->building($app['name']);
            // 更新应用信息
            $info = self::getPackInfo($name);
            $app->save($info);
            return $info;
        } catch (\Exception $e) {
            throw_error($e->getMessage());
        } finally {
            // 移除临时文件
            @unlink($tmpFile);
        }
    }

    /**
     * 卸载(待完成)
     * @param  [type] $app [description]
     * @return [type]      [description]
     */
    public static function uninstall($name)
    {
        $app = self::getPackInfo($name);
        $path = base_path() . $name;

        $model = self::getInfo(['name' => $name]);
        if (!$model) {
            throw_error(lang('app_does_not_exist'));
        }

        self::startTrans();
        try {
            // 执行卸载脚本
            $uninstaller = $path . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'uninstall.php';
            if (file_exists($uninstaller)) {
                require_once $uninstaller;
            }
            // 删除权限菜单
            AuthService::model()->where(['app' => $name])->delete();
            // 删除应用配置
            ConfigService::model()->where(['app' => $name])->delete();
            // 删除应用记录
            $model->remove();
            self::startCommit();
            return $model;
        } catch (Exception $e) {
            self::startRollback();
            throw_error($e->getMessage());
            return false;
        }
    }

    /**
     * 删除安装包
     * @param  string  $name [description]
     * @return [type]        [description]
     */
    public static function remove($name, $force = false)
    {
        // 删除对应数据表
        // ....
        // ...
        // 删除应用记录
        self::model()->where(['name' => $name])->delete();
        // 删除应用目录
        $path = base_path() . $name . DIRECTORY_SEPARATOR;
        return self::_removeFolder($path);
    }

    /**
     * 删除文件或文件夹
     * @param  string $path [description]
     * @return [type]       [description]
     */
    private static function _removeFolder($path)
    {
        if (is_dir($path)) {
            if (!$handle = @opendir($path)) {
                throw_error($handle);
            }
            while (false !== ($file = readdir($handle))) {
                if ($file !== "." && $file !== "..") {
                    //排除当前目录与父级目录
                    $file = $path . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($file)) {
                        self::_removeFolder($file);
                        //目录清空后删除空文件夹
                        @rmdir($file . DIRECTORY_SEPARATOR);
                    } else {
                        @unlink($file);
                    }
                }
            }
            try {
                return rmdir($path);
            } catch (\Exception $e) {
                $msg = explode(': ', $e->getMessage())[1];
                throw_error($msg);
            }
        }
        if (is_file($path)) {
            try {
                return unlink($path);
            } catch (\Exception $e) {
                $msg = explode(': ', $e->getMessage())[1];
                throw_error($msg);
            }
        }
        return true;
    }

    /**
     * 是否已下载
     * @param  string $app [description]
     * @return array      [description]
     */
    public static function isDownload($name)
    {
        $apps = self::getDownloaded();
        return !!isset($apps[$name]);
    }

    /**
     * 是否已安装
     * @param  [type] $app [description]
     * @return [type]      [description]
     */
    public static function isInstall($name)
    {
        $apps = self::getInstalled();
        return !!isset($apps[strtolower($name)]);
    }

    /**
     * 是否已启用
     * @param  [type] $app [description]
     * @return [type]      [description]
     */
    public static function isActive($name)
    {
        $apps = self::getInstalled();
        if (isset($apps[strtolower($name)])) {
            return !!$apps[strtolower($name)]['status'];
        }
        return false;
    }

    /**
     * 获取包信息
     * @param  string $name [description]
     * @return [type]       [description]
     */
    public static function getPackInfo($name)
    {
        if ($name === 'core') {
            $path         = root_path() . $name . DIRECTORY_SEPARATOR . 'app.json';
        } else {
            $path         = base_path() . $name . DIRECTORY_SEPARATOR . 'app.json';
        }
        if (!is_file($path)) {
            return false;
        }
        $info         = json_decode(file_get_contents($path), true);
        $info['name'] = strtolower($name);
        return $info;
    }

    /**
     * 获取所有应用名称
     * @return [type] [description]
     */
    public static function getApps()
    {
        $path = base_path();
        $apps = [];
        foreach (glob("{$path}*") as $item) {
            if (is_dir($item)) {
                $item = explode(DIRECTORY_SEPARATOR, $item);
                array_push($apps, end($item));
            }
        }
        return $apps;
    }

    /**
     * 获取可用的
     * @return [type] [description]
     */
    public static function getActive()
    {
        return self::model()->where('status', 1)->column('name');
    }

    /**
     * 获取已安装
     * @return [type] [description]
     */
    private static function getInstalled()
    {
        $data = self::model()->select()->toArray();
        return array_combine(array_column($data, 'name'), array_values($data));
    }

    /**
     * 获取已下载
     * @return [type] [description]
     */
    private static function getDownloaded()
    {
        $apps     = [];
        $basePath = base_path();
        foreach (self::_scanApps($basePath) as $file) {
            if (preg_match("|(\w+)/app.json$|i", $file, $matches)) {
                list($path, $name) = $matches;
                $info              = json_decode(file_get_contents($basePath . DIRECTORY_SEPARATOR . $path), true);
                $info['name']      = strtolower($name);
                $apps[]            = $info;
            }
        }
        $apps = array_combine(array_column($apps, 'name'), array_values($apps));
        return $apps;
    }

    /**
     * 获取备份目录
     * @return string
     */
    private static function getBackupDir()
    {
        $dir = runtime_path() . 'backup';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * 应用备份
     * @param  string $name
     * @return boolean
     */
    public static function backup($name)
    {
        $appPath = base_path() . $name;
        $backupPath = self::getBackupDir();
        $file = $backupPath . $name . '-backup-' . date("YmdHis") . '.zip';
        $zip = new ZipFile();
        try {
            $zip->addDirRecursive($appPath)
                ->saveAsFile($file)
                ->close();
        } catch (ZipException $e) {
            throw_error($e->getMessage());
        } finally {
            $zip->close();
        }
        return true;
    }

    /**
     * 解压文件
     *
     * @param [type] $name
     * @return void
     */
    private static function unpack($name)
    {
        if (!$name) {
            throw new Exception('Invalid parameters');
        }
        $appPath = base_path() . $name;
        $tempDir = self::getBackupDir();
        $tempFile = $tempDir . $name . '.zip';
        if (!is_dir($appPath)) {
            @mkdir($appPath, 0755);
        }
        $zip = new ZipFile();
        try {
            $zip->openFile($tempFile);
        } catch (ZipException $e) {
            $zip->close();
            throw_error('Unable to open the zip file');
        }
        try {
            $zip->extractTo($appPath);
        } catch (ZipException $e) {
            throw_error('Unable to extract the file');
        } finally {
            $zip->close();
        }
        return $appPath;
    }



    /**
     * 获取本地应用
     * @param string $path 扫描目录
     * @param string $ext 文件后缀
     * @return array
     */
    private static function _scanApps($path, $ext = 'json')
    {
        $data = [];
        foreach (glob("{$path}*") as $item) {
            if (is_dir($item) && stripos($item, 'node_modules') === false) {
                $data = array_merge($data, self::_scanApps("{$item}/"));
            } elseif (is_file($item) && pathinfo($item, PATHINFO_EXTENSION) === $ext) {
                $data[] = strtr($item, '\\', '/');
            }
        }
        return $data;
    }

    /**
     * 同步更新文件
     * @param array $file
     * @return array
     */
    public function fileSynchronization($file)
    {
        if (in_array($file['type'], ['add', 'mod'])) {
            if ($this->downloadFile(encode($file['name']))) {
                return [true, $file['type'], $file['name']];
            } else {
                return [false, $file['type'], $file['name']];
            }
        } elseif (in_array($file['type'], ['del'])) {
            $real = $this->path . $file['name'];
            if (is_file($real) && unlink($real)) {
                $this->removeEmptyDirectory(dirname($real));
                return [true, $file['type'], $file['name']];
            } else {
                return [false, $file['type'], $file['name']];
            }
        }
    }

    /**
     * 下载更新文件内容
     * @param string $encode
     * @return boolean|integer
     */
    private function downloadFile($encode)
    {
        $service = self::instance();
        $result = json_decode(HttpExtend::get("{$service->api}/update/get&encode={$encode}"), true);
        if (empty($result['code'])) {
            return false;
        }

        $filename = $this->path . decode($encode);
        file_exists(dirname($filename)) || mkdir(dirname($filename), 0755, true);
        return file_put_contents($filename, base64_decode($result['data']['content']));
    }

    /**
     * 清理空目录
     * @param string $path
     */
    private function removeEmptyDirectory($path)
    {
        if (is_dir($path) && count(scandir($path)) === 2 && rmdir($path)) {
            $this->removeEmptyDirectory(dirname($path));
        }
    }

    /**
     * 获取文件差异数据
     * @param array $rules 文件规则
     * @param array $ignore 忽略规则
     * @return array
     */
    public function generateDifference($rules = [], $ignore = [])
    {
        $service = self::instance();
        list($this->rules, $this->ignore, $data) = [$rules, $ignore, []];
        $response = HttpExtend::post("{$service->api}?/appstore/upgrade/tree", [
            'rules' => serialize($this->rules), 'ignore' => serialize($this->ignore),
        ]);
        $result = json_decode($response, true);
        if (!empty($result['code'])) {
            $new = $this->getAppFiles($result['data']['rules'], $result['data']['ignore']);
            foreach ($this->generateDifferenceContrast($result['data']['list'], $new['list']) as $file) {
                if (in_array($file['type'], ['add', 'del', 'mod'])) {
                    foreach ($this->rules as $rule) {
                        if (stripos($file['name'], $rule) === 0) {
                            $data[] = $file;
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 两二维数组对比
     * @param array $serve 线上文件列表信息
     * @param array $local 本地文件列表信息
     * @return array
     */
    private function generateDifferenceContrast(array $serve = [], array $local = [])
    {
        // 数据扁平化
        list($_serve, $_local, $_new) = [[], [], []];
        foreach ($serve as $t) {
            $_serve[$t['name']] = $t;
        }

        foreach ($local as $t) {
            $_local[$t['name']] = $t;
        }

        unset($serve, $local);
        // 线上数据差异计算
        foreach ($_serve as $t) {
            isset($_local[$t['name']]) ? array_push($_new, [
                'type' => $t['hash'] === $_local[$t['name']]['hash'] ? null : 'mod', 'name' => $t['name'],
            ]) : array_push($_new, ['type' => 'add', 'name' => $t['name']]);
        }

        // 本地数据增量计算
        foreach ($_local as $t) {
            if (!isset($_serve[$t['name']])) {
                array_push($_new, ['type' => 'del', 'name' => $t['name']]);
            }
        }

        unset($_serve, $_local);
        usort($_new, function ($a, $b) {
            return $a['name'] !== $b['name'] ? ($a['name'] > $b['name'] ? 1 : -1) : 0;
        });
        return $_new;
    }

    /**
     * 获取文件信息列表
     * @param array $rules 文件规则
     * @param array $ignore 忽略规则
     * @param array $data 扫描结果列表
     * @return array
     */
    public function getAppFiles(array $rules, array $ignore = [], array $data = [])
    {
        // 扫描规则文件
        foreach ($rules as $key => $rule) {
            $name = strtr(trim($rule, '\\/'), '\\', '/');
            $data = array_merge($data, $this->scanFiles("{$this->path}{$name}"));
        }
        // 清除忽略文件
        foreach ($data as $key => $item) {
            foreach ($ignore as $ingore) {
                if (stripos($item['name'], $ingore) === 0) {
                    unset($data[$key]);
                }
            }
        }

        return ['rules' => $rules, 'ignore' => $ignore, 'list' => $data];
    }

    /**
     * 获取目录文件列表
     * @param string $path 待扫描的目录
     * @param array $data 扫描结果
     * @return array
     */
    private function scanFiles($path, $data = [])
    {
        if (file_exists($path)) {
            if (is_dir($path)) {
                foreach (scandir($path) as $sub) {
                    if (strpos($sub, '.') !== 0) {
                        if (is_dir($temp = "{$path}/{$sub}")) {
                            $data = array_merge($data, $this->scanFiles($temp));
                        } else {
                            array_push($data, $this->getFileInfo($temp));
                        }
                    }
                }
            } else {
                return [$this->getFileInfo($path)];
            }
        }

        return $data;
    }

    /**
     * 获取指定文件信息
     * @param string $filename
     * @return array
     */
    private function getFileInfo($filename)
    {
        return [
            'name' => str_replace($this->path, '', $filename),
            'hash' => md5(preg_replace('/\s+/', '', file_get_contents($filename))),
        ];
    }
}
