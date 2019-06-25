<?php
/**
 * Created by PhpStorm.
 * User: brewlin
 * Date: 2019/6/23
 * Time: 18:30
 */

namespace App\Process;


use Core\Processor\ProcessorInterface;
use Process\Contract\AbstractProcess;
use Process\Process;
use Process\ProcessInterface;

/**
 * 自定义进程
 * 1.注册服务
 * 2.定时从服务中心发现服务 并刷新到本地serviceslist
 * Class DiscoveryProcess
 * @package App\Process
 */
class DiscoveryProcess extends AbstractProcess
{
    public function __construct()
    {
        $this->name = "discovery";
    }

    public function check(): bool
    {
        return true;
    }

    /**
     * 自定义子进程 执行入口
     * @param Process $process
     */
    public function run(Process $process)
    {
        provider()->select()->registerService();
        while (true){
            $services = provider()->select()->getServiceList("im-cloud-node");
            var_dump($services);
//            $this->updateServices($services);
            sleep(10);
        }
    }

    /**
     * update service list
     * @param array $services
     */
    public function updateServices(array $services)
    {
       //do sth
    }
}