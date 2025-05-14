<?php

// Carrega automaticamente as classes do projeto via Composer
require '../vendor/autoload.php';

// Importa a classe responsável por lidar com a API do Spotify
use spotify\tela_inicial\library\SpotifyClient;

// Cria uma instância do cliente Spotify
$spotify = new SpotifyClient();

// Verifica se o usuário está autorizado (ou seja, autenticado com sucesso)
if ($spotify->authorized()) {

    // Obtém os dados do usuário autenticado
    $user = $spotify->getUser();

    // Salvar o refresh token em um arquivo para uso no CLI
    $refreshToken = $_SESSION['spotify_refresh_token'];

    // Define o caminho do arquivo onde o refresh token será salvo
    $filePath = __DIR__ . '/spotify_refresh_token.txt';


    try {
        // Salva o refresh token no arquivo
        file_put_contents($filePath, $refreshToken);

        // Registra no log que o token foi salvo com sucesso
        error_log("Refresh token salvo em: $filePath");
    } catch (\Exception $e) {
        // Em caso de erro ao salvar o token, registra no log
        error_log("Erro ao salvar refresh token: " . $e->getMessage());
    }


    header('Location:index.php');
} else {
    echo "Falha na autenticação. Verifique o log de erros.";
}
