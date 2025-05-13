<?php

require __DIR__ . '/../../vendor/autoload.php';

use spotify\tela_inicial\library\SpotifyClient;
use spotify\tela_inicial\Search\RedisCache;

$spotify = new SpotifyClient();
$cache = new RedisCache(3600);
$market = 'BR';

// Função para limpar o cache.log após 5 execuções
function clearCacheLogAfterFiveExecutions($cache, $logFile =  '/mnt/c/xampp/htdocs/spotify/tela_inicial/Search/cache.log')
{
    $redis = $cache->getRedis();
    $counterkey = 'cache_update_counter';

    // Incrementar o contador
    $counter = $redis->incr($counterkey);

    // Se atingir 5 execuções, limpar o cache.log e resetar o contador
    if ($counter >= 5) {
        try {
            // Esvaziar o arquivo
            file_put_contents($logFile, '');
            $redis->set($counterkey, 0);
            error_log("cache.log limpo após 5 execuções bem-sucedidas.");
        } catch (\Exception $e) {
            error_log("Erro ao limpar cache.log: " . $e->getMessage());
        }
    }
}


// Função para buscar artistas populares
function fetchPopularArtists($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'popular_artists';
    $startTime = microtime(true);
    $limit = 25;
    $offset = 0;
    $allArtists = [];

    $data = $spotify->api->search('year:2025', ['artist'], [
        'limit' => $limit,
        'offset' => $offset,
        'market' => $market
    ]);

    $artists = array_map(fn($artist) => [
        'name' => $artist->name,
        'image' => $artist->images[0]->url ?? 'default.jpg',
        'description' => 'Artista',
        'id' => $artist->id,
        'popularity' => $artist->popularity
    ], $data->artists->items);

    $allArtists = array_merge($allArtists, $artists);

    usort($allArtists, fn($a, $b) => $a['popularity'] <=> $b['popularity']);
    $results = ['popularArtists' => $allArtists];

    $cacheSetResult = $cache->set($cacheKey, $results);
    $endTime = microtime(true);

    return [
        'data' => $results,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_artists' => count($allArtists)
        ]
    ];
}


// Função para buscar podcasts populares
function fetchPopularPodcasts($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'popular_podcasts_br';
    $startTime = microtime(true);
    $podcasts = $spotify->api->search('podcast', 'show', [
        'market' => $market,
        'limit' => 25
    ])->shows->items;

    if (empty($podcasts)) {
        throw new Exception('Nenhum podcast encontrado');
    }

    $episodesPodcast = [];
    foreach ($podcasts as $podcast) {
        $podcastId = $podcast->id;
        try {
            $episodes = $spotify->api->getShowEpisodes($podcastId, [
                'market' => 'BR',
                'limit' => 1
            ])->items;

            if (!empty($episodes)) {
                foreach ($episodes as $episode) {
                    if (isset($episode->id) && isset($episode->name)) {
                        $episodesPodcast[] = [
                            'id' => $episode->id,
                            'uri' => "spotify:episode:{$episode->id}",
                            'name' => $episode->name,
                            'image' => $episode->images[0]->url ?? 'default.jpg',
                            'duration_ms' => $episode->duration_ms,
                            'release_date' => $episode->release_date
                        ];
                    }
                }
            }
            usleep(100000); // Delay para evitar rate limiting
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            error_log("Erro ao buscar episódios do podcast $podcastId: " . $e->getMessage());
            continue;
        }
    }


    $cacheSetResult = $cache->set($cacheKey, $episodesPodcast);


    $endTime = microtime(true);


    return [
        'data' => $episodesPodcast,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_episodes' => count($episodesPodcast)
        ]
    ];
}



function retrieveRecentlyPlayedContent($spotify, $cache)
{
    $cacheKey = 'recently_played';
    $startTime = microtime(true);

    // Verificar cache primeiro
    try {
        $cachedContent = $cache->get($cacheKey);
        if (isset($cachedContent['data']) && is_array($cachedContent['data']) && !empty($cachedContent['data'])) {
            return [
                'data' => $cachedContent['data'],
                'debug' => [
                    'cache' => ['status' => 'retrieved from cache'],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => count($cachedContent['data']),
                    'from_cache' => true
                ]
            ];
        } else {
            error_log("Cache vazio ou inválido para $cacheKey: " . json_encode($cachedContent));
        }
    } catch (\Exception $e) {
        error_log("Erro ao recuperar cache para $cacheKey: " . $e->getMessage());
    }

    // Autenticação no modo CLI
    if (php_sapi_name() === 'cli') {
        $refreshTokenFile = __DIR__ . '/../spotify_refresh_token.txt';
        if (file_exists($refreshTokenFile)) {
            $refreshToken = trim(file_get_contents($refreshTokenFile));
            if ($refreshToken && $spotify->authenticateWithRefreshToken($refreshToken)) {
                error_log("Autenticação com refresh token bem-sucedida em retrieveRecentlyPlayedContent");
            } else {
                error_log("Falha na autenticação com refresh token em retrieveRecentlyPlayedContent");
                return [
                    'data' => [],
                    'debug' => [
                        'cache' => [],
                        'response_time' => (microtime(true) - $startTime) . " seconds",
                        'fetched_items' => 0,
                        'from_cache' => false,
                        'error' => 'Falha na autenticação com refresh token no CLI'
                    ]
                ];
            }
        } else {
            error_log("Arquivo de refresh token não encontrado em retrieveRecentlyPlayedContent");
            return [
                'data' => [],
                'debug' => [
                    'cache' => [],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => 0,
                    'from_cache' => false,
                    'error' => 'Arquivo de refresh token não encontrado no CLI'
                ]
            ];
        }
    } else {
        try {
            if (!$spotify->setAccessTokenFromSession()) {
                if (!$spotify->refreshToken()) {
                    error_log("Falha na autenticação de usuário com Spotify");
                    return [
                        'data' => [],
                        'debug' => [
                            'cache' => [],
                            'response_time' => (microtime(true) - $startTime) . " seconds",
                            'fetched_items' => 0,
                            'from_cache' => false,
                            'error' => 'Autenticação de usuário necessária. Por favor, faça login no Spotify.'
                        ]
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("Erro na autenticação de usuário: " . $e->getMessage());
            return [
                'data' => [],
                'debug' => [
                    'cache' => [],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => 0,
                    'from_cache' => false,
                    'error' => 'Erro na autenticação: ' . $e->getMessage()
                ]
            ];
        }
    }


    $accessToken = $spotify->getAccessToken();


    // Usando cURL para recently-played
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.spotify.com/v1/me/player/recently-played?limit=25');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //error_log("Resposta cURL da API recently-played (HTTP $httpCode): " . $response);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Erro na chamada cURL: HTTP $httpCode");
            return [
                'data' => [],
                'debug' => [
                    'cache' => [],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => 0,
                    'from_cache' => false,
                    'error' => "Erro na chamada cURL: HTTP $httpCode"
                ]
            ];
        }

        $recentlyPlayed = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erro ao decodificar JSON da cURL: " . json_last_error_msg());
            return [
                'data' => [],
                'debug' => [
                    'cache' => [],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => 0,
                    'from_cache' => false,
                    'error' => 'Error ao decodificar JSON DA cURL'
                ]
            ];
        }


        if (empty($recentlyPlayed->items)) {
            error_log("Nenhum conteúdo reproduzido recentemente encontrado");
            try {
                $cacheSetResult = $cache->set($cacheKey, [], 3600);
                error_log("Array vazio armazenado no cache para $cacheKey: " . json_encode($cacheSetResult));
            } catch (\Exception $e) {
                error_log("Erro ao armazenar array vazio no cache para $cacheKey: " . $e->getMessage());
            }
            return [
                'data' => [],
                'debug' => [
                    'cache' => $cacheSetResult['debug'] ?? ['status' => 'cache set'],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => 0,
                    'from_cache' => false,
                    'message' => 'Nenhum conteúdo reproduzido recentemente encontrado'
                ]

            ];
        }


        $recentContent = [];
        $seenIds = [];
        foreach ($recentlyPlayed->items as $item) {
            $content = $item->track;
            $playedAt = $item->played_at;
            $id = $content->type === 'episode' ? $content->show->id : ($content->artists[0]->id ?? '');
            if (isset($seenIds[$id])) {
                continue; // Ignorar duplicatas
            }
            $seenIds[$id] = true;

            if ($content->type === 'track') {
                $artist = $content->artists[0] ?? null;
                if ($artist) {
                    $artistImage = 'default.jpg'; // Fallback

                    try {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://api.spotify.com/v1/artists/{$artist->id}");
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $artistResponse = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode === 200) {
                            $artistData = json_decode($artistResponse);
                            if (json_last_error() === JSON_ERROR_NONE && !empty($artistData->images)) {
                                $artistImage = $artistData->images[0]->url ?? 'default.jpg';
                            }
                        } else {
                            error_log("Erro ao buscar imagem do artista {$artist->id}: HTTP $httpCode");
                        }
                    } catch (\Exception $e) {
                        error_log("Erro ao buscar imagem do artista {$artist->id}: " . $e->getMessage());
                    }

                    $recentContent[] = [
                        'id' => $artist->id,
                        'uri' => "spotify:artist:{$artist->id}",
                        'name' => $artist->name,
                        'image' => $artistImage,
                        'description' => 'Artista',
                        'played_at' => $playedAt,
                        'type' => 'artist'
                    ];
                }
            }
        }

        if (empty($recentContent)) {
            error_log("Nenhum conteúdo válido encontrado após processamento");
            try {
                $cacheSetResult = $cache->set($cacheKey, [], 3600);
                error_log("Array vazio armazenado no cache para $cacheKey: " . json_encode($cacheSetResult));
            } catch (\Exception $e) {
                error_log("Erro ao armazenar array vazio no cache para $cacheKey: " . $e->getMessage());
            }
            return [
                'data' => [],
                'debug' => [
                    'cache' => $cacheSetResult['debug'] ?? ['status' => 'cache set'],
                    'response_time' => (microtime(true) - $startTime) . " seconds",
                    'fetched_items' => 0,
                    'from_cache' => false,
                    'message' => 'Nenhum conteúdo válido encontrado'
                ]
            ];
        }

        // Armazenar no cache
        try {
            $cacheSetResult = $cache->set($cacheKey, $recentContent, 3600);
            error_log("Dados armazenados no cache para $cacheKey: " . json_encode($recentContent));
        } catch (\Exception $e) {
            error_log("Erro ao armazenar no cache para $cacheKey: " . $e->getMessage());
        }

        $endTime = microtime(true);

        return [
            'data' => $recentContent,
            'debug' => [
                'cache' => $cacheSetResult['debug'] ?? ['status' => 'cache set'],
                'response_time' => ($endTime - $startTime) . " seconds",
                'fetched_items' => count($recentContent),
                'from_cache' => false
            ]
        ];
    } catch (\Exception $e) {
        error_log("Erro na chamada cURL para recently-played: " . $e->getMessage());
        return [
            'data' => [],
            'debug' => [
                'cache' => [],
                'response_time' => (microtime(true) - $startTime) . " seconds",
                'fetched_items' => 0,
                'from_cache' => false,
                'error' => 'Erro na chamada cURL: ' . $e->getMessage()
            ]
        ];
    }
}


// Função para buscar novos álbuns
function fetchNewAlbums($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'new_albums_releases_br';
    $startTime = microtime(true);

    $newReleases = $spotify->api->getNewReleases([
        'country' => $market,
        'limit' => 20,
        'offset' => 0
    ]);

    $albums = [];
    $albumIds = [];

    foreach ($newReleases->albums->items as $item) {
        $albumIds[] = $item->id;
        $albums[$item->id] = [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $item->images[0]->url ?? 'default.jpg',
            'artist' => $item->artists[0]->name
        ];
    }

    foreach ($albumIds as $albumId) {
        try {
            $albumData = $spotify->api->getAlbum($albumId, ['market' => 'BR']);
            $albums[$albumId]['popularity'] = $albumData->popularity ?? 0;
            usleep(100000); // Delay para evitar rate limiting
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            error_log("Erro ao buscar álbum $albumId: " . $e->getMessage());
            $albums[$albumId]['popularity'] = 0;
        }
    }

    $filteredAlbums = array_filter($albums, fn($album) => $album['popularity'] > 0);
    usort($filteredAlbums, fn($a, $b) => $b['popularity'] - $a['popularity']);
    $newAlbums = array_values($filteredAlbums);

    if (empty($newAlbums)) {
        throw new Exception('Nenhum álbum encontrado com popularidade');
    }

    $cacheSetResult = $cache->set($cacheKey, $newAlbums);
    $endTime = microtime(true);

    return [
        'data' => $newAlbums,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_albums' => count($newAlbums)
        ]
    ];
}

// Função para buscar paradas de sucesso
function fetchHitsParades($spotify, $cache, $market = 'BR')
{
    $cacheKey = 'hits_parades';
    $startTime = microtime(true);

    $playlistIds = [
        '5DqR5bAbk7mTq5jnvJsjel',
        '6GNmpxMYl4hD90GwGINyla',
        '4Zn9LFbwTguxz4XeAWTDi1',
        '0X039tyQfxhPtVWoZUqqzX',
        '4iXBmc9lmaFnjBKK9aCXg3',
        '5teJDcsrQJ9QZGYugoq2MB',
        '1hCQkNupVD4HqfTS4GnMwC',
        '44HCx45fac9vrwqne4Sw41',
        '3OXekjrqVhrdiZoZE5ZY9S',
        '4ymxQmWEV5ae73JrK4owIK',
        '49VFPROr6yhf8yB2J2wcNz',
        '6VNAy0lActqHlZWbbpQcUW',
        '6pKvMoTAkiRhugrwibucHN',
        '67HYZM7wyXrABqp60yEILo',
        '1yKMlyu7hirmy9fPxWcVnT',
        '5MZvlLOt6b9JqbHOaEzJ5Z',
        '0IhVk09lB03uNccMTUemfd'
    ];

    $hitParades = [];

    foreach ($playlistIds as $playlistId) {
        try {
            $playlistData = $spotify->api->getPlaylist($playlistId, [
                'market' => 'BR',
                'fields' => 'id,name,description,images'
            ]);

            $hitParades[] = [
                'id' => $playlistData->id,
                'image' => $playlistData->images[0]->url ?? 'default.jpg',
                'name' => $playlistData->name,
                'description' => $playlistData->description,
            ];
            error_log("Playlist adicionada: $playlistId - " . $playlistData->name);
            usleep(100000); // Delay para evitar rate limiting
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            error_log("Erro ao buscar playlist $playlistId: " . $e->getMessage());
            continue;
        }
    }

    if (empty($hitParades)) {
        throw new Exception('Nenhuma playlist encontrada');
    }

    $cacheSetResult = $cache->set($cacheKey, $hitParades);
    $endTime = microtime(true);

    return [
        'data' => $hitParades,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_playlists' => count($hitParades)
        ]
    ];
}

// Função para atualizar todas as seções
function updateAllSections($spotify, $cache, $market = 'BR')
{
    $startTime = microtime(true);
    $debug = [];

    $artistsResult = fetchPopularArtists($spotify, $cache, $market);
    $debug['artists_debug'] = $artistsResult['debug'];

    $podcastsResult = fetchPopularPodcasts($spotify, $cache, $market);
    $debug['podcasts_debug'] = $podcastsResult['debug'];

    $recentlyPlayed = retrieveRecentlyPlayedContent($spotify, $cache);
    $debug['recently_played'] = $recentlyPlayed['debug'];


    $albumsResult = fetchNewAlbums($spotify, $cache, $market);
    $debug['albums_debug'] = $albumsResult['debug'];

    $hitParadesResult = fetchHitsParades($spotify, $cache, $market);
    $debug['hit_parades_debug'] = $hitParadesResult['debug'];

    if (php_sapi_name() !== 'cli') {
        $recentlyPlayed = retrieveRecentlyPlayedContent($spotify, $cache);
        $debug['recentlyPlayed'] = $recentlyPlayed['debug'];
    } else {
        $debug['recentlyPlayed'] = [
            'cache' => [],
            'response_time' => '0 seconds',
            'fetched_items' => 0,
            'from_cache' => false,
            'message' => 'Operação não suportada no modo CLI'
        ];
    }

    $endTime = microtime(true);

    $cache->set('cache_updated', [
        'status' => 'updated',
        'timestamp' => date('Y-m-d H:i:s'),
        'ttl' => 3600
    ]);

    return [
        'debug' => [
            'artists_debug' => $debug['artists_debug'],
            'podcasts_debug' => $debug['podcasts_debug'],
            'albums_debug' => $debug['albums_debug'],
            'hit_parades_debug' => $debug['hit_parades_debug'],
            'recently_played' => $debug['recently_played'],
            'total_response_time' => ($endTime - $startTime) . " seconds"
        ]
    ];
}




// Verificar autenticação
if (php_sapi_name() === 'cli') {
    $refreshTokenFile = __DIR__ . '/../spotify_refresh_token.txt';
    if (file_exists($refreshTokenFile)) {
        $refreshToken = trim(file_get_contents($refreshTokenFile));
        if ($refreshToken && $spotify->authenticateWithRefreshToken($refreshToken)) {
            error_log("Autenticação com refresh token bem-sucedida no CLI");
        }
    } else {
        error_log("Falha ao autenticar com refresh token. Usando Client Credentials.");

        if (!$spotify->authenticateClientCredentials()) {
            error_log("Autenticação Client Credentials falhou no WarmCache.php");
            exit(1);
        }
    }
} else {
    session_start();
    if (!$spotify->setAccessTokenFromSession()) {
        error_log("Autenticação falhou no WarmCache.php");
        exit(1);
    }
}

// Atualizar o cache
try {
    $result = updateAllSections($spotify, $cache, $market);
    error_log("Cache atualizado com sucesso: " . json_encode($result['debug']));

    // Incrementar contador e limpar cache.log após 5 execuções
    clearCacheLogAfterFiveExecutions($cache);
} catch (\Exception $e) {
    error_log("Erro ao atualizar o cache: " . $e->getMessage());
    exit(1);
}

exit(0);
