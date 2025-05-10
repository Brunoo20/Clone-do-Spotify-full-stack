<?php
session_start();
require '../../vendor/autoload.php';

use spotify\tela_inicial\library\SpotifyClient;

header('Content-Type: application/json');

if(isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false){
    ob_start('ob_gzhandler');
}else{
    ob_start();
}

// Função para buscar com retentativa
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
        if ($statusCode === 429 || $statusCode >= 500) { // Rate limit ou erro de servidor
            if ($retries < $maxRetries) {
                $delay = $baseDelay * pow(2, $retries); // Exponential backoff
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

// Configuração do cache (usando cache em arquivo)
$cacheTTL = 3600; // 60 minutos
$cacheDir = __DIR__ . '/cache'; // Diretório para cache em arquivo
$debugMessages = [];

// Cria o diretório de cache, se não existir
if (!is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        $debugMessages[] = "Falha ao criar diretório de cache: $cacheDir";
    }
}

$spotify = new SpotifyClient();
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';


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
$data = json_decode(file_get_contents('php://input'), true);
$query = $data['query'] ?? '';



$cleanQuery = trim($query);

// Verificação mínima para evitar consultas vazias
if(empty($cleanQuery)){
    echo json_encode([
        'success' => false,
        'error' => 'Consulta vazia',
        'debug' => 'Nenhuma termo de busca fornecido'
    ]);
    ob_end_flush();
    exit;
}

// Gera uma chave única para o cache
$cachekey = 'search_' .md5($cleanQuery);
$cacheFile = "$cacheDir/$cachekey.json";

// Tenta obter do cache
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
    // Faz uma única busca para todos os tipos
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

    // Processa playlists
    $validPlaylists = array_filter($searchResults->playlists->items, function ($playlist) {
        return $playlist !== null && isset($playlist->name);
    });
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

    // Processa tracks
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

    // Determina o "best_result"
    $allItems = [];
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
    foreach ($validPlaylists as $playlist) {
        $allItems[] = [
            'type' => 'playlist',
            'id' => $playlist->id,
            'name' => $playlist->name,
            'images' => $playlist->images ?? []
        ];
    }
    foreach ($searchResults->shows->items as $show) {
        $allItems[] = [
            'type' => 'show',
            'id' => $show->id,
            'name' => $show->name,
            'publisher' => $show->publisher,
            'images' => $show->images ?? []
        ];
    }

    $results['best_result'] = !empty($allItems) ? $allItems[0] : null;




    // Armazena no cache
    if (is_writable($cacheDir)) {
        file_put_contents($cacheFile, json_encode($results));
        $debugMessages[] = "Resultados armazenados no cache (arquivo) para query: $cleanQuery";
    } else {
        $debugMessages[] = "Falha ao armazenar no cache: diretório $cacheDir não é gravável";
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'debug' => $debugMessages
    ]);
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => array_merge($debugMessages, ['Erro geral: ' . $e->getMessage()])
    ]);
}

ob_end_flush();
