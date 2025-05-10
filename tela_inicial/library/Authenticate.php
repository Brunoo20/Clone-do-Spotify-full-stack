<?php

namespace spotify\tela_inicial\library;
use spotify\tela_inicial\library\SpotifyClient;

class Authenticate
{
    public function logout($authType = null){

        // Inicia a sessão para acessar os dados armazenados
        session_start();

        // Verifica se o tipo de autenticação é "spotify" ou nulo (padrão)
        if($authType === null || $authType === 'spotify'){
            // Remove os tokens da sessão
            unset($_SESSION['spotify_access_token']);
            unset($_SESSION['spotify_refresh_token']);

            session_destroy();

            header('Location: /spotify/firstPage.php');

            exit;
        }
        
        return false;

    }

}