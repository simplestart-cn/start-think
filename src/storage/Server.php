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

namespace start\storage;

use think\Request;
use think\Exception;

/**
 * 存储引擎抽象类
 * Class server
 * @package app\common\library\storage\drivers
 */
abstract class Server
{
    /* @var $file \think\File */
    protected $file;
    protected $error;
    protected $fileName;
    protected $fileInfo;

    // 是否为内部上传
    protected $isInternal = false;

    /**
     * 构造函数
     * Server constructor.
     */
    protected function __construct()
    {
    }

    /**
     * 设置上传的文件信息
     * @param string $name
     * @throws Exception
     */
    public function setUploadFile($name)
    {
        // 接收上传的文件
        $this->file = Request::instance()->file($name);
        if (empty($this->file)) {
            throw new Exception('未找到上传文件的信息');
        }
        // 文件信息
        $this->fileInfo = $this->file->getInfo();
        // 生成保存文件名
        $this->fileName = $this->buildSaveName();
    }

    /**
     * 设置上传的文件信息
     * @param string $filePath
     */
    public function setUploadFileByReal($filePath)
    {
        // 设置为系统内部上传
        $this->isInternal = true;
        // 文件信息
        $this->fileInfo = [
            'name' => basename($filePath),
            'size' => filesize($filePath),
            'tmp_name' => $filePath,
            'error' => 0,
        ];
        // 生成保存文件名
        $this->fileName = $this->buildSaveName();
    }

    /**
     * 文件上传
     * @return mixed
     */
    abstract protected function upload();

    /**
     * 文件删除
     * @param $fileName
     * @return mixed
     */
    abstract protected function delete($fileName);

    /**
     * 返回上传后文件路径
     * @return mixed
     */
    abstract public function getFileName();

    /**
     * 返回文件信息
     * @return mixed
     */
    public function getFileInfo()
    {
        return $this->fileInfo;
    }

    protected function getRealPath()
    {
        return $this->getFileInfo()['tmp_name'];
    }

    /**
     * 返回错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 生成保存文件名
     */
    private function buildSaveName()
    {
        // 要上传图片的本地路径
        $realPath = $this->getRealPath();
        // 扩展名
        $ext = pathinfo($this->getFileInfo()['name'], PATHINFO_EXTENSION);
        // 自动生成文件名
        return date('YmdHis') . substr(md5($realPath), 0, 5)
            . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT) . ".{$ext}";
    }

}
