<?php
session_start();
require '../../vendor/autoload.php';

use spotify\tela_inicial\library\SpotifyClient;

header('Content-Type: application/json');

// Ativa compressão gzip se suportado pelo cliente
if(isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false){
    ob_start('ob_gzhandler');
}else{
    ob_start();
}

/**
 * Função que realiza a busca no Spotify com tentativas de retentativa
 * em caso de falhas por rate limit (429) ou erro de servidor (>=500).
 */
function searchWithRetry($spotify, $query, $types, $options, $retries = 0, $maxRetries = 3, $baseDelay = 1000)
{
    $debugMessages = [];
    try {
        $result = $spotify->api->search($query, $types, $options);
        $debugMessages[] = "Busca bem-sucedida para query: $query, tipos: " . implode(',', $types);
        return ['result' => $result, 'debug' => $debugMessages];
    } catch (\Exception $e) {
        $statusCode = $e->getCode();
        $debugMessages[] = "Erro $statusCode na busca: " . $e->getMessage();
        // Se erro for 429 ou erro de servidor
        if ($statusCode === 429 || $statusCode >= 500) {
            if ($retries < $maxRetries) {
                $delay = $baseDelay * pow(2, $retries); // Atraso exponencial
                $debugMessages[] =  "Retentando busca ($retries/$maxRetries) após {$delay}ms";
                usleep($delay * 1000); // Converte ms para microsegundos
                $retryResult = searchWithRetry($spotify, $query, $types, $options, $retries + 1, $maxRetries, $baseDelay);
                $debugMessages = array_merge($debugMessages, $retryResult['debug']);
                return ['result' => $retryResult['result'], 'debug' => $debugMessages];
            }
        }
        throw new \Exception($e->getMessage(), $e->getCode(), $e);
    }
}

// Configurações de cache
$cacheTTL = 3600; // Tempo de validade: 1 hora
$cacheDir = __DIR__ . '/cache';
$debugMessages = [];

// Cria o diretório de cache se não existir
if (!is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        $debugMessages[] = "Falha ao criar diretório de cache: $cacheDir";
    }
}

// Instancia o cliente do Spotify
$spotify = new SpotifyClient();
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';

// Verifica se o token está disponível na sessão
if (!$spotify->setAccessTokenFromSession()) {
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado',
        'debug' => [
            'token' => $tokenInSession,
            'session' => print_r($_SESSION, true)
        ]
    ]);
    ob_end_flush();
    exit;
}

// Lê os dados JSON recebidos via POST
$data = json_decode(file_get_contents('php://input'), true);
$query = $data['query'] ?? '';
$cleanQuery = trim($query);

// Valida se a consulta está vazia
if(empty($cleanQuery)){
    echo json_encode([
        'success' => false,
        'error' => 'Consulta vazia',
        'debug' => 'Nenhuma termo de busca fornecido'
    ]);
    ob_end_flush();
    exit;
}

// Gera uma chave de cache única baseada na consulta
$cachekey = 'search_' .md5($cleanQuery);
$cacheFile = "$cacheDir/$cachekey.json";

// Verifica se há resultado no cache e ainda é válido
if(file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL){
    $cachedResults = json_decode(file_get_contents($cacheFile), true);
    $debugMessages[] = "Resultados obtidos do cache (arquivo) para query: $cleanQuery";
    echo json_encode([
        'success' => true,
        'results' => $cachedResults,
        'debug' => $debugMessages
    ]);
    ob_get_flush();
    exit;
}

try {
    // Realiza a busca nos principais tipos
    $searchResult = searchWithRetry($spotify, $cleanQuery, ['artist', 'album', 'playlist', 'show', 'track'], ['limit' => 10]);
    $searchResults = $searchResult['result'];
    $debugMessages = array_merge($debugMessages, $searchResult['debug']);
    $results = [];

    // Processa artistas
    $results['artists'] = array_map(function ($artist) {
        return [
            'id' => $artist->id,
            'name' => $artist->name,
            'popularity' => $artist->popularity ?? 0,
            'images' => $artist->images ?? []
        ];
    }, $searchResults->artists->items);

    // Processa álbuns
    $results['albums'] = array_map(function ($album) {
        return [
            'id' => $album->id,
            'name' => $album->name,
            'images' => $album->images ?? [],
            'artists' => array_map(function ($artist) {
                return ['id' => $artist->id, 'name' => $artist->name];
            }, $album->artists)
        ];
    }, $searchResults->albums->items);

    // Filtra playlists válidas
    $validPlaylists = array_filter($searchResults->playlists->items, function ($playlist) {
        return $playlist !== null && isset($playlist->name);
    });

    // Processa playlists
    $results['playlists'] = array_map(function ($playlist) {
        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'images' => $playlist->images ?? []
        ];
    }, array_values($validPlaylists));

    // Processa podcasts (shows)
    $results['podcasts'] = array_map(function ($show) {
        return [
            'id' => $show->id,
            'name' => $show->name,
            'publisher' => $show->publisher,
            'images' => $show->images ?? []
        ];
    }, $searchResults->shows->items);

    // Processa faixas (tracks)
    $results['tracks'] = array_map(function ($track) {
        return [
            'id' => $track->id,
            'name' => $track->name,
            'popularity' => $track->popularity ?? 0,
            'images' => $track->album->images ?? [],
            'artists' => array_map(function ($artist) {
                return ['id' => $artist->id, 'name' => $artist->name];
            }, $track->artists),
            'duration_ms' => $track->duration_ms
        ];
    }, $searchResults->tracks->items);

    // Monta todos os itens para determinar o "melhor resultado"
    $allItems = [];

    // Adiciona artistas
    foreach ($searchResults->artists->items as $artist) {
        $allItems[] = [
            'type' => 'artist',
            'id' => $artist->id,
            'name' => $artist->name,
            'popularity' => $artist->popularity ?? 0,
            'images' => $artist->images ?? [],
            'artists' => [['id' => $artist->id, 'name' => $artist->name]]
        ];
    }

    // Adiciona faixas
    foreach ($searchResults->tracks->items as $track) {
        $allItems[] = [
            'type' => 'track',
            'id' => $track->id,
            'name' => $track->name,
            'popularity' => $track->popularity ?? 0,
            'images' => $track->album->images ?? [],
            'artists' => array_map(function ($artist) {
                return ['id' => $artist->id, 'name' => $artist->name];
            }, $track->artists),
            'duration_ms' => $track->duration_ms
        ];
    }

    // Adiciona álbuns
    foreach ($searchResults->albums->items as $album) {
        $allItems[] = [
            'type' => 'album',
            'id' => $album->id,
            'name' => $album->name,
            'popularity' => $album->popularity ?? 0,
            'images' => $album->images ?? [],
            'artists' => array_map(function ($artist) {
                return ['id' => $artist->id, 'name' => $artist->name];
            }, $album->artists)
        ];
    }

    // Adiciona playlists
    foreach ($validPlaylists as $playlist) {
        $allItems[] = [
            'type' => 'playlist',
            'id' => $playlist->id,
            'name' => $playlist->name,
            'images' => $playlist->images ?? []
        ];
    }

    // Adiciona shows/podcasts
    foreach ($searchResults->shows->items as $show) {
        $allItems[] = [
            'type' => 'show',
            'id' => $show->id,
            'name' => $show->name,
            'publisher' => $show->publisher,
            'images' => $show->images ?? []
        ];
    }

    // Define o melhor resultado (primeiro da lista combinada)
    $results['best_result'] = !empty($allItems) ? $allItems[0] : null;

    // Armazena resultado no cache
    if (is_writable($cacheDir)) {
        file_put_contents($cacheFile, json_encode($results));
        $debugMessages[] = "Resultados armazenados no cache (arquivo) para query: $cleanQuery";
    } else {
        $debugMessages[] = "Falha ao armazenar no cache: diretório $cacheDir não é gravável";
    }

    // Retorna resposta JSON
    echo json_encode([
        'success' => true,
        'results' => $results,
        'debug' => $debugMessages
    ]);
} catch (\Exception $e) {
    // Retorna erro em caso de falha
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => array_merge($debugMessages, ['Erro geral: ' . $e->getMessage()])
    ]);
}

// Libera o buffer de saída
ob_end_flush();
