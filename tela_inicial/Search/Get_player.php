<?php
session_start();

require '../../vendor/autoload.php';

use spotify\tela_inicial\library\SpotifyClient;

header('Content-type: application/json');

$spotify = new SpotifyClient;
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';
error_log("Token na sessão em Get_player.php: " . $tokenInSession);

if (!$spotify->setAccessTokenFromSession()) {
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado',
        'debug' => [
            'token' => $tokenInSession,
            'session' => print_r($_SESSION, true)
        ]
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;
$query = $input['query'] ?? null;
$volume = $input['volume'] ?? null;
$deviceId = $input['device_id'] ?? null;
$episodeUri = $input['episode_uri'] ?? null;
$positionMs = $input['position_ms'] ?? null;

$response = ['success' => false, 'message' => 'Ação não especificada'];

try {
    switch ($action) {
        case 'get_token':
            $response = [
                'success' => true,
                'token' => $tokenInSession
            ];
            break;

        case 'get_artist_top_tracks':
            $artistId = $input['artist_id'] ?? null;
            if (!$artistId) {
                $response = ['success' => false, 'message' => 'ID do artista não fornecido'];
            } else {
                $topTracks = $spotify->api->getArtistTopTracks($artistId, ['market' => 'US']);
                if ($topTracks && !empty($topTracks->tracks)) {
                    $tracks = array_map(function ($track) {
                        return [
                            'uri' => $track->uri,
                            'name' => $track->name,
                            'artist' => $track->artists[0]->name,
                            'artist_id' => $track->artists[0]->id,
                            'album_image' => $track->album->images[0]->url ?? null
                        ];
                    }, $topTracks->tracks);
                    $response = ['success' => true, 'tracks' => $tracks];
                    $_SESSION['artist_top_tracks'] = $tracks; // Armazena na sessão
                } else {
                    $response = ['success' => false, 'message' => 'Nenhuma música encontrada para o artista'];
                }
            }
            break;

        case 'play':
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
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

            $spotify->api->changeMyDevice(['device_ids' => [$deviceId], 'play' => false]);
            $options = ['device_id' => $deviceId];

            if ($episodeUri) {
                $options['uris'] = [$episodeUri];
                if ($positionMs !== null) {
                    $options['position_ms'] = (int)$positionMs;
                }
                $_SESSION['last_uri'] = $episodeUri; // Armazena qualquer URI reproduzido
                $_SESSION['queue'] = null; // Limpa a fila para podcasts
            } elseif ($query) {
                $options['context_uri'] = $query;
                $_SESSION['last_uri'] = $query; // Armazena context_uri se aplicável
                $_SESSION['queue'] = null;  // Limpa a fila para contextos
            } elseif (isset($input['uris']) && !empty($input['uris'])) {
                $options['uris'] = $input['uris'];
                if ($positionMs !== null) {
                    $options['position_ms'] = (int)$positionMs;
                }
                $_SESSION['last_uri'] = $input['uris'][0];  // Armazena o primeiro URI
                $_SESSION['queue'] = $input['uris']; // Armazena a fila completa
            } elseif (isset($_SESSION['last_uri'])) {
                $options['uris'] = [$_SESSION['last_uri']];
                if ($positionMs !== null) {
                    $options['position_ms'] = (int)$positionMs;
                }
                $_SESSION['queue'] = null; // Sem fila para reprodução única
            } else {
                $options['uris'] = ['spotify:track:4iV5W9uYEdYUVa79Axb7Rh'];
                $_SESSION['last_uri'] = $options['uris'][0];
                $_SESSION['queue'] = null;
            }

            $spotify->play($options);

            $volume = isset($input['volume']) ? max(0, min(100, (int)$input['volume'])) : 100;
            $spotify->setVolume($volume, ['device_id' => $deviceId]);

            // Tenta obter o estado atual da reprodução
            $currentTrack = $spotify->api->getMyCurrentTrack(['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => 'Reprodução iniciada'];


            if ($currentTrack && $currentTrack->item) {
                $item = $currentTrack->item;
                $response['data'] = [
                    'name' => $item->name,
                    'artist' => $item->type === 'track' ? implode(', ', array_map(fn($artist) => $artist->name, $item->artists)) : $item->show->name,
                    'album_image' => $item->type === 'track' ? ($item->album->images[0]->url ?? null) : ($item->images[0]->url ?? null),
                    'uri' => $item->uri
                ];
            } else {
                // Tenta usar o último URI reproduzido como fallback
                $lastUri = $_SESSION['last_uri'] ?? null;
                if ($lastUri) {
                    if (strpos($lastUri, 'spotify:episode:') === 0) {
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
                    } else if (strpos($lastUri, 'spotify:track:') === 0) {
                        $trackId = str_replace('spotify:track', '', $lastUri);
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
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
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
            $spotify->pause(['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => 'Reprodução pausada'];
            break;
        case 'back':
            try {
                // Verifica se o dispositivo está ativo
                if (!$deviceId) {
                    throw new Exception('Device ID não fornecido');
                }
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

                // Chama o método back
                $spotify->back(['device_id' => $deviceId]);

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
                } else {
                    // Fallback para o último URI
                    $lastUri = $_SESSION['last_uri'] ?? null;
                    if ($lastUri) {
                        if (strpos($lastUri, 'spotify:episode') === 0) {
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
                // Verifica se o dispositivo está ativo
                if (!$deviceId) {
                    throw new Exception('Device ID não fornecido');
                }
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

                // Chama o método next
                $spotify->next(['device_id' => $deviceId]);

                // Tenta obter o estado atual da reprodução (com até 3 tentativas)
                $currentTrack = null;
                $attempts = 0;
                $maxAttempts = 3;
                while ($attempts < $maxAttempts) {
                    $currentTrack = $spotify->getMyCurrentTrack(['device_id' => $deviceId]);
                    if ($currentTrack && $currentTrack->item) {
                        break;
                    }

                    $attempts++;
                }

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
                    // Fallback para o último URI
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
            if ($volume === null) {
                throw new Exception('Volume não especificado');
            }
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
            $user = $spotify->getUser();
            if (isset($user->product) && $user->product !== 'premium') {
                throw new Exception('Esta funcionalidade requer uma conta Spotify Premium.');
            }
            $volume = max(0, min(100, (int)$volume));
            $spotify->setVolume($volume, ['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => "Volume ajustado para $volume%"];
            break;

        case 'seek':
            if ($positionMs === null) {
                throw new Exception('Posição (position_ms) não especificada');
            }
            if (!$deviceId) {
                throw new Exception('Device ID não fornecido');
            }
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
            $spotify->seek((int)$positionMs, ['device_id' => $deviceId]);
            $response = ['success' => true, 'message' => "Posição ajustada para $positionMs ms"];
            break;
        case 'repeat_mode':
            try {
                $state = $input['state'] ?? null;
                $deviceId = $input['device_id'] ?? null;

                if (!$state) {
                    throw new Exception('Estado de repetição não especificado');
                }
                if (!$deviceId) {
                    throw new Exception('Device ID não fornecido');
                }

                // Log dos parâmetros recebidos
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

                // Chama a função repeatMode definida em SpotifyClient.php
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
            $currentTrack = $spotify->getMyCurrentTrack($deviceId ? ['device_id' => $deviceId] : []);
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
                // Usa o último URI reproduzido como fallback
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
                    } else if (strpos($lastUri, 'spotify:track:') === 0) {
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
            $playback = $spotify->getCurrentPlayback();
            $response = ['success' => true, 'data' => $playback];
            break;

        default:
            $response = ['success' => false, 'message' => 'Ação inválida'];
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => 'Erro ao processar a solicitação: ' . $e->getMessage()];
}

ob_clean();
echo json_encode($response);
exit;
