<?php
// Define o namespace da classe
namespace spotify\tela_inicial\Search;

class RedisCache {
    private $redis; // Instância do cliente Redis
    private $ttl;   // Tempo de expiração do cache em segundos

    // Construtor da classe - define o tempo de vida do cache (TTL) e conecta ao servidor Redis
    public function __construct($ttl = 3600) {
        $this->redis = new \Redis(); // Cria nova instância do cliente Redis
        $this->redis->connect('127.0.0.1', 6379); // Conecta ao servidor Redis na porta padrão 6379
        $this->ttl = $ttl; // Define o TTL (tempo de vida dos dados no cache)
    }

    // Método para buscar dados do cache
    public function get($key) {
        $startTime = microtime(true); // Marca o tempo inicial para depuração

        try {
            $data = $this->redis->get($key); // Tenta recuperar o valor da chave no Redis
            if ($data !== false) {
                $data = json_decode($data, true); // Decodifica os dados JSON
                return [
                    'data' => $data,
                    'debug' => "Cache retrieved for $key in " . (microtime(true) - $startTime) . " seconds"
                ];
            }
        } catch (\RedisException $e) {
            // Em caso de erro, registra no log
            error_log("Redis get error for $key: " . $e->getMessage());
        }

        // Retorna cache perdido (miss) se a chave não for encontrada ou houver erro
        return [
            'data' => null,
            'debug' => "Cache miss for $key in " . (microtime(true) - $startTime) . " seconds"
        ];
    }

    // Método para salvar dados no cache
    public function set($key, $data) {
        $startTime = microtime(true); // Marca o tempo inicial para depuração

        try {
            // Codifica os dados como JSON e define a chave no Redis com TTL
            $this->redis->setex($key, $this->ttl, json_encode($data));
            return [
                'debug' => "Cache saved for $key in " . (microtime(true) - $startTime) . " seconds"
            ];
        } catch (\RedisException $e) {
            // Em caso de erro, registra no log e retorna mensagem de falha
            error_log("Redis set error for $key: " . $e->getMessage());
            return [
                'debug' => "Cache save failed for $key: " . $e->getMessage()
            ];
        }
    }

    // Método auxiliar para acessar diretamente a instância Redis, se necessário
    public function getRedis() {
        return $this->redis;
    }
}
