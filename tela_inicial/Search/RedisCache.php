<?php
namespace spotify\tela_inicial\Search;

class RedisCache{
    private $redis;
    private $ttl;

    public function __construct($ttl = 3600){
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->ttl = $ttl;
    }

    public function get($key){
        $startTime = microtime(true);
        try{
            $data = $this->redis->get($key);
            if($data !== false){
                $data = json_decode($data, true);
                return [
                    'data' => $data,
                    'debug' => "Cache retrieved for $key in " . (microtime(true) - $startTime) . " seconds"
                ];
            }
        }catch (\RedisException $e){
            error_log("Redis get error for $key: " . $e->getMessage());
        }
        return [
            'data' => null,
            'debug' => "Cache miss for $key in " . (microtime(true) - $startTime) . " seconds"
        ];
    }

    public function set($key, $data){
        $startTime = microtime(true);
        try{
            $this->redis->setex($key, $this->ttl, json_encode($data));
            return[
                'debug' => "Cache saved for $key in " . (microtime(true) - $startTime) . " seconds"
            ];
        }catch (\RedisException $e){
            error_log("Redis set error for $key: " . $e->getMessage());
            return [
                'debug' => "Cache save failed for $key: " . $e->getMessage()
            ];
        }
    }

    public function getRedis(){
        return $this->redis;
    }
}