<?php
session_start();
require '../../vendor/autoload.php';

use spotify\tela_inicial\library\SpotifyClient;
use spotify\tela_inicial\Search\RedisCache;

header('Content-Type: application/json');

// Habilitar compressão Gzip
if (extension_loaded('zlib') && !headers_sent()) {
    ob_start('ob_gzhandler');
}

// Função para retornar erro com debug
function sendError($error, $debug = null)
{
    echo json_encode([
        'success' => false,
        'error' => $error,
        'debug' => $debug
    ]);
    exit;
}

// Função para retornar sucesso com debug
function sendSuccess($results, $debug = [])
{
    $etag = md5(json_encode($results));
    header("ETag: \"$etag\"");
    echo json_encode([
        'success' => true,
        'results' => $results,
        'debug' => $debug
    ]);
    exit;
}




// Função para buscar artistas populares
function fetchPopularArtists($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'popular_artists';
    $cacheResult = $cache->get($cacheKey);
    $cached = $cacheResult['data'];
    $cacheDebug = $cacheResult['debug'];

    if ($cached !== null) {
        return [
            'data' => $cached,
            'debug' => [
                'cache' => $cacheDebug,
                'from_cache' => true

            ]
        ];
    }

    throw new Exception('Dados de artistas populares não estão disponíveis no cache');
}


// Função para buscar podcasts populares
function fetchPopularPodcasts($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'popular_podcasts_br';
    $cacheResult = $cache->get($cacheKey);
    $cached = $cacheResult['data'];
    $cacheDebug = $cacheResult['debug'];

    


    if ($cached !== null) {
        return [
            'data' => $cached,
            'debug' => [
                'cache' => $cacheDebug,
                'from_cache' => true

            ]
        ];
    }

    throw new Exception('Dados de podcasts populares não estão disponíveis no cache');
}

function retrieveRecentlyPlayedContent($spotify, $cache)
{
    $cacheKey = 'recently_played';
    $cacheResult = $cache->get($cacheKey);
    $cached = $cacheResult['data'];
    $cacheDebug = $cacheResult['debug'];

    if ($cached !== null) {
        return [
            'data' => $cached,
            'debug' => [
                'cache' => $cacheDebug,
                'from_cache' => true
            ]
        ];
    }

    throw new Exception('Dados que foram tocados recentemente não estão disponíveis no cache');
}


// Função para buscar novos álbuns
function fetchNewAlbums($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'new_albums_releases_br';
    $cacheResult = $cache->get($cacheKey);
    $cached = $cacheResult['data'];
    $cacheDebug = $cacheResult['debug'];

  

    if ($cached !== null) {
        return [
            'data' => $cached,
            'debug' => [
                'cache' => $cacheDebug,
                'from_cache' => true
            ]
        ];
    }

    throw new Exception('Dados de novos álbuns não estão disponíveis no cache');
}

// Função para buscar paradas de sucesso
function fetchHitsParades($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'hits_parades';
    $cacheResult = $cache->get($cacheKey);
    $cached = $cacheResult['data'];
    $cacheDebug = $cacheResult['debug'];


    if ($cached !== null) {
        return [
            'data' => $cached,
            'debug' => [
                'cache' => $cacheDebug,
                'from_cache' => true
            ]
        ];
    }

    throw new Exception('Dados de paradas de sucesso não estão disponíveis no cache');
}

// Função para buscar todas as seções
function fetchAllSections($spotify, $cache, $market = 'BR')
{

    $startTime = microtime(true);
    $artistsResult = fetchPopularArtists($spotify, $cache, $market);
    $podcastsResult = fetchPopularPodcasts($spotify, $cache, $market);
    $recentlyPlayedResult = retrieveRecentlyPlayedContent($spotify, $cache);
    $albumsResult = fetchNewAlbums($spotify, $cache, $market);
    $hitParadesResult = fetchHitsParades($spotify, $cache, $market);

    $endTime = microtime(true);

    return [
        'data' => [
            'popularArtists' => $artistsResult['data'],
            'episodesPodcast' => $podcastsResult['data'],
            'recentlyPlayedResult' => $recentlyPlayedResult['data'],
            'newAlbums' => $albumsResult['data'],
            'hitParades' => $hitParadesResult['data']
        ],
        'debug' => [
            'artists_debug' => $artistsResult['debug'],
            'podcasts_debug' => $podcastsResult['debug'],
            'recently_played_debug' => $recentlyPlayedResult['debug'],
            'albums_debug' => $albumsResult['debug'],
            'hit_parades_debug' => $hitParadesResult['debug'],
            'total_response_time' => ($endTime - $startTime) . " seconds"
        ]
    ];
}

// Inicialização
$spotify = new SpotifyClient();
$cache = new RedisCache(3600);
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';

// Verificar autenticação
if (!$spotify->setAccessTokenFromSession()) {
    sendError('Não autenticado', [
        'token' => $tokenInSession,
        'session' => print_r($_SESSION, true)
    ]);
}

// Ler entrada
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';

if (empty($type)) {
    sendError('Tipo de consulta vazio');
}

// Processar requisição com base no tipo
try {
    $result = null;
    $debug = [];

    switch ($type) {
        case 'all_sections':
            $result = fetchAllSections($spotify, $cache);
            $results = $result['data'];
            $debug = $result['debug'];
            break;
        default:
            sendError('Tipo de consulta inválido', "Tipo recebido: $type");
    }

    sendSuccess($results, $debug);
} catch (\Exception $e) {
    sendError('Erro ao processar a consulta', $e->getMessage());
}




exit;
