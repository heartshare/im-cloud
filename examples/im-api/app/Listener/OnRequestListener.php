<?php
/**
 * Created by PhpStorm.
 * User: brewlin
 * Date: 2020/1/16 0016
 * Time: 下午 2:45
 */

namespace App\Listener;

use App\Lib\UserEnum;
use App\Services\UserCacheService;
use Core\Container\Mapping\Bean;
use Core\Context\Context;
use Core\Http\Request\Request;
use Core\Http\Response\Response;

/**
 * Class OnRequestListener
 * @package App\Listener
 * @Bean()
 */
class OnRequestListener
{
    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function onRequest(Request $request, Response $response): bool
    {
        if(in_array($request->getUriPath(),['/login','/register']))return false;

        //从请求上下文获取 全局 request() response()
        $headerToken = $request->getHeaderLine('token');
        $requestToken = $request->input('token');
        $token = $headerToken ?? $requestToken;
        if(empty($token)){
            $response->withStatus(404)->withContentType('application/json')
                     ->withContent(json_encode(["code" => 0,"msg" => "token异常"]))->end();
            return true;
        }
        /** @var UserCacheService $usercacehe */
        $usercacehe = bean(UserCacheService::class);
        $user = $usercacehe->getUserByToken($token);

        if(empty($user)){
            $response->withStatus(404)->withContentType('application/json')
                ->withContent(json_encode(["code" => 0,"msg" => "token异常"]))->end();
            return true;
        }
        $user['fd'] = $usercacehe->getFdByNum($user['number']);
        Context::withValue(UserEnum::User,$token);
        return false;
    }
}