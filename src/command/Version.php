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

namespace start\command;

use start\Command;
use think\console\Input;
use think\console\Output;

/**
 * 框架版本号指令
 * Class Version
 * @package start\command
 */
class Version extends Command
{
    protected function configure()
    {
        $this->setName('start:version');
        $this->setDescription("Library and ThinkPHP Version for StartCloud");
    }

    /**
     * @param Input $input
     * @param Output $output
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('start-library ' . $this->version());
        $output->writeln('think-php ' . $this->app->version());
    }
}