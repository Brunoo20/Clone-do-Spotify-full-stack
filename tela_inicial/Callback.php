<?php

require '../vendor/autoload.php';

use spotify\tela_inicial\library\SpotifyClient;

$spotify = new SpotifyClient();


if ($spotify->authorized()) {
    $user = $spotify->getUser();

    // Salvar o refresh token em um arquivo para uso no CLI
    $refreshToken = $_SESSION['spotify_refresh_token'];
    $filePath = __DIR__ . '/spotify_refresh_token.txt';
    

    try {
        file_put_contents($filePath, $refreshToken);
        error_log("Refresh token salvo em: $filePath");
    } catch (\Exception $e) {
        error_log("Erro ao salvar refresh token: " . $e->getMessage());
    }


    header('Location:index.php');
} else {
    echo "Falha na autenticação. Verifique o log de erros.";
}
