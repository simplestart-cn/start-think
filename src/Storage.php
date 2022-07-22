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

namespace start;

use think\App;
use think\Container;

/**
 * 文件存储引擎
 * Class Storage
 * @package start
 */

abstract class Storage
{
    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 储存配置
     * @var [type]
     */
    private $config;

    /**
     * 当前引擎
     * @var [type]
     */
    private $engine;

    /**
     * Storage constructor.
     * @param App $app
     */
    public function __construct($config = null, App $app)
    {
        $this->app = $app;
        // 设置存储配置
        $this->initialize($config);
    }

    /**
     * 存储初始化
     * @param  [type] $config [description]
     * @return [type]         [description]
     */
    protected function initialize($config = null)
    {
        $this->config = $config;
        $class = ucfirst(strtolower(is_null($config) || $config['engine'] == 'local' ? 'local' : $config['engine']));
        if (class_exists($object = "start\\storage\\{$class}")) {
            $this->engine = new $object($config);
        } else {
            throw new Exception("File driver [{$class}] does not exist.");
        }
        return $this;
    }

    /**
     * 静态访问启用
     * @param string $method 方法名称
     * @param array $arguments 调用参数
     * @return mixed
     */
    public static function __callStatic($method, $arguments)
    {
        if (method_exists($class = self::instance(), $method)) {
            return call_user_func_array([$class, $method], $arguments);
        } else {
            throw new Exception("method not exists: " . get_class($class) . "->{$method}()");
        }
    }

    /**
     * 设置存储配置
     * @param  array  $config 存储配置
     * @return object         [description]
     */
    public static function instance($config = null)
    {
        $class = ucfirst(strtolower(is_null($config) ? 'local' : $config['engine']));
        if (class_exists($object = "start\\storage\\{$class}")) {
            return Container::getInstance()->make($object)->initialize($config);
        } else {
            throw new Exception("File driver [{$class}] does not exist.");
        }
    }

    /**
     * 设置上传的文件信息
     * @param string $name
     * @return mixed
     */
    public function setUploadFile($name = 'iFile')
    {
        return $this->engine->setUploadFile($name);
    }

    /**
     * 设置上传的文件信息
     * @param string $filePath
     * @return mixed
     */
    public function setUploadFileByReal($filePath)
    {
        return $this->engine->setUploadFileByReal($filePath);
    }

    /**
     * 执行文件上传
     */
    public function upload()
    {
        return $this->engine->upload();
    }

    /**
     * 执行文件删除
     * @param $fileName
     * @return mixed
     */
    public function delete($fileName)
    {
        return $this->engine->delete($fileName);
    }

    /**
     * 获取错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->engine->getError();
    }

    /**
     * 获取文件路径
     * @return mixed
     */
    public function getFileName()
    {
        return $this->engine->getFileName();
    }

    /**
     * 返回文件信息
     * @return mixed
     */
    public function getFileInfo()
    {
        return $this->engine->getFileInfo();
    }

    /**
     * 下载文件到本地
     * @param string $url 文件URL地址
     * @param boolean $force 是否强制下载
     * @param integer $expire 文件保留时间
     * @return array
     */
    public static function download($url, $force = false, $expire = 0)
    {
        try {
            $file = LocalStorage::instance();
            $name = self::getName($url, '', 'down/');
            if (empty($force) && $file->has($name)) {
                if ($expire < 1 || filemtime($file->path($name)) + $expire > time()) {
                    return $file->info($name);
                }
            }
            return $file->set($name, file_get_contents($url));
        } catch (\Exception $e) {
            return ['url' => $url, 'hash' => md5($url), 'key' => $url, 'file' => $url];
        }
    }

    /**
     * 获取文件相对名称
     * @param string $url 文件访问链接
     * @param string $ext 文件后缀名称
     * @param string $pre 文件存储前缀
     * @param string $fun 名称规则方法
     * @return string
     */
    public static function getName($url, $ext = '', $pre = '', $fun = 'md5')
    {
        if (empty($ext)) $ext = pathinfo($url, 4);
        list($xmd, $ext) = [$fun($url), trim($ext, '.\\/')];
        $attr = [trim($pre, '.\\/'), substr($xmd, 0, 2), substr($xmd, 2, 30)];
        return trim(join('/', $attr), '/') . '.' . strtolower($ext ? $ext : 'tmp');
    }

    /**
     * 根据文件后缀获取文件MINE
     * @param array|string $exts 文件后缀
     * @param array $mime 文件信息
     * @return string
     */
    public static function getMime($exts, $mime = [])
    {
        $mimes = self::mimes();
        foreach (is_string($exts) ? explode(',', $exts) : $exts as $ext) {
            $mime[] = $mimes[strtolower($ext)] ?? 'application/octet-stream';
        }
        return join(',', array_unique($mime));
    }

    /**
     * 获取所有文件的信息
     * @return array
     */
    public static function mimes(): array
    {
        static $mimes = [];
        if (count($mimes) > 0) return $mimes;
        return $mimes = include __DIR__ . '/storage/bin/mimes.php';
    }

}