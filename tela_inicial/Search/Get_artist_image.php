<?php
session_start();
require '../../vendor/autoload.php';// Ajuste o caminho para o autoloader
use spotify\tela_inicial\library\SpotifyClient;

header('Content-Type: application/json');

$spotify = new SpotifyClient();
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';
error_log("Token na sessão em Get_artist_image.php: " . $tokenInSession);

if (!$spotify->setAccessTokenFromSession()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado', 'debug' => 'Token: ' . $tokenInSession]);
    exit;
}

// Ler o corpo da requisição (JSON) e depurar
$input = file_get_contents('php://input');
error_log("Corpo da requisição: " . $input);

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro ao decodificar JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar a requisição', 'debug' => 'Input: ' . $input]);
    exit;
}

$artistId = $data['artist_id'] ?? '';

if (empty($artistId)) {
    echo json_encode(['success' => false, 'error' => 'ID do artista não fornecido', 'debug' => 'Artist ID: ' . $artistId . ', Input: ' . $input]);
    exit;
}

try {
    $artist = $spotify->api->getArtist($artistId);
    error_log("Resposta da API para artista $artistId: " . print_r($artist, true));
    $image = $artist->images[0]->url ?? null;
    echo json_encode(['success' => true, 'artist' => $artist, 'image' => $image]);
} catch (\Exception $e) {
    error_log("Erro ao buscar artista $artistId: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => 'Artist ID: ' . $artistId]);
}