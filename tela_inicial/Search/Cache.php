<?php

namespace spotify\tela_inicial\Search;

class Cache
{
    // Diretório onde os arquivos de cache serão armazenados
    private $cacheDir = 'cache/';
    
    // Tempo de vida do cache (em segundos) - padrão: 3600 segundos (1 hora)
    private $ttl = 3600;

    // Construtor da classe
    public function __construct($ttl = 3600)
    {
        // Define o tempo de expiração personalizado (se fornecido)
        $this->ttl = $ttl;

        // Garante que o diretório de cache exista
        $this->ensureCacheDirExists();

        // Remove arquivos de cache expirados
        $this->cleanExpiredCache();
    }

    // Garante que o diretório de cache exista, e o cria se não existir
    private function ensureCacheDirExists()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    // Limpa arquivos de cache expirados com base na data de expiração salva
    private function cleanExpiredCache()
    {
        $files = glob($this->cacheDir . '*.json'); // Pega todos os arquivos JSON do diretório
        $currenTime = time(); // Hora atual
        $deleteFiles = 0; // Contador de arquivos apagados

        foreach ($files as $file) {
            $cacheData = json_decode(file_get_contents($file), true);

            // Verifica se o cache está expirado
            if ($cacheData && isset($cacheData['expiration']) && $cacheData['expiration'] < $currenTime) {
                unlink($file); // Remove o arquivo
                $deleteFiles++;
            }
        }

        return ['message' => "Deleted $deleteFiles expired cache files"];
    }

    // Recupera dados do cache com base em uma chave
    public function get($cacheKey)
    {
        $cacheKey = $this->generateUserSpecificKey($cacheKey);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';

        // Verifica se o arquivo de cache existe
        if (file_exists($cacheFile)) {
            $fileHandle = fopen($cacheFile, 'r');

            // Tenta obter um bloqueio de leitura
            if (flock($fileHandle, LOCK_SH)) {
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                flock($fileHandle, LOCK_UN);
                fclose($fileHandle);

                // Verifica se o cache ainda está válido
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

        // Cache não encontrado
        return [
            'data' => null,
            'debug' => "Cache miss for $cacheKey"
        ];
    }

    // Salva dados no cache
    public function set($cacheKey, $data)
    {
        $cacheKey = $this->generateUserSpecificKey($cacheKey);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';

        // Dados a serem armazenados no cache, com data de expiração
        $cacheData = ['data' => $data, 'expiration' => time() + $this->ttl];

        $fileHandle = fopen($cacheFile, 'w');

        // Tenta obter um bloqueio exclusivo para escrita
        if (flock($fileHandle, LOCK_EX)) {
            fwrite($fileHandle, json_encode($cacheData, JSON_UNESCAPED_SLASHES));
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            return ['debug' => "Cache saved for $cacheKey"];
        } else {
            fclose($fileHandle);
            return ['debug' => "Failed to acquire write lock for cache: $cacheKey"];
        }
    }

    // Remove o cache associado a uma chave específica
    public function clear($cacheKey)
    {
        $cacheKey = $this->generateUserSpecificKey($cacheKey);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';

        // Se o cache existir, remove-o
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            return ['debug' => "Cache cleared for $cacheKey"];
        }

        return ['debug' => "No cache to clear for $cacheKey"];
    }

    // Gera uma chave única para o usuário, combinando o ID do usuário ou ID da sessão com a chave fornecida
    private function generateUserSpecificKey($cacheKey)
    {
        $userId = $_SESSION['user_id'] ?? session_id(); // Usa user_id se disponível, senão session_id
        return "user_{$userId}_{$cacheKey}";
    }
}
