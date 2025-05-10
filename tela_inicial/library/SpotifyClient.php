<?php

namespace spotify\tela_inicial\library;


require __DIR__ . '/../source/config.php';

use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\Session;

class SpotifyClient
{
    public readonly Session $spotify;
    public readonly SpotifyWebAPI $api;

    private ?object $user = null;

    public function __construct()
    {
        if (!defined('app_id') || !defined('app_secret') || !defined('app_redirect_Uri')) {
            throw new \Exception("Constantes de configuração do Spotify não definidas em config.php");
        }
        // Configurações do aplicativo Spotify a partir do config.php
        $this->spotify = new Session(
            app_id,
            app_secret,
            app_redirect_Uri
        );

        // Inicializa a API do Spotify
        $this->api = new SpotifyWebAPI();
    }

    public function getAccessToken(){
        return $this->spotify->getAccessToken();
    }


    public function authorized()
    {
        if (!isset($_GET['code'])) {
            error_log("Nenhum código de autorização recebido na URL.");
            return false;
        }
        // Verifica se o código de autorização foi retornado
        if (isset($_GET['code'])) {
            try {
                // Obtém o token de acesso usando o código de autorização
                $this->spotify->requestAccessToken($_GET['code']);
                $accessToken = $this->spotify->getAccessToken();
                $refreshToken = $this->spotify->getRefreshToken();

                if (empty($accessToken)) {
                    error_log("Token de acesso retornado está vazio.");
                    return false;
                }

                // Configura o token na API
                $this->api->setAccessToken($accessToken);

                // Obtém os dados do usuário autenticado
                $this->user = $this->api->me();

                // Salva o token na sessão
                session_start(); // Inicia a sessão (se não estiver iniciada)
                $_SESSION['spotify_access_token'] = $accessToken;
                $_SESSION['spotify_refresh_token'] = $refreshToken; // Salva o refresh token
                error_log("Token salvo na sessão: " . $accessToken);

                return true;
            } catch (\Exception $e) {
                // Log de erro caso algo falhe
                error_log("Erro ao obter token de acesso do Spotify: " . $e->getMessage());
                return false;
            }
        }

        // Retorna falso se o código não estiver presente
        return false;
    }

    public function getUser()
    {
        // Retorna os dados do usuário autenticado
        return $this->user;
    }

    public function generateAuthLink()
    {

        // Gera o link de autenticação com escopos definidos
        $options = [
            'scope' => [
                'user-read-email',           // Ler email do usuário
                'user-read-private',         // Ler dados privados do usuário
                'playlist-modify-public',    // Gerenciar playlists públicas
                'playlist-modify-private',   // Gerenciar playlists privadas
                'user-read-playback-position',
                'user-read-playback-state',
                'user-modify-playback-state',
                'streaming',
                'user-read-recently-played'


            ],
            'state' => 'auth=spotify' // Proteção CSRF
        ];
        return $this->spotify->getAuthorizeUrl($options);
    }

    public function refreshToken()
    {
        session_start();
        if (isset($_SESSION['spotify_refresh_token'])) {
            try {
                $this->spotify->refreshAccessToken($_SESSION['spotify_refresh_token']);
                $newAccessToken = $this->spotify->getAccessToken();
                $_SESSION['spotify_access_token'] = $newAccessToken;
                $this->api->setAccessToken($newAccessToken);
                $this->user = $this->api->me();
                error_log("Token renovado: " . $newAccessToken);
                return true;
            } catch (\Exception $e) {
                error_log("Erro ao renovar token: " . $e->getMessage());
                return false;
            }
        }
        error_log("Nenhum refresh token disponível.");
        return false;
    }

    // Método adicional para reusar o token já salvo
    public function setAccessTokenFromSession()
    {

        if (isset($_SESSION['spotify_access_token']) && !empty($_SESSION['spotify_access_token'])) {
            $this->api->setAccessToken($_SESSION['spotify_access_token']);
            try {
                $this->user = $this->api->me();
                return true;
            } catch (\Exception $e) {
                error_log("Erro ao usar token da sessão: " . $e->getMessage());
                return false;
            }
        }
        error_log("Nenhum token válido encontrado na sessão.");
        return false;
    }

    



    public function  authenticateWithRefreshToken($refreshToken)
    {
        try {
            $this->spotify->refreshAccessToken($refreshToken);
            $newAccessToken = $this->spotify->getAccessToken();
            if (empty($newAccessToken)) {
                error_log("Token de acesso renovado está vazio.");
                return false;
            }

            $this->api->setAccessToken($newAccessToken);
            error_log("Token de acesso renovado com sucesso: $newAccessToken");
            try {
                $this->user = $this->api->me();
            } catch (\Exception $e) {
                error_log("Erro ao obter dados do usuário após renovação: " . $e->getMessage());
            }
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao renovar token de acesso: " . $e->getMessage());
            return false;
        }
    }

    // Métodos adicionais para controle de reprodução
    public function play($options = [])
    {
        $deviceId = $options['device_id'] ?? null;
        $contextUri = $options['context_uri'] ?? null;
        $uris = $options['uris'] ?? null;

        // Monta o corpo da requisição
        $body = [];
        if ($contextUri) {
            $body['context_uri'] = $contextUri;
        } elseif ($uris) {
            $body['uris'] = (array) $uris;
        }

        // Converte para JSON e garante que não será falso
        $bodyJson = json_encode($body) ?: '{}'; // Se json_encode falhar, usa '{}'

        // Faz a requisição para iniciar a reprodução
        if ($deviceId) {
            $this->api->play($deviceId, json_decode($bodyJson, true)); // Converte de volta para array
        } else {
            $this->api->play(json_decode($bodyJson, true)); // Converte de volta para array
        }
    }

    public function pause($options = [])
    {
        $deviceId = $options['device_id'] ?? null;
        $this->api->pause($deviceId);
    }

    public function back($options = [])
    {
        $deviceId = isset($options['device_id']) ? $options['device_id'] : null;

        try {
            // Verifica o estado atual do player
            $playback = $this->api->getMyCurrentPlaybackInfo();
            if (!$playback || !$playback->device || ($deviceId && $playback->device->id !== $deviceId)) {
                throw new \Exception("Dispositivo $deviceId não está ativo ou não foi encontrado.");
            }

            // Armazena a URI atual para verificar se back retrocedeu
            $currentUri = $playback->item ? $playback->item->uri : null;

            if ($playback && $playback->is_playing === false) {
                // Se o player está pausado, retoma a reprodução
                $this->api->play($deviceId);

                $playbackCheck = $this->api->getMyCurrentPlaybackInfo();
                if (!$playbackCheck || $playbackCheck->is_playing === false) {
                    throw new \Exception("Não foi possível retomar a reprodução antes de retroceder.");
                }
            }

            // Chama a ação previous
            $this->api->previous($deviceId);


            // Verifica se a faixa mudou
            $newPlayback = $this->api->getMyCurrentPlaybackInfo();
            $newUri = $newPlayback->item ? $newPlayback->item->uri : null;
            if ($currentUri && $newUri === $currentUri) {
                // Se a faixa não mudou, tenta retomar a playlist
                if (isset($_SESSION['current_playlist_uri'])) {
                    $this->api->play($deviceId, ['context_uri' => $_SESSION['current_playlist_uri']]);

                    $newPlayback = $this->api->getMyCurrentPlaybackInfo();
                    $newUri = $newPlayback->item ? $newPlayback->item->uri : null;
                    if ($newUri === $currentUri) {
                        throw new \Exception("Ação back não retrocedeu a faixa e playlist não pôde ser retomada.");
                    }
                } else {
                    throw new \Exception("Ação back não retrocedeu a faixa e nenhum contexto de playlist disponível.");
                }
            }

            return true;
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            error_log("Erro ao chamar next: " . $e->getMessage());
            throw new \Exception("Erro ao avançar para a próxima faixa: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Erro inesperado ao chamar next: " . $e->getMessage());
            throw new \Exception("Erro inesperado ao avançar para a próxima faixa: " . $e->getMessage());
        }
    }

    public function next($options = [])
    {
        $deviceId = isset($options['device_id']) ? $options['device_id'] : null;

        try {
            // Verifica o estado atual do player
            $playback = $this->api->getMyCurrentPlaybackInfo();
            if (!$playback || !$playback->device || ($deviceId && $playback->device->id !== $deviceId)) {
                throw new \Exception("Dispositivo $deviceId não está ativo ou não foi encontrado.");
            }

            // Armazena a URI atual para verificar se next avançou
            $currentUri = $playback->item ? $playback->item->uri : null;

            if ($playback && $playback->is_playing === false) {
                // Se o player está pausado, retoma a reprodução
                $this->api->play($deviceId);
            }

            // Chama a ação next
            $this->api->next($deviceId);


            // Verifica se a faixa mudou
            $newPlayback = $this->api->getMyCurrentPlaybackInfo();
            $newUri = $newPlayback->item ? $newPlayback->item->uri : null;
            if ($currentUri && $newUri === $currentUri) {
                // Se a faixa não mudou, tenta retomar a playlist
                if (isset($_SESSION['current_playlist_uri'])) {
                    $this->api->play($deviceId, ['context_uri' => $_SESSION['current_playlist_uri']]);

                    $newPlayback = $this->api->getMyCurrentPlaybackInfo();
                    $newUri = $newPlayback->item ? $newPlayback->item->uri : null;
                    if ($newUri === $currentUri) {
                        throw new \Exception("Ação next não avançou a faixa e playlist não pôde ser retomada.");
                    }
                } else {
                    throw new \Exception("Ação next não avançou a faixa e nenhum contexto de playlist disponível.");
                }
            }

            return true;
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            error_log("Erro ao chamar next: " . $e->getMessage());
            throw new \Exception("Erro ao avançar para a próxima faixa: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Erro inesperado ao chamar next: " . $e->getMessage());
            throw new \Exception("Erro inesperado ao avançar para a próxima faixa: " . $e->getMessage());
        }
    }

    public function setVolume($volume, $options = [])
    {
        $deviceId = $options['device_id'] ?? null;
        $this->api->changeVolume(['volume_percent' => $volume], $deviceId ? ['device_id' => $deviceId] : []);
    }

    public function getCurrentPlayback()
    {
        return $this->api->getMyCurrentPlaybackInfo();
    }

    public function seek($positionMs, $options = [])
    {
        $deviceId = $options['device_id'] ?? null;
        $positionMs = (int)$positionMs;


        if ($positionMs < 0) {
            throw new \Exception("A posição (position_ms) deve ser um valor não negativo.");
        }

        // Monta os parâmetros da requisição
        $params = ['position_ms' => $positionMs];

        // Faz a requisição para ajustar a posição de reprodução
        if ($deviceId) {
            $this->api->seek($params, $deviceId);
        }
    }

    public function getMyCurrentTrack($options = [])
    {
        try {
            $track = $this->api->getMyCurrentTrack($options);

            // Fallback para getMyCurrentPlaybackInfo se item for null
            if (!$track || !isset($track->item)) {

                $playback = $this->api->getMyCurrentPlaybackInfo($options);
                if ($playback && isset($playback->item)) {
                    return $playback;
                } else {
                    print_r("Fallback falhou: item ainda nulo ou playback inválido");
                }
            }

            return $track;
        } catch (\Exception $e) {
            print_r("Erro em getMyCurrentTrack: " . $e->getMessage());
            return null;
        }
    }

    public function authenticateClientCredentials()
    {
        try {
            // Solicita um token usando Client Credentials
            $this->spotify->requestCredentialsToken();
            $accessToken = $this->spotify->getAccessToken();
            if (empty($accessToken)) {
                error_log("Token de Client Credentials retornado está vazio.");
                return false;
            }
            // Configura o token na API
            $this->api->setAccessToken($accessToken);
            error_log("Token de Client Credentials configurado: " . $accessToken);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao obter token de Client Credentials: " . $e->getMessage());
            return false;
        }
    }
   
}
