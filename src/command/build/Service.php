<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace start\command\build;

use start\command\Build;

class Service extends Build
{
    protected $type = "Service";

    protected function configure()
    {
        parent::configure();
        $this->setName('build:service')
            ->setDescription('Create a new Service class');
    }

    protected function getStub(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'service.stub';
    }

    protected function getClassPath(string $namespace, string $classname): string
    {
        return str_replace('.php', 'Service.php', parent::getClassPath($namespace, $classname));
    }

}
