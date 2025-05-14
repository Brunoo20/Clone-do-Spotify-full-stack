<?php
// Inicia a sessão para gerenciar variáveis de usuário, como tokens de autenticação
session_start();

// Inclui o autoloader do Composer para carregar as dependências do projeto
require '../../vendor/autoload.php';

// Importa a classe SpotifyClient do namespace do projeto
use spotify\tela_inicial\library\SpotifyClient;

// Define o tipo de conteúdo da resposta como JSON
header('Content-type: application/json');

// Instancia o cliente Spotify para interagir com a API
$spotify = new SpotifyClient;
// Obtém o token de acesso da sessão ou define uma mensagem padrão se não encontrado
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';
// Registra o token no log para depuração
error_log("Token na sessão em Get_player.php: " . $tokenInSession);

// Verifica se o token de acesso pode ser definido a partir da sessão
if (!$spotify->setAccessTokenFromSession()) {
    // Se a autenticação falhar, retorna uma resposta JSON com erro
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado',
        'debug' => [
            'token' => $tokenInSession,
            'session' => print_r($_SESSION, true) // Inclui informações da sessão para depuração
        ]
    ]);
    exit;
}

// Lê e decodifica o corpo da requisição JSON
$input = json_decode(file_get_contents('php://input'), true);
// Extrai os parâmetros da requisição, com valores padrão null se não fornecidos
$action = $input['action'] ?? null;
$query = $input['query'] ?? null;
$volume = $input['volume'] ?? null;
$deviceId = $input['device_id'] ?? null;
$episodeUri = $input['episode_uri'] ?? null;
$positionMs = $input['position_ms'] ?? null;

// Define uma resposta padrão para caso nenhuma ação seja especificada
$response = ['success' => false, 'message' => 'Ação não especificada'];

try {
    // Processa a ação solicitada usando uma estrutura switch
    switch ($action) {
        case 'get_token':
            // Retorna o token de acesso armazenado na sessão
            $response = [
                'success' => true,
                'token' => $tokenInSession
            ];
            break;

        case 'get_artist_top_tracks':
            // Obtém o ID do artista da requisição
            $artistId = $input['artist_id'] ?? null;
            if (!$artistId) {
                // Retorna erro se o ID do artista não for fornecido
                $response = ['success' => false, 'message' => 'ID do artista não fornecido'];
            } else {
                // Busca as principais faixas do artista no mercado dos EUA
                $topTracks = $spotify->api->getArtistTopTracks($artistId, ['market' => 'US']);
                if ($topTracks && !empty($topTracks->tracks)) {
                    // Mapeia as faixas para um formato simplificado
                    $tracks = array_map(function ($track) {
                        return [
                            'uri' => $track->uri,
                            'name' => $track->name,
                            'artist' => $track->artists[0]->name,
                            'artist_id' => $track->artists[0]->id,
                            'album_image' => $track->album->images[0]->url ?? null
                        ];
                    }, $topTracks->tracks);
                    // Retorna as faixas e armazena na sessão
                    $response = ['success' => true, 'tracks' => $tracks];
                    $_SESSION['artist_top_tracks'] = $tracks;
                } else {
                    // Retorna erro se nenhuma faixa for encontrada
                    $response = ['success' => false, 'message' => 'Nenhuma música encontrada para o artista'];
                }
            }
            break;

        case 'play':
            // Verifica se o ID do dispositivo foi fornecido
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
            // Obtém a lista de dispositivos disponíveis
            $devices = $spotify->api->getMyDevices();
            $deviceFound = false;
            foreach ($devices->devices as $device) {
                if ($device->id === $deviceId) {
                    $deviceFound = true;
                    break;
                }
            }

            // Lança erro se o dispositivo não for encontrado
            if (!$deviceFound) {
                throw new Exception("Dispositivo não encontrado ou não está ativo: $deviceId");
            }

            // Define o dispositivo ativo sem iniciar a reprodução imediatamente
            $spotify->api->changeMyDevice(['device_ids' => [$deviceId], 'play' => false]);
            $options = ['device_id' => $deviceId];

            // Configura as opções de reprodução com base nos parâmetros fornecidos
            if ($episodeUri) {
                // Reproduz um episódio específico
                $options['uris'] = [$episodeUri];
                if ($positionMs !== null) {
                    $options['position_ms'] = (int)$positionMs;
                }
                $_SESSION['last_uri'] = $episodeUri;
                $_SESSION['queue'] = null; // Limpa a fila para podcasts
            } elseif ($query) {
                // Reproduz um contexto (ex.: playlist ou álbum)
                $options['context_uri'] = $query;
                $_SESSION['last_uri'] = $query;
                $_SESSION['queue'] = null; // Limpa a fila para contextos
            } elseif (isset($input['uris']) && !empty($input['uris'])) {
                // Reproduz uma lista de URIs fornecida
                $options['uris'] = $input['uris'];
                if ($positionMs !== null) {
                    $options['position_ms'] = (int)$positionMs;
                }
                $_SESSION['last_uri'] = $input['uris'][0];
                $_SESSION['queue'] = $input['uris']; // Armazena a fila
            } elseif (isset($_SESSION['last_uri'])) {
                // Reproduz o último URI armazenado
                $options['uris'] = [$_SESSION['last_uri']];
                if ($positionMs !== null) {
                    $options['position_ms'] = (int)$positionMs;
                }
                $_SESSION['queue'] = null;
            } else {
                // Usa uma faixa padrão como fallback
                $options['uris'] = ['spotify:track:4iV5W9uYEdYUVa79Axb7Rh'];
                $_SESSION['last_uri'] = $options['uris'][0];
                $_SESSION['queue'] = null;
            }

            // Inicia a reprodução com as opções configuradas
            $spotify->play($options);

            // Ajusta o volume, garantindo que esteja entre 0 e 100
            $volume = isset($input['volume']) ? max(0, min(100, (int)$input['volume'])) : 100;
            $spotify->setVolume($volume, ['device_id' => $deviceId]);

            // Tenta obter o estado atual da reprodução
            $currentTrack = $spotify->api->getMyCurrentTrack(['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => 'Reprodução iniciada'];

            // Inclui detalhes da faixa ou episódio em reprodução, se disponível
            if ($currentTrack && $currentTrack->item) {
                $item = $currentTrack->item;
                $response['data'] = [
                    'name' => $item->name,
                    'artist' => $item->type === 'track' ? implode(', ', array_map(fn($artist) => $artist->name, $item->artists)) : $item->show->name,
                    'album_image' => $item->type === 'track' ? ($item->album->images[0]->url ?? null) : ($item->images[0]->url ?? null),
                    'uri' => $item->uri
                ];
            } else {
                // Usa o último URI como fallback
                $lastUri = $_SESSION['last_uri'] ?? null;
                if ($lastUri) {
                    if (strpos($lastUri, 'spotify:episode:') === 0) {
                        // Busca detalhes do episódio
                        $episodeId = str_replace('spotify:episode:', '', $lastUri);
                        try {
                            $episode = $spotify->api->getEpisode($episodeId);
                            if ($episode) {
                                $response['data'] = [
                                    'name' => $episode->name,
                                    'artist' => $episode->show->name,
                                    'album_image' => $episode->images[0]->url ?? null,
                                    'uri' => $lastUri
                                ];
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao buscar episódio: " . $e->getMessage());
                        }
                    } elseif (strpos($lastUri, 'spotify:track:') === 0) {
                        // Busca detalhes da faixa
                        $trackId = str_replace('spotify:track:', '', $lastUri);
                        try {
                            $track = $spotify->api->getTrack($trackId);
                            if ($track) {
                                $response['data'] = [
                                    'name' => $track->name,
                                    'artist' => implode(', ', array_map(fn($artist) => $artist->name, $track->artists)),
                                    'album_image' => $track->album->images[0]->url ?? null,
                                    'uri' => $lastUri
                                ];
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao buscar faixa: " . $e->getMessage());
                        }
                    }
                }
            }
            break;

        case 'pause':
            // Verifica se o ID do dispositivo foi fornecido
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
            // Verifica se o dispositivo está ativo
            $devices = $spotify->api->getMyDevices();
            $deviceFound = false;
            foreach ($devices->devices as $device) {
                if ($device->id === $deviceId) {
                    $deviceFound = true;
                    break;
                }
            }
            if (!$deviceFound) {
                throw new Exception("Dispositivo não encontrado ou não está ativo: $deviceId");
            }
            // Pausa a reprodução no dispositivo especificado
            $spotify->pause(['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => 'Reprodução pausada'];
            break;

        case 'back':
            try {
                // Verifica se o ID do dispositivo foi fornecido
                if (!$deviceId) {
                    throw new Exception('Device ID não fornecido');
                }
                // Verifica se o dispositivo está ativo
                $devices = $spotify->api->getMyDevices();
                $deviceFound = false;
                foreach ($devices->devices as $device) {
                    if ($device->id === $deviceId) {
                        $deviceFound = true;
                        break;
                    }
                }
                if (!$deviceFound) {
                    throw new Exception("Dispositivo não encontrado ou não está ativo: $deviceId");
                }

                // Retrocede para a faixa ou episódio anterior
                $spotify->back(['device_id' => $deviceId]);

                // Tenta obter o estado atual da reprodução
                $currentTrack = $spotify->api->getMyCurrentTrack(['device_id' => $deviceId]);
                if ($currentTrack && $currentTrack->item) {
                    $item = $currentTrack->item;
                    $response = [
                        'success' => true,
                        'message' => 'Faixa/episódio anterior reproduzido',
                        'data' => [
                            'name' => $item->name,
                            'artist' => $item->type === 'track' ? implode(', ', array_map(fn($artist) => $artist->name, $item->artists)) : $item->show->name,
                            'album_image' => $item->type === 'track' ? ($item->album->images[0]->url ?? null) : ($item->images[0]->url ?? null),
                            'uri' => $item->uri
                        ]
                    ];
                    $_SESSION['last_uri'] = $item->uri;
                } else {
                    // Usa o último URI como fallback
                    $lastUri = $_SESSION['last_uri'] ?? null;
                    if ($lastUri) {
                        if (strpos($lastUri, 'spotify:episode:') === 0) {
                            $episodeId = str_replace('spotify:episode:', '', $lastUri);
                            try {
                                $episode = $spotify->api->getEpisode($episodeId);
                                if ($episode) {
                                    $response = [
                                        'success' => true,
                                        'message' => 'Faixa/episódio anterior reproduzido',
                                        'data' => [
                                            'name' => $episode->name,
                                            'artist' => $episode->show->name,
                                            'album_image' => $episode->images[0]->url ?? null,
                                            'uri' => $lastUri
                                        ]
                                    ];
                                    error_log("Dados de back (getEpisode): " . json_encode($response['data']));
                                } else {
                                    $response = ['success' => true, 'message' => 'Faixa/episódio anterior reproduzido, mas detalhes não disponíveis'];
                                }
                            } catch (\Exception $e) {
                                error_log("Erro ao buscar episódio: " . $e->getMessage());
                                $response = ['success' => true, 'message' => 'Faixa/episódio anterior reproduzido, mas detalhes não disponíveis'];
                            }
                        } elseif (strpos($lastUri, 'spotify:track:') === 0) {
                            $trackId = str_replace('spotify:track:', '', $lastUri);
                            try {
                                $track = $spotify->api->getTrack($trackId);
                                if ($track) {
                                    $response = [
                                        'success' => true,
                                        'message' => 'Faixa/episódio anterior reproduzido',
                                        'data' => [
                                            'name' => $track->name,
                                            'artist' => implode(', ', array_map(fn($artist) => $artist->name, $track->artists)),
                                            'album_image' => $track->album->images[0]->url ?? null,
                                            'uri' => $lastUri
                                        ]
                                    ];
                                    error_log("Dados de back (getTrack): " . json_encode($response['data']));
                                } else {
                                    $response = ['success' => true, 'message' => 'Faixa/episódio anterior reproduzido, mas detalhes não disponíveis'];
                                }
                            } catch (\Exception $e) {
                                error_log("Erro ao buscar faixa: " . $e->getMessage());
                                $response = ['success' => true, 'message' => 'Faixa/episódio anterior reproduzido, mas detalhes não disponíveis'];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $response = ['success' => false, 'error' => 'Erro ao retroceder para a faixa anterior: ' . $e->getMessage()];
            }
            break;

        case 'next':
            try {
                // Verifica se o ID do dispositivo foi fornecido
                if (!$deviceId) {
                    throw new Exception('Device ID não fornecido');
                }
                // Verifica se o dispositivo está ativo
                $devices = $spotify->api->getMyDevices();
                $deviceFound = false;
                foreach ($devices->devices as $device) {
                    if ($device->id === $deviceId) {
                        $deviceFound = true;
                        break;
                    }
                }
                if (!$deviceFound) {
                    throw new Exception("Dispositivo não encontrado ou não está ativo: $deviceId");
                }

                // Avança para a próxima faixa ou episódio
                $spotify->next(['device_id' => $deviceId]);

                // Tenta obter o estado atual da reprodução com até 3 tentativas
                $currentTrack = null;
                $attempts = 0;
                $maxAttempts = 3;
                while ($attempts < $maxAttempts) {
                    $currentTrack = $spotify->api->getMyCurrentTrack(['device_id' => $deviceId]);
                    if ($currentTrack && $currentTrack->item) {
                        break;
                    }
                    $attempts++;
                }

                // Inclui detalhes da faixa ou episódio atual, se disponível
                if ($currentTrack && $currentTrack->item) {
                    $item = $currentTrack->item;
                    $response = [
                        'success' => true,
                        'message' => 'Próxima faixa/episódio reproduzido',
                        'data' => [
                            'name' => $item->name,
                            'artist' => $item->type === 'track' ? implode(', ', array_map(fn($artist) => $artist->name, $item->artists)) : $item->show->name,
                            'album_image' => $item->type === 'track' ? ($item->album->images[0]->url ?? null) : ($item->images[0]->url ?? null),
                            'uri' => $item->uri
                        ]
                    ];
                    $_SESSION['last_uri'] = $item->uri;
                    error_log("Dados de next (getMyCurrentTrack): " . json_encode($response['data']));
                } else {
                    // Usa o último URI como fallback
                    $lastUri = $_SESSION['last_uri'] ?? null;
                    if ($lastUri) {
                        if (strpos($lastUri, 'spotify:episode:') === 0) {
                            $episodeId = str_replace('spotify:episode:', '', $lastUri);
                            try {
                                $episode = $spotify->api->getEpisode($episodeId);
                                if ($episode) {
                                    $response = [
                                        'success' => true,
                                        'message' => 'Próxima faixa/episódio reproduzido',
                                        'data' => [
                                            'name' => $episode->name,
                                            'artist' => $episode->show->name,
                                            'album_image' => $episode->images[0]->url ?? null,
                                            'uri' => $lastUri
                                        ]
                                    ];
                                    error_log("Dados de next (getEpisode): " . json_encode($response['data']));
                                } else {
                                    $response = ['success' => true, 'message' => 'Próxima faixa/episódio reproduzido, mas detalhes não disponíveis'];
                                }
                            } catch (\Exception $e) {
                                error_log("Erro ao buscar episódio: " . $e->getMessage());
                                $response = ['success' => true, 'message' => 'Próxima faixa/episódio reproduzido, mas detalhes não disponíveis'];
                            }
                        } elseif (strpos($lastUri, 'spotify:track:') === 0) {
                            $trackId = str_replace('spotify:track:', '', $lastUri);
                            try {
                                $track = $spotify->api->getTrack($trackId);
                                if ($track) {
                                    $response = [
                                        'success' => true,
                                        'message' => 'Próxima faixa/episódio reproduzido',
                                        'data' => [
                                            'name' => $track->name,
                                            'artist' => implode(', ', array_map(fn($artist) => $artist->name, $track->artists)),
                                            'album_image' => $track->album->images[0]->url ?? null,
                                            'uri' => $lastUri
                                        ]
                                    ];
                                    error_log("Dados de next (getTrack): " . json_encode($response['data']));
                                } else {
                                    $response = ['success' => true, 'message' => 'Próxima faixa/episódio reproduzido, mas detalhes não disponíveis'];
                                }
                            } catch (\Exception $e) {
                                error_log("Erro ao buscar faixa: " . $e->getMessage());
                                $response = ['success' => true, 'message' => 'Próxima faixa/episódio reproduzido, mas detalhes não disponíveis'];
                            }
                        } else {
                            $response = ['success' => true, 'message' => 'Próxima faixa/episódio reproduzido, mas detalhes não disponíveis'];
                        }
                    } else {
                        $response = ['success' => true, 'message' => 'Próxima faixa/episódio reproduzido, mas detalhes não disponíveis'];
                    }
                    error_log("Dados de next (fallback): " . json_encode($response));
                }
            } catch (\Exception $e) {
                $response = ['success' => false, 'error' => 'Erro ao avançar para a próxima faixa: ' . $e->getMessage()];
                error_log("Erro em next: " . $e->getMessage());
            }
            break;

        case 'set_volume':
            // Verifica se o volume e o ID do dispositivo foram fornecidos
            if ($volume === null) {
                throw new Exception('Volume não especificado');
            }
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
            // Verifica se o usuário tem uma conta Premium
            $user = $spotify->getUser();
            if (isset($user->product) && $user->product !== 'premium') {
                throw new Exception('Esta funcionalidade requer uma conta Spotify Premium.');
            }
            // Ajusta o volume, garantindo que esteja entre 0 e 100
            $volume = max(0, min(100, (int)$volume));
            $spotify->setVolume($volume, ['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => "Volume ajustado para $volume%"];
            break;

        case 'seek':
            // Verifica se a posição e o ID do dispositivo foram fornecidos
            if ($positionMs === null) {
                throw new Exception('Posição (position_ms) não especificada');
            }
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
            // Verifica se o dispositivo está ativo
            $devices = $spotify->api->getMyDevices();
            $deviceFound = false;
            foreach ($devices->devices as $device) {
                if ($device->id === $deviceId) {
                    $deviceFound = true;
                    break;
                }
            }
            if (!$deviceFound) {
                throw new Exception("Dispositivo não encontrado ou não está ativo: $deviceId");
            }
            // Ajusta a posição da reprodução
            $spotify->seek((int)$positionMs, ['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => "Posição ajustada para $positionMs ms"];
            break;

        case 'repeat_mode':
            try {
                // Obtém o estado de repetição e o ID do dispositivo
                $state = $input['state'] ?? null;
                $deviceId = $input['device_id'] ?? null;

                // Verifica se os parâmetros necessários foram fornecidos
                if (!$state) {
                    throw new Exception('Estado de repetição não especificado');
                }
                if (!$deviceId) {
                    throw new Exception('Device ID não fornecido');
                }

                // Registra os parâmetros no log para depuração
                error_log("repeat_mode: state=$state, device_id=$deviceId");

                // Verifica se o dispositivo está ativo
                $devices = $spotify->api->getMyDevices();
                $deviceFound = false;
                foreach ($devices->devices as $device) {
                    if ($device->id === $deviceId) {
                        $deviceFound = true;
                        break;
                    }
                }
                if (!$deviceFound) {
                    throw new Exception("Dispositivo não encontrado ou não está ativo: $deviceId");
                }

                // Define o modo de repetição
                $spotify->repeatMode([
                    'state' => $state,
                    'device_id' => $deviceId
                ]);

                $response = [
                    'success' => true,
                    'message' => "Modo de repetição ajustado para '$state'"
                ];
            } catch (\Exception $e) {
                $response = [
                    'success' => false,
                    'error' => 'Erro ao configurar o modo de repetição: ' . $e->getMessage()
                ];
                error_log("Erro em repeat_mode: " . $e->getMessage());
            }
            break;

        case 'get_current_track':
            // Obtém a faixa ou episódio em reprodução
            $currentTrack = $spotify->api->getMyCurrentTrack($deviceId ? ['device_id' => $deviceId] : []);
            if ($currentTrack && $currentTrack->item) {
                $item = $currentTrack->item;
                $response = [
                    'success' => true,
                    'data' => [
                        'name' => $item->name,
                        'artist' => $item->type === 'track' ? implode(', ', array_map(fn($artist) => $artist->name, $item->artists)) : $item->show->name,
                        'album_image' => $item->type === 'track' ? ($item->album->images[0]->url ?? null) : ($item->images[0]->url ?? null),
                        'uri' => $item->uri
                    ]
                ];
            } else {
                // Usa o último URI como fallback
                $lastUri = $_SESSION['last_uri'] ?? null;
                if ($lastUri) {
                    if (strpos($lastUri, 'spotify:episode:') === 0) {
                        $episodeId = str_replace('spotify:episode:', '', $lastUri);
                        try {
                            $episode = $spotify->api->getEpisode($episodeId);
                            if ($episode) {
                                $response = [
                                    'success' => true,
                                    'data' => [
                                        'name' => $episode->name,
                                        'artist' => $episode->show->name,
                                        'album_image' => $episode->images[0]->url ?? null,
                                        'uri' => $lastUri
                                    ]
                                ];
                            } else {
                                $response = ['success' => false, 'message' => 'Nenhum episódio encontrado'];
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao buscar episódio: " . $e->getMessage());
                            $response = ['success' => false, 'message' => 'Erro ao buscar episódio'];
                        }
                    } elseif (strpos($lastUri, 'spotify:track:') === 0) {
                        $trackId = str_replace('spotify:track:', '', $lastUri);
                        try {
                            $track = $spotify->api->getTrack($trackId);
                            if ($track) {
                                $response = [
                                    'success' => true,
                                    'data' => [
                                        'name' => $track->name,
                                        'artist' => implode(', ', array_map(fn($artist) => $artist->name, $track->artists)),
                                        'album_image' => $track->album->images[0]->url ?? null,
                                        'uri' => $lastUri
                                    ]
                                ];
                            } else {
                                $response = ['success' => false, 'message' => 'Nenhuma faixa encontrada'];
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao buscar faixa: " . $e->getMessage());
                            $response = ['success' => false, 'message' => 'Erro ao buscar faixa'];
                        }
                    } else {
                        $response = ['success' => false, 'message' => 'Nenhuma faixa ou episódio em reprodução no momento'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Nenhuma faixa ou episódio em reprodução no momento'];
                }
            }
            break;

        case 'get_current_playback':
            // Obtém o estado atual da reprodução
            $playback = $spotify->getCurrentPlayback();
            $response = ['success' => true, 'data' => $playback];
            break;

        default:
            // Retorna erro para ações inválidas
            $response = ['success' => false, 'message' => 'Ação inválida'];
            break;
    }
} catch (Exception $e) {
    // Captura qualquer exceção não tratada e retorna como erro
    $response = ['success' => false, 'error' => 'Erro ao processar a solicitação: ' . $e->getMessage()];
}

// Limpa qualquer saída anterior no buffer
ob_clean();
// Retorna a resposta em formato JSON
echo json_encode($response);
// Finaliza a execução
exit;