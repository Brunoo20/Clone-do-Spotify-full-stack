<?php
// Inicia a sessão para acessar os dados salvos (como o token de autenticação)
session_start();

// Carrega automaticamente as classes com o Composer
require '../vendor/autoload.php';

// Usa a classe SpotifyClient localizada no namespace especificado
use spotify\tela_inicial\library\SpotifyClient;

// Define o tipo de conteúdo da resposta como JSON
header('Content-Type: application/json');

// Cria uma instância do cliente do Spotify
$spotify = new SpotifyClient();

// Tenta definir o token de acesso a partir da sessão; se não conseguir, retorna erro
if (!$spotify->setAccessTokenFromSession()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Lê os dados recebidos via POST (esperando JSON no corpo da requisição)
$data = json_decode(file_get_contents('php://input'), true);

// Extrai o ID da faixa (track_id) do corpo da requisição; se não vier, usa string vazia
$trackId = $data['track_id'] ?? '';

// Verifica se o ID da faixa foi fornecido
if (empty($trackId)) {
    echo json_encode(['success' => false, 'error' => 'ID da faixa não fornecido']);
    exit;
}

try {
    // Solicita à API do Spotify que toque a faixa com o ID fornecido
    $spotify->api->play("spotify:track:$trackId");

    // Retorna sucesso
    echo json_encode(['success' => true]);

} catch (\Exception $e) {
    // Em caso de erro, retorna a mensagem da exceção
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
