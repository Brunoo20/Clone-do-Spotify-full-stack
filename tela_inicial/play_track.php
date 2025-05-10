<?php
session_start();
require '../vendor/autoload.php';
use spotify\tela_inicial\library\SpotifyClient;

header('Content-Type: application/json');

$spotify = new SpotifyClient();
if (!$spotify->setAccessTokenFromSession()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$trackId = $data['track_id'] ?? '';

if (empty($trackId)) {
    echo json_encode(['success' => false, 'error' => 'ID da faixa não fornecido']);
    exit;
}

try {
    $spotify->api->play("spotify:track:$trackId");
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}