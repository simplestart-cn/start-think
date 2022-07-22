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

namespace start\command\build;

use start\command\Build;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class Cms extends Build
{
    
    /**
     * 应用基础目录
     * @var string
     */
    protected $basePath;

    protected $type = "CMS";

    protected function getStub(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('build:cms')
            ->addOption('apidoc', null, Option::VALUE_NONE, 'Generate with apidoc config.')
            ->addOption('plain', null, Option::VALUE_NONE, 'Generate an empty controller class.')
            ->setDescription('Build Controller-Model-Service');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->input = $input;
        $this->output = $output;
        $name = trim($input->getArgument('name'));

        $this->buildController($name);

        $this->buildModel($name);

        $this->buildService($name);
        
    }

    protected function buildController($name)
    {
        $controller = (new Controller);
        $controller->input = $this->input;
        $controller->output = $this->output;

        $stub = $controller->getStub();

        $appname = $this->getAppName($name);

        $namespace = $this->getNamespace($name, 'controller');

        $classname = $this->getClassName($name);

        $classpath = $this->getClassPath($namespace, $classname);

        $this->buildClass($appname, $namespace, $classname, $classpath, $stub);
    }

    protected function buildModel($name)
    {
        $stub = (new Model)->getStub();

        $appname = $this->getAppName($name);

        $namespace = $this->getNamespace($name, 'model');

        $classname = $this->getClassName($name);

        $classpath = $this->getClassPath($namespace, $classname);

        $this->buildClass($appname, $namespace, $classname, $classpath, $stub);
    }

    protected function buildService($name)
    {
        $stub = (new Service)->getStub();

        $appname = $this->getAppName($name);

        $namespace = $this->getNamespace($name, 'service');

        $classname = $this->getClassName($name);

        $classpath = str_replace('.php', 'Service.php', $this->getClassPath($namespace, $classname));

        $this->buildClass($appname, $namespace, $classname, $classpath, $stub);
    }
}
