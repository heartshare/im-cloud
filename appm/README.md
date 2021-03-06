应用节点
==============

## @[cloud](./cloud)
> 注册中心节点
完成功能开发
```php
cd cloud
composer install
```
## 进度 (test version)
- 完成grpc接口
- 完成tcp  websocket 注册链接
- 完成主流程

## @[job](./job)
> 消费中心节点
完成功能开发
```php
cd job
composer install
```
### 进度 (test version)
- 完成了队列的消费 多进程消费
- 完成了grpc cloud的调用

## @[logic](./logic)
> 业务中心节点
```php
cd logic
composer install
```
### 进度 (test version)
- 完成了http api
    -   `完成并发压测单点推送`
- 完成了grpc api
- 完成了注册发现与服务调用
- 完成了队列的生产 使用rabbitmq

## 终端处理
![](../resource/console.png)
### process
![](../resource/process.png)
### start
![](../resource/start.png)
### 监控
![](../resource/monitor.png)
### make start
![](../resource/makestart.png)
### make stop
![](../resource/makestop.png)

