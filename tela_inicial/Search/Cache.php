<?php

namespace spotify\tela_inicial\Search;

class Cache
{
    private $cacheDir = 'cache/';
    private $ttl = 900; // Tempo de vida do cache em segundos


    public function __construct($ttl = 900)
    { // TTL padrão de 5 minutos
        $this->ttl = $ttl;
        $this->ensureCacheDirExists();
        $this->cleanExpiredCache();
    }

    private function ensureCacheDirExists()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    private function cleanExpiredCache()
    {
        $files = glob($this->cacheDir . '*.json');
        $currenTime = time();
        $deleteFiles = 0;

        foreach ($files as $file) {
            $cacheData = json_decode(file_get_contents($file), true);
            if ($cacheData && isset($cacheData['expiration']) && $cacheData['expiration'] < $currenTime) {
                unlink($file);
                $deleteFiles++;
            }
        }

        return ['message' => "Deleted $deleteFiles expired cache files"];
    }

    public function get($cacheKey)
    {
        $cacheKey = $this->generateUserSpecificKey($cacheKey);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';

        if (file_exists($cacheFile)) {
            $fileHandle = fopen($cacheFile, 'r');
            if (flock($fileHandle, LOCK_SH)) { // Bloqueio compartilhado para leitura
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                flock($fileHandle, LOCK_UN);
                fclose($fileHandle);

                if ($cacheData && isset($cacheData['expiration']) && $cacheData['expiration'] > time()) {
                    return [
                        'data' => $cacheData['data'],
                        'debug' => "Cache hit for $cacheKey"
                    ];
                } else {
                    fclose($fileHandle);
                    return [
                        'data' => null,
                        'debug' => "Failed to acquire read lock for cache: $cacheKey"
                    ];
                }
            }
        }
        return [
            'data' => null,
            'debug' => "Cache miss for $cacheKey"
        ];
    }

    public function set($cacheKey, $data)
    {
        $cacheKey = $this->generateUserSpecificKey($cacheKey);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';
        $cacheData = ['data' => $data, 'expiration' => time() + $this->ttl];

        $fileHandle = fopen($cacheFile, 'w');
        if (flock($fileHandle, LOCK_EX)) { // Bloqueio exclusivo para escrita
            fwrite($fileHandle, json_encode($cacheData, JSON_UNESCAPED_SLASHES));
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            return ['debug' => "Cache saved for $cacheKey"];
        } else {
            fclose($fileHandle);
            return ['debug' => "Failed to acquire write lock for cache: $cacheKey"];
        }
    }

    public function clear($cacheKey)
    {
        $cacheKey = $this->generateUserSpecificKey($cacheKey);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            return ['debug' => "Cache cleared for $cacheKey"];
        }
        return ['debug' => "No cache to clear for $cacheKey"];
    }

    private function generateUserSpecificKey($cacheKey){
        $userId = $_SESSION['user_id'] ?? session_id(); // Usa user_id ou session_id
        return "user_{$userId}_{$cacheKey}";
    }
}
