<?php
/**
 * Created by PhpStorm.
 * User: brewlin
 * Date: 2019/7/1
 * Time: 23:05
 */

namespace App\Lib;


use App\Service\Dao\QueueDao;
use App\Service\Dao\RedisDao;
use App\Service\Model\Room;
use Core\Container\Container;
use Core\Container\Mapping\Bean;

/**
 * @package lib
 * @Bean()
 */
class LogicPush
{
    public function pushKeys(int $op,array $keys,$msg)
    {
        /** @var RedisDao $servers */
        $servers = \container()->get(RedisDao::class)->getServersByKeys($keys);
        $pushKeys = [];
        foreach ($keys as $i => $key){
            $server = $servers[$i];
            if(!empty($server) && empty($key))
                $pushKeys[$server][] = $key;
        }
        foreach ($pushKeys as $server => $key){
            //丢到队列里去操做，让job去处理
            \container()->get(QueueDao::class)->pushMsg($op,$server,$pushKeys[$server],$msg);
        }
    }
    public function pushMids(int $op,array $mids,$msg)
    {
        /** @var RedisDao $servers */
        $servers = \container()->get(RedisDao::class)->getKeysByMids($mids);
        $keys = [];
        foreach($servers as $key => $server){
            $keys[$server][] = $key;
        }
        foreach($keys as $server => $key){
            //丢到队列里去操做，让job去处理
            \container()->get(QueueDao::class)->pushMsg($op,$server,$key,$msg);
        }

    }

    /**
     * @param int $op
     * @param $type
     * @param $room
     * @param $msg
     * @throws \Exception
     */
    public function pushRoom(int $op,$type,$room,$msg){
        \container()
            ->get(QueueDao::class)
            ->broadcastRoomMsg($op,\bean(Room::class)
                ->encodeRoomKey($type,$room),$msg);
    }
    

}