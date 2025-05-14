<?php

// Inclui o autoloader do Composer para carregar as dependências do projeto
require __DIR__ . '/../../vendor/autoload.php';

// Importa as classes necessárias do namespace do projeto
use spotify\tela_inicial\library\SpotifyClient;
use spotify\tela_inicial\Search\RedisCache;

// Instancia o cliente Spotify para interagir com a API
$spotify = new SpotifyClient();
// Instancia o cache Redis com tempo de expiração de 3600 segundos (1 hora)
$cache = new RedisCache(3600);
// Define o mercado (país) como Brasil
$market = 'BR';

// Função para limpar o arquivo cache.log após 5 execuções
function clearCacheLogAfterFiveExecutions($cache, $logFile = '/mnt/c/xampp/htdocs/spotify/tela_inicial/Search/cache.log')
{
    // Obtém a conexão Redis do cache
    $redis = $cache->getRedis();
    // Define a chave para o contador de execuções
    $counterkey = 'cache_update_counter';

    // Incrementa o contador de execuções no Redis
    $counter = $redis->incr($counterkey);

    // Verifica se o contador atingiu 5 execuções
    if ($counter >= 5) {
        try {
            // Esvazia o arquivo cache.log
            file_put_contents($logFile, '');
            // Reseta o contador no Redis
            $redis->set($counterkey, 0);
            // Registra no log que o cache foi limpo
            error_log("cache.log limpo após 5 execuções bem-sucedidas.");
        } catch (\Exception $e) {
            // Registra qualquer erro ocorrido ao limpar o cache.log
            error_log("Erro ao limpar cache.log: " . $e->getMessage());
        }
    }
}

// Função para buscar artistas populares usando a API do Spotify
function fetchPopularArtists($spotify, $cache, $market = 'BR')
{
    // Define a chave do cache para artistas populares
    $cacheKey = 'popular_artists';
    // Registra o tempo inicial para medir o desempenho
    $startTime = microtime(true);
    // Define o limite e o offset para a busca
    $limit = 25;
    $offset = 0;
    // Array para armazenar todos os artistas encontrados
    $allArtists = [];

    // Realiza a busca por artistas lançados em 2025
    $data = $spotify->api->search('year:2025', ['artist'], [
        'limit' => $limit,
        'offset' => $offset,
        'market' => $market
    ]);

    // Mapeia os dados dos artistas para um formato simplificado
    $artists = array_map(fn($artist) => [
        'name' => $artist->name,
        'image' => $artist->images[0]->url ?? 'default.jpg', // Usa imagem padrão se não houver
        'description' => 'Artista',
        'id' => $artist->id,
        'popularity' => $artist->popularity
    ], $data->artists->items);

    // Combina os artistas encontrados no array principal
    $allArtists = array_merge($allArtists, $artists);

    // Ordena os artistas por popularidade (do menos para o mais popular)
    usort($allArtists, fn($a, $b) => $a['popularity'] <=> $b['popularity']);
    // Prepara o resultado para armazenamento no cache
    $results = ['popularArtists' => $allArtists];

    // Armazena os resultados no cache
    $cacheSetResult = $cache->set($cacheKey, $results);
    // Registra o tempo final
    $endTime = microtime(true);

    // Retorna os dados e informações de depuração
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
    // Define a chave do cache para podcasts populares
    $cacheKey = 'popular_podcasts_br';
    // Registra o tempo inicial
    $startTime = microtime(true);
    // Busca podcasts populares no mercado especificado
    $podcasts = $spotify->api->search('podcast', 'show', [
        'market' => $market,
        'limit' => 25
    ])->shows->items;

    // Verifica se a busca retornou resultados
    if (empty($podcasts)) {
        throw new Exception('Nenhum podcast encontrado');
    }

    // Array para armazenar os episódios dos podcasts
    $episodesPodcast = [];
    foreach ($podcasts as $podcast) {
        $podcastId = $podcast->id;
        try {
            // Busca o último episódio do podcast
            $episodes = $spotify->api->getShowEpisodes($podcastId, [
                'market' => 'BR',
                'limit' => 1
            ])->items;

            // Processa cada episódio encontrado
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
            // Adiciona um atraso para evitar limites de taxa da API
            usleep(100000);
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            // Registra erros ao buscar episódios
            error_log("Erro ao buscar episódios do podcast $podcastId: " . $e->getMessage());
            continue;
        }
    }

    // Armazena os episódios no cache
    $cacheSetResult = $cache->set($cacheKey, $episodesPodcast);
    // Registra o tempo final
    $endTime = microtime(true);

    // Retorna os dados e informações de depuração
    return [
        'data' => $episodesPodcast,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_episodes' => count($episodesPodcast)
        ]
    ];
}

// Função para recuperar conteúdos reproduzidos recentemente pelo usuário
function retrieveRecentlyPlayedContent($spotify, $cache)
{
    // Define a chave do cache para conteúdos recentes
    $cacheKey = 'recently_played';
    // Registra o tempo inicial
    $startTime = microtime(true);

    // Autenticação no modo CLI
    if (php_sapi_name() === 'cli') {
        // Caminho para o arquivo de refresh token
        $refreshTokenFile = __DIR__ . '/../spotify_refresh_token.txt';
        if (file_exists($refreshTokenFile)) {
            // Lê o refresh token do arquivo
            $refreshToken = trim(file_get_contents($refreshTokenFile));
            if ($refreshToken && $spotify->authenticateWithRefreshToken($refreshToken)) {
                error_log("Autenticação com refresh token bem-sucedida em retrieveRecentlyPlayedContent");
            } else {
                // Registra falha na autenticação
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
            // Registra ausência do arquivo de refresh token
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
        // Autenticação no modo web
        try {
            if (!$spotify->setAccessTokenFromSession()) {
                if (!$spotify->refreshToken()) {
                    // Registra falha na autenticação do usuário
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
            // Registra erros na autenticação
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

    // Obtém o token de acesso
    $accessToken = $spotify->getAccessToken();

    // Usa cURL para buscar conteúdos reproduzidos recentemente
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.spotify.com/v1/me/player/recently-played?limit=25');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Verifica se a chamada foi bem-sucedida
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

        // Decodifica a resposta JSON
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
                    'error' => 'Erro ao decodificar JSON da cURL'
                ]
            ];
        }

        // Verifica se há conteúdos reproduzidos
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

        // Processa os conteúdos reproduzidos
        $recentContent = [];
        $seenIds = [];
        foreach ($recentlyPlayed->items as $item) {
            $content = $item->track;
            $playedAt = $item->played_at;
            $id = $content->type === 'episode' ? $content->show->id : ($content->artists[0]->id ?? '');
            if (isset($seenIds[$id])) {
                continue; // Ignora duplicatas
            }
            $seenIds[$id] = true;

            // Processa faixas (tracks)
            if ($content->type === 'track') {
                $artist = $content->artists[0] ?? null;
                if ($artist) {
                    $artistImage = 'default.jpg'; // Imagem padrão

                    // Busca a imagem do artista
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

                    // Adiciona o artista ao conteúdo recente
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

        // Verifica se há conteúdos válidos
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

        // Armazena os conteúdos no cache
        try {
            $cacheSetResult = $cache->set($cacheKey, $recentContent, 3600);
            error_log("Dados armazenados no cache para $cacheKey: " . json_encode($recentContent));
        } catch (\Exception $e) {
            error_log("Erro ao armazenar no cache para $cacheKey: " . $e->getMessage());
        }

        // Registra o tempo final
        $endTime = microtime(true);

        // Retorna os dados e informações de depuração
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
        // Registra erros na chamada cURL
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

// Função para buscar novos álbuns lançados
function fetchNewAlbums($spotify, $cache, $market = 'BR')
{
    // Define a chave do cache para novos álbuns
    $cacheKey = 'new_albums_releases_br';
    // Registra o tempo inicial
    $startTime = microtime(true);

    // Busca novos lançamentos de álbuns
    $newReleases = $spotify->api->getNewReleases([
        'country' => $market,
        'limit' => 20,
        'offset' => 0
    ]);

    // Arrays para armazenar álbuns e seus IDs
    $albums = [];
    $albumIds = [];

    // Processa os álbuns retornados
    foreach ($newReleases->albums->items as $item) {
        $albumIds[] = $item->id;
        $albums[$item->id] = [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $item->images[0]->url ?? 'default.jpg',
            'artist' => $item->artists[0]->name
        ];
    }

    // Busca informações adicionais (popularidade) para cada álbum
    foreach ($albumIds as $albumId) {
        try {
            $albumData = $spotify->api->getAlbum($albumId, ['market' => 'BR']);
            $albums[$albumId]['popularity'] = $albumData->popularity ?? 0;
            usleep(100000); // Atraso para evitar limites de taxa
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            error_log("Erro ao buscar álbum $albumId: " . $e->getMessage());
            $albums[$albumId]['popularity'] = 0;
        }
    }

    // Filtra álbuns com popularidade maior que 0
    $filteredAlbums = array_filter($albums, fn($album) => $album['popularity'] > 0);
    // Ordena por popularidade (do mais para o menos popular)
    usort($filteredAlbums, fn($a, $b) => $b['popularity'] - $a['popularity']);
    $newAlbums = array_values($filteredAlbums);

    // Verifica se há álbuns válidos
    if (empty($newAlbums)) {
        throw new Exception('Nenhum álbum encontrado com popularidade');
    }

    // Armazena os álbuns no cache
    $cacheSetResult = $cache->set($cacheKey, $newAlbums);
    // Registra o tempo final
    $endTime = microtime(true);

    // Retorna os dados e informações de depuração
    return [
        'data' => $newAlbums,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_albums' => count($newAlbums)
        ]
    ];
}

// Função para buscar paradas de sucesso (playlists populares)
function fetchHitsParades($spotify, $cache, $market = 'BR')
{
    // Define a chave do cache para paradas de sucesso
    $cacheKey = 'hits_parades';
    // Registra o tempo inicial
    $startTime = microtime(true);

    // Lista de IDs de playlists populares
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

    // Array para armazenar as playlists
    $hitParades = [];

    // Busca informações de cada playlist
    foreach ($playlistIds as $playlistId) {
        try {
            $playlistData = $spotify->api->getPlaylist($playlistId, [
                'market' => 'BR',
                'fields' => 'id,name,description,images'
            ]);

            // Adiciona a playlist ao array
            $hitParades[] = [
                'id' => $playlistData->id,
                'image' => $playlistData->images[0]->url ?? 'default.jpg',
                'name' => $playlistData->name,
                'description' => $playlistData->description,
            ];
            error_log("Playlist adicionada: $playlistId - " . $playlistData->name);
            usleep(100000); // Atraso para evitar limites de taxa
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            // Registra erros ao buscar playlists
            error_log("Erro ao buscar playlist $playlistId: " . $e->getMessage());
            continue;
        }
    }

    // Verifica se há playlists válidas
    if (empty($hitParades)) {
        throw new Exception('Nenhuma playlist encontrada');
    }

    // Armazena as playlists no cache
    $cacheSetResult = $cache->set($cacheKey, $hitParades);
    // Registra o tempo final
    $endTime = microtime(true);

    // Retorna os dados e informações de depuração
    return [
        'data' => $hitParades,
        'debug' => [
            'cache' => $cacheSetResult['debug'],
            'response_time' => ($endTime - $startTime) . " seconds",
            'fetched_playlists' => count($hitParades)
        ]
    ];
}

// Função para atualizar todas as seções do cache
function updateAllSections($spotify, $cache, $market = 'BR')
{
    // Registra o tempo inicial
    $startTime = microtime(true);
    // Array para armazenar informações de depuração
    $debug = [];

    // Atualiza a seção de artistas populares
    $artistsResult = fetchPopularArtists($spotify, $cache, $market);
    $debug['artists_debug'] = $artistsResult['debug'];

    // Atualiza a seção de podcasts populares
    $podcastsResult = fetchPopularPodcasts($spotify, $cache, $market);
    $debug['podcasts_debug'] = $podcastsResult['debug'];

    // Atualiza a seção de conteúdos reproduzidos recentemente
    $recentlyPlayed = retrieveRecentlyPlayedContent($spotify, $cache);
    $debug['recently_played'] = $recentlyPlayed['debug'];

    // Atualiza a seção de novos álbuns
    $albumsResult = fetchNewAlbums($spotify, $cache, $market);
    $debug['albums_debug'] = $albumsResult['debug'];

    // Atualiza a seção de paradas de sucesso
    $hitParadesResult = fetchHitsParades($spotify, $cache, $market);
    $debug['hit_parades_debug'] = $hitParadesResult['debug'];

    // Verifica o modo de execução (CLI ou web)
    if (php_sapi_name() !== 'cli') {
        // Atualiza novamente a seção de conteúdos recentes no modo web
        $recentlyPlayed = retrieveRecentlyPlayedContent($spotify, $cache);
        $debug['recentlyPlayed'] = $recentlyPlayed['debug'];
    } else {
        // No modo CLI, retorna uma mensagem indicando que a operação não é suportada
        $debug['recentlyPlayed'] = [
            'cache' => [],
            'response_time' => '0 seconds',
            'fetched_items' => 0,
            'from_cache' => false,
            'message' => 'Operação não suportada no modo CLI'
        ];
    }

    // Registra o tempo final
    $endTime = microtime(true);

    // Registra no cache que a atualização foi concluída
    $cache->set('cache_updated', [
        'status' => 'updated',
        'timestamp' => date('Y-m-d H:i:s'),
        'ttl' => 3600
    ]);

    // Retorna as informações de depuração
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

// Verifica autenticação com base no modo de execução
if (php_sapi_name() === 'cli') {
    // Autenticação no modo CLI
    $refreshTokenFile = __DIR__ . '/../spotify_refresh_token.txt';
    if (file_exists($refreshTokenFile)) {
        // Tenta autenticar com refresh token
        $refreshToken = trim(file_get_contents($refreshTokenFile));
        if ($refreshToken && $spotify->authenticateWithRefreshToken($refreshToken)) {
            error_log("Autenticação com refresh token bem-sucedida no CLI");
        }
    } else {
        // Tenta autenticação com Client Credentials se refresh token não estiver disponível
        error_log("Falha ao autenticar com refresh token. Usando Client Credentials.");
        if (!$spotify->authenticateClientCredentials()) {
            error_log("Autenticação Client Credentials falhou no WarmCache.php");
            exit(1);
        }
    }
} else {
    // Autenticação no modo web
    session_start();
    if (!$spotify->setAccessTokenFromSession()) {
        error_log("Autenticação falhou no WarmCache.php");
        exit(1);
    }
}

// Atualiza o cache chamando a função principal
try {
    $result = updateAllSections($spotify, $cache, $market);
    error_log("Cache atualizado com sucesso: " . json_encode($result['debug']));

    // Incrementa o contador e limpa cache.log após 5 execuções
    clearCacheLogAfterFiveExecutions($cache);
} catch (\Exception $e) {
    // Registra erros durante a atualização do cache
    error_log("Erro ao atualizar o cache: " . $e->getMessage());
    exit(1);
}

// Finaliza a execução com sucesso
exit(0);