<?php
/**
 * Created by PhpStorm.
 * User: brewlin
 * Date: 2019/8/4
 * Time: 20:44
 */

namespace App\Packet;
use App\Process\TaskProcess;
use Core\Container\Mapping\Bean;
use Core\Contract\PackerInterface;
use Log\Helper\Log;
use Process\ProcessManager;

/**
 * 投递到bucket缓存池中处理，该操作都在taskprocess中处理
 * Class Task
 * @package App\Packet
 * @Bean()
 */
class Task implements PackerInterface
{
    /**
     * @var string
     */
    private $class;
    /**
     * @var string
     */
    private $method;
    /**
     * @var array
     */
    private $arg;

    /**
     * @param string $data
     * @return $this
     * @throws \Exception
     */
    public function unpack(string $buf)
    {
        $data = json_decode($buf,true);
        if(!isset($data['class'])){
            throw new \Exception("class is not isset");
        }
        if(!isset($data['method'])){
            throw new \Exception("method is not isset");
        }
        if(!isset($data['arg'])){
            throw new \Exception("arg is not isset");
        }
        $this->setClass($data['class']);
        $this->setMethod($data['method']);
        $this->arg = $data['arg'];
        return $this;
    }

    /**
     * @param string $class
     * @param string $method
     * @param array $arg
     * @return $this
     */
    public function deliver(string $class,string $method,array $arg)
    {
        $this->setClass($class);
        $this->setMethod($method);
        $this->setArg($arg);
        $this->exec();
    }

    /**
     * @param $data
     * @return string
     */
    public function pack($data = null): string
    {
        if(empty($this->class) || empty($this->method) || empty($this->arg)){
            Log::error("rquire class | method |arg");
            return '';
        }
        $class = $this->getClass();
        $method = $this->getMethod();
        $arg = $this->getArg();
        $taskData = compact('class','method','arg');
        return json_encode($taskData);
        // TODO: Implement pack() method.
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
    public function setClass(string $class)
    {
        $this->class = $class;
    }
    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }
    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    /**
     * @return array
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param array $arg
     */
    public function setArg(array $arg)
    {
        $this->arg = $arg;
    }

    /**
     * exec the task
     */
    public  function exec()
    {
        $process = ProcessManager::getProcesses(TaskProcess::Name);
        $pack = $this->pack(null);
        if(empty($pack))return;
        $process->write($pack);
    }


}