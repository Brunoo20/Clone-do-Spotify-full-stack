<?php
session_start();
require '../../vendor/autoload.php'; // Ajuste o caminho conforme sua estrutura
use spotify\tela_inicial\library\SpotifyClient;

header('Content-Type: application/json');

$spotify = new SpotifyClient();
$tokenInSession = $_SESSION['spotify_access_token'] ?? 'Não encontrado';
error_log("Token na sessão em Get_artist_data.php: " . $tokenInSession);

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

$rawInput = file_get_contents('php://input');
error_log("Corpo da requisição recebido: " . $rawInput);

$data = json_decode($rawInput, true);
error_log("Dados decodificados: " . print_r($data, true));

$artistId = $data['artist_id'] ?? '';
error_log("artistId extraído: " . $artistId);

if (empty($artistId)) {
    echo json_encode(['success' => false, 'error' => 'Consulta vazia']);
    exit;
}

try {
    // Busca os dados do artista
    $artistData = $spotify->api->getArtist($artistId);

    // Busca as músicas populares do artista
    $topTracks = $spotify->api->getArtistTopTracks($artistId, ['market' => 'US']);
    $topTracksArray = $topTracks->tracks;

    // Busca os álbuns do artista com paginação
    $allAlbums = [];
    $offset = 0;
    $limit = 50;

    do {
        $artistAlbums = $spotify->api->getArtistAlbums($artistId, [
            'include_groups' => 'album,single,compilation,appears_on',
            'limit' => $limit,
            'market' => 'US',
            'offset' => $offset
        ]);

        error_log("Resposta bruta da API getArtistAlbums (offset $offset): " . json_encode($artistAlbums, JSON_PRETTY_PRINT));

        $allAlbums = array_merge($allAlbums, $artistAlbums->items);
        $offset += $limit;
    } while (isset($artistAlbums->next) && $artistAlbums->next !== null);

    error_log("Total de álbuns retornados após paginação: " . count($allAlbums));

    // Organiza os álbuns por tipo
    $albumsByType = [
        'albums' => [],
        'single' => [],
        'compilation' => []
    ];

    // Mapeia álbuns com suas faixas populares
    $albumPopularity = []; // Array para associar álbum ID à popularidade
    foreach ($allAlbums as $album) {
        $albumPopularity[$album->id] = 0; // Inicializa popularidade
        error_log("Processando álbum: $album->name, tipo: $album->album_type, ID: $album->id");
        switch ($album->album_type) {
            case 'album':
                $albumsByType['albums'][] = [
                    'id' => $album->id,
                    'name' => $album->name,
                    'release_date' => $album->release_date,
                    'image_url' => $album->images[0]->url ?? 'default.jpg',
                    'total_tracks' => $album->total_tracks
                ];
                break;
            case 'single':

                $albumsByType['single'][] = [
                    'id' => $album->id,
                    'name' => $album->name,
                    'release_date' => $album->release_date,
                    'image_url' => $album->images[0]->url ?? 'default.jpg',
                    'total_tracks' => $album->total_tracks
                ];
                break;
            case 'compilation':

                $albumsByType['compilation'][] = [
                    'id' => $album->id,
                    'name' => $album->name,
                    'release_date' => $album->release_date,
                    'image_url' => $album->images[0]->url ?? 'default.jpg',
                    'total_tracks' => $album->total_tracks
                ];
                break;
            default:
                error_log("Tipo de álbum não reconhecido: $album->album_type para $album->name");
                break;
        }
    }

    // Associa popularidade às faixas mais populares
    foreach ($topTracksArray as $track) {
        $albumId = $track->album->id;
        if (isset($albumPopularity[$albumId])) {
            // Usa a popularidade da faixa (0 a 100) como métrica
            $albumPopularity[$albumId] = max($albumPopularity[$albumId], $track->popularity);
            error_log("Associando popularidade $track->popularity ao álbum $albumId ($track->name)");
        }
    }

    // Combina álbuns de todos os tipos em uma lista única para ordenação por popularidade
    $allReleases = array_merge($albumsByType['albums'], $albumsByType['single'], $albumsByType['compilation']);

    // Ordena os álbuns pela popularidade (decrescente)
    usort($allReleases, function ($a, $b) use ($albumPopularity) {
        $popA = $albumPopularity[$a['id']] ?? 0;
        $popB = $albumPopularity[$b['id']] ?? 0;
        error_log("Comparando $a[name] ($popA) com $b[name] ($popB)");
        return $popB - $popA; // Ordem decrescente por popularidade
    });

    // Seleciona os 8 lançamentos mais populares
    $popularReleases = array_slice($allReleases, 0, 8);
    $Albums = array_slice($albumsByType, 0, 8);
    error_log("Popular releases por popularidade: " . print_r($popularReleases, true));

    // Busca artistas parecidos por gênero
    $genres = $artistData->genres ?? [];
    $similarArtists = [];

    if (!empty($genres)) {
        // Usa até 2 gêneros principais para a busca (para evitar queries muito amplas)
        $searchGenres = array_slice($genres, 0, 10);
        $query = implode(' ', $searchGenres);  // Ex.: "pop rock"
        error_log("Buscando artistas com gêneros: " . $query);

        // Faz a busca por artistas usando o endpoint de busca
        $searchResults = $spotify->api->search($query, 'artist', [
            'market' => 'US',
            'limit' => 50 // Limita a 20 resultados para evitar excesso
        ]);

        $foundArtists = $searchResults->artists->items ?? [];
        error_log("Artistas encontrados: " . count($foundArtists));

        foreach ($foundArtists as $foundArtist) {
            // Evita incluir o próprio artista na lista de similares
            if ($foundArtist->id === $artistId) {
                continue;
            }

            // Verifica se há interseção de gêneros
            $foundGenres = $foundArtist->genres ?? [];
            $commonGenres = array_intersect($genres, $foundGenres);
            if (!empty($commonGenres)) {
                $similarArtists[] = [
                    'id' => $foundArtist->id,
                    'name' => $foundArtist->name,
                    'genres' => $foundGenres,
                    'popularity' => $foundArtist->popularity ?? 0,
                    'image_url' => !empty($foundArtist->images) ? $foundArtist->images[0]->url : 'default.jpg'
                ];
            }
        }

        // Ordena os artistas similares por popularidade (decrescente)
        usort($similarArtists, function ($a, $b) {
            return $b['popularity'] - $a['popularity'];
        });

        // Limita a 45 artistas similares
        $similarArtists = array_slice($similarArtists, 0 , 45);
        error_log("Artistas similares encontrados: " . print_r($similarArtists, true));
    }else{
        error_log("Nenhum gênero encontrado para o artista $artistId");
    }
    

    // Obtém a maior imagem disponível do artista
    $imageUrl = !empty($artistData->images) ? $artistData->images[0]->url : 'default.jpg';

    // Prepara a resposta
    $response = [
        'success' => true,
        'data' => [
            'name' => $artistData->name,
            'followers' => $artistData->followers->total,
            'tracks' => array_slice($topTracks->tracks, 0, 10),
            'image_url' => $imageUrl,
            'albums' => $albumsByType,
            'popular_releases' => $popularReleases,
            'similar_artists' => $similarArtists // Adiciona os artistas similares à resposta
        ]
    ];

    error_log("Resposta final enviada: " . json_encode($response, JSON_PRETTY_PRINT));
    echo json_encode($response);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
