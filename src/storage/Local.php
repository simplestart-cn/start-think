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

/**
 * 本地文件驱动
 * Class Local
 * @package app\common\library\storage\drivers
 */
class Local extends Server
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 上传图片文件
     * @return array|bool
     */
    public function upload()
    {
        return $this->isInternal ? $this->uploadByInternal() : $this->uploadByExternal();
    }

    /**
     * 外部上传(指用户上传,需验证文件类型、大小)
     * @return bool
     */
    private function uploadByExternal()
    {
        // 上传目录
        $uplodDir = WEB_PATH . 'uploads';
        // 验证文件并上传
        $info = $this->file->validate([
            'size' => 4 * 1024 * 1024,
            'ext' => 'jpg,jpeg,png,gif'
        ])->move($uplodDir, $this->fileName);
        if (empty($info)) {
            $this->error = $this->file->getError();
            return false;
        }
        return true;
    }

    /**
     * 内部上传(指系统上传,信任模式)
     * @return bool
     */
    private function uploadByInternal()
    {
        // 上传目录
        $uplodDir = WEB_PATH . 'uploads';
        // 要上传图片的本地路径
        $realPath = $this->getRealPath();
        if (!rename($realPath, "{$uplodDir}/$this->fileName")) {
            $this->error = 'upload write error';
            return false;
        }
        return true;
    }

    /**
     * 删除文件
     * @param $fileName
     * @return bool|mixed
     */
    public function delete($fileName)
    {
        // 文件所在目录
        $filePath = WEB_PATH . "uploads/{$fileName}";
        return !file_exists($filePath) ?: unlink($filePath);
    }

    /**
     * 返回文件路径
     * @return mixed
     */
    public function getFileName()
    {
        return $this->fileName;
    }

}
