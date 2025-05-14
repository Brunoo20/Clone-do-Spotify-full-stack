<?php
// Inicia a sessão para armazenar ou recuperar dados do usuário
session_start();

// Carrega as dependências gerenciadas pelo Composer (autoloader)
require 'vendor/autoload.php';

// Importa as classes necessárias do namespace
use spotify\tela_inicial\library\Authenticate;
use spotify\tela_inicial\library\SpotifyClient;

// Cria uma instância do cliente Spotify
$spotify = new SpotifyClient();

// Gera o link de autenticação do Spotify (para login)
$authUrl = $spotify->generateAuthLink();

// Verifica se o token de acesso está presente na sessão e tenta configurá-lo
$isAuthenticated = $spotify->setAccessTokenFromSession();

// Se autenticado, obtém os dados do usuário atual
$user = $isAuthenticated ? $spotify->getUser() : null;

// Cria uma instância da classe responsável pela autenticação
$auth = new Authenticate();

// Verifica se a URL contém o parâmetro `logout` para encerrar a sessão
if (isset($_GET['logout'])) {
    $authType = $_GET['auth'] ?? null; // Tipo de autenticação (opcional)
    $auth->logout($authType);          // Chama o método de logout apropriado
}
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://kit.fontawesome.com/0ed6f7bf05.js" crossorigin="anonymous"></script>
    <script defer src="js/script.js"></script>
    <link rel="icon" type="image/png" href="tela_inicial/img/spotify-logo.png">
    <title>Spotify</title>
</head>

<body>

    <div class="container">
        <nav class="sidebar">
            <div class="nav-logo">
                <i class="fa-brands fa-spotify"></i>
            </div>
            <div class="nav-library">
                <i class="fa-solid fa-book-open"></i>
                <p>Sua Biblioteca +</p>
            </div>
            <div class="nav-playlist">
                <h5>Crie sua Primeira Playlist</h5>
                <p>É fácil, vamos te ajudar</p>
                <button> Criar Playlist</button>
            </div>
            <div class="nav-podcast">
                <h5>Que tal Seguir um Podcast?</h5>
                <p>Avisaremos você sobre nossos episódios</p>
                <button>Explore Podcast</button>
            </div>
            <div class="nav-footer">
                <a href="#">Legal</a>
                <a href="#">Centro de Privacidade</a>
                <a href="#">Política de Privacidade</a>
                <a href="#">Cookies</a>
                <a href="#">Sobre anúncios</a>
                <a href="#">Acessibilidade</a>
            </div>
            <button class="nav-lang-button">
                <i class="fa-solid fa-globe"></i>
                Português do Brasil

            </button>


        </nav>
        <main>
            <header class="top-bar">
                <div class="house">
                    <i class="fa-solid fa-house"></i>
                </div>

                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input placeholder="O que você quer ouvir?">
                    <i class="fa-solid fa-basket-shopping"></i>
                </div>
                <div class="nav-buttons">
                    <a href="https://www.spotify.com/signup" class="nav-button-signup"> Inscrever-se</a>
                    <a href="<?= $authUrl ?>" class="nav-button-login">Entrar</a>
                </div>


            </header>


            <section class="content">
                <div class="content-section">
                    <h2>Artistas Populares</h2>
                    <div class="artists-grid">

                    </div>



                </div>

                <div class="content-section">
                    <h2>Álbuns Populares</h2>
                    <div class="albums-grid">

                    </div>

                </div>


                <footer class="footer">
                    <div class="footer-container">
                        <div class="footer-content">
                            <div class="footer-sections">
                                <div class="footer-section">
                                    <h3>Empresa</h3>
                                    <ul>
                                        <li><a href="#">Sobre</a></li>
                                        <li><a href="#">Empregos</a></li>
                                        <li><a href="#">For the Record</a></li>
                                    </ul>
                                </div>

                                <div class="footer-section">
                                    <h3>Comunidades</h3>
                                    <ul>
                                        <li><a href="#">Para Artistas</a></li>
                                        <li><a href="#">Desenvolvedores</a></li>
                                        <li><a href="#">Publicidade</a></li>
                                        <li><a href="#">Investidores</a></li>
                                        <li><a href="#">Fornecedores</a></li>
                                    </ul>
                                </div>

                                <div class="footer-section">
                                    <h3>Links úteis</h3>
                                    <ul>
                                        <li><a href="#">Suporte</a></li>
                                        <li><a href="#">Aplicativo móvel grátis</a></li>
                                    </ul>
                                </div>

                                <div class="footer-section">
                                    <h3>Planos do Spotify</h3>
                                    <ul>
                                        <li><a href="#">Premium Individual</a></li>
                                        <li><a href="#">Premium Duo</a></li>
                                        <li><a href="#">Premium Família</a></li>
                                        <li><a href="#">Premium Universitário</a></li>
                                        <li><a href="#">Spotify Free</a></li>
                                    </ul>
                                </div>

                            </div>

                        </div>

                        <div class="social-media">
                            <a href="https://www.instagram.com/spotify/"><i class="fa-brands fa-instagram"></i></a>
                            <a href="https://x.com/spotify?mx=2"><i class="fa-brands fa-twitter"></i></a>
                            <a href="https://www.facebook.com/Spotify"><i class="fa-brands fa-facebook"></i></a>

                        </div>

                    </div>


                    <div class="footer-copy">
                        <p>@ 2024 Spotify AB</p>
                    </div>




                </footer>

            </section>



        </main>


    </div>

    <div class="banner">
        <div>
            <h2>Testar o Premium de graça</h2>
            <p>Inscreva-se para curtir músicas ilimitadas só com alguns anúncios. Não precisa de um cartão de crédito
            </p>
        </div>
        <button>Inscreva-se grátis</button>
    </div>







</body>

</html>