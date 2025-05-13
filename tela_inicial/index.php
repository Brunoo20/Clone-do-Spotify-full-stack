<?php
session_start();

require '../vendor/autoload.php';

use spotify\tela_inicial\library\Authenticate;
use spotify\tela_inicial\library\SpotifyClient;



$spotify = new SpotifyClient();
$authUrl = $spotify->generateAuthLink();
$isAuthenticated = $spotify->setAccessTokenFromSession();
$user = $isAuthenticated ? $spotify->getUser() : null;

// Impedir o acesso à página se o usuário não estiver autenticado
if (!$isAuthenticated) {
    header('Location: firstPage.php');
    exit;
}
$auth = new Authenticate();



if (isset($_GET['logout'])) {
    $authType = $_GET['auth'] ?? null;
    $auth->logout($authType);
}


?>



<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
   <link rel="icon" type="image/png" href="img/spotify-logo.png">

    <script defer src="js/initialContent.js"></script>
    <script defer src="js/searchHandler.js"></script>
    <script defer src="js/play.js"></script>
    <script defer src="js/artists.js"></script>
    <script defer src="js/albums.js"></script>
    <script defer src="js/bestResult.js"></script>
    <script defer src="js/playlists.js"></script>
    <script defer src="js/popularArtists.js"></script>
    <script defer src="js/episodesPodcast.js"></script>
    <script defer src="js/recentlyPlayed.js"></script>
    <script defer src="js/hitParades.js"></script>
    <script defer src="js/newReleasesAlbums.js"></script>
    <script defer src="js/pageOfArtist.js"></script>
   
    <script defer src="https://sdk.scdn.co/spotify-player.js" ></script>
    




    <title>Spotify-Web player: música para todas as pessoas</title>
</head>

<body>
    <div class="head">
        <header class="header7">


            <span class="iconSpotify">
                <a href="index.php">
                    <svg role="img" viewBox="0 0 24 24" aria-label="Spotify" aria-hidden="false">
                        <title>Spotify</title>
                        <path
                            d="M13.427.01C6.805-.253 1.224 4.902.961 11.524.698 18.147 5.853 23.728 12.476 23.99c6.622.263 12.203-4.892 12.466-11.514S20.049.272 13.427.01m5.066 17.579a.717.717 0 0 1-.977.268 14.4 14.4 0 0 0-5.138-1.747 14.4 14.4 0 0 0-5.42.263.717.717 0 0 1-.338-1.392c1.95-.474 3.955-.571 5.958-.29 2.003.282 3.903.928 5.647 1.92a.717.717 0 0 1 .268.978m1.577-3.15a.93.93 0 0 1-1.262.376 17.7 17.7 0 0 0-5.972-1.96 17.7 17.7 0 0 0-6.281.238.93.93 0 0 1-1.11-.71.93.93 0 0 1 .71-1.11 19.5 19.5 0 0 1 6.94-.262 19.5 19.5 0 0 1 6.599 2.165c.452.245.62.81.376 1.263m1.748-3.551a1.147 1.147 0 0 1-1.546.488 21.4 21.4 0 0 0-6.918-2.208 21.4 21.4 0 0 0-7.259.215 1.146 1.146 0 0 1-.456-2.246 23.7 23.7 0 0 1 8.034-.24 23.7 23.7 0 0 1 7.657 2.445c.561.292.78.984.488 1.546m13.612-.036-.832-.247c-1.67-.495-2.14-.681-2.14-1.353 0-.637.708-1.327 2.264-1.327 1.539 0 2.839.752 3.51 1.31.116.096.24.052.24-.098V6.935c0-.097-.027-.15-.098-.203-.83-.62-2.272-1.07-3.723-1.07-2.953 0-4.722 1.68-4.722 3.59 0 2.157 1.371 2.91 3.626 3.546l.973.274c1.689.478 1.998.902 1.998 1.556 0 1.097-.831 1.433-2.07 1.433-1.556 0-3.457-.911-4.35-2.025-.08-.098-.177-.053-.177.062v2.423c0 .097.01.141.08.22.743.814 2.52 1.53 4.59 1.53 2.546 0 4.456-1.485 4.456-3.784 0-1.787-1.052-2.865-3.625-3.635m10.107-1.76c-1.68 0-2.653 1.026-3.219 2.052V9.376c0-.08-.044-.124-.124-.124h-2.22c-.079 0-.123.044-.123.124V20.72c0 .08.044.124.124.124h2.22c.079 0 .123-.044.123-.124v-4.536c.566 1.025 1.521 2.034 3.237 2.034 2.264 0 3.89-1.955 3.89-4.581s-1.644-4.545-3.908-4.545m-.654 6.986c-1.185 0-2.211-1.167-2.618-2.458.407-1.362 1.344-2.405 2.618-2.405 1.211 0 2.051.92 2.051 2.423s-.84 2.44-2.051 2.44m40.633-6.826h-2.264c-.08 0-.115.017-.15.097l-2.282 5.483-2.29-5.483c-.035-.08-.07-.097-.15-.097h-3.661v-.584c0-.955.645-1.397 1.476-1.397.496 0 1.035.256 1.415.486.089.053.15-.008.115-.088l-.796-1.901a.26.26 0 0 0-.124-.133c-.389-.203-1.025-.38-1.644-.38-1.875 0-2.954 1.432-2.954 3.254v.743h-1.503c-.08 0-.124.044-.124.124v1.768c0 .08.044.124.124.124h1.503v6.668c0 .08.044.123.124.123h2.264c.08 0 .124-.044.124-.123v-6.668h1.936l2.812 6.11-1.512 3.325c-.044.098.009.142.097.142h2.414c.08 0 .116-.018.15-.097l4.997-11.355c.035-.08-.009-.141-.097-.141M54.964 9.04c-2.865 0-4.837 2.025-4.837 4.616 0 2.573 1.971 4.616 4.837 4.616 2.856 0 4.846-2.043 4.846-4.616 0-2.591-1.99-4.616-4.846-4.616m.008 7.065c-1.37 0-2.343-1.043-2.343-2.45 0-1.405.973-2.449 2.343-2.449 1.362 0 2.335 1.043 2.335 2.45 0 1.406-.973 2.45-2.335 2.45m33.541-6.334a1.24 1.24 0 0 0-.483-.471 1.4 1.4 0 0 0-.693-.17q-.384 0-.693.17a1.24 1.24 0 0 0-.484.471q-.174.302-.174.681 0 .375.174.677.175.3.484.471t.693.17.693-.17.483-.471.175-.676q0-.38-.175-.682m-.211 1.247a1 1 0 0 1-.394.39 1.15 1.15 0 0 1-.571.14 1.16 1.16 0 0 1-.576-.14 1 1 0 0 1-.391-.39 1.14 1.14 0 0 1-.14-.566q0-.316.14-.562t.391-.388.576-.14q.32 0 .57.14.253.141.395.39t.142.565q0 .312-.142.56m-19.835-5.78c-.85 0-1.468.6-1.468 1.396s.619 1.397 1.468 1.397c.866 0 1.485-.6 1.485-1.397 0-.796-.619-1.397-1.485-1.397m19.329 5.19a.31.31 0 0 0 .134-.262q0-.168-.132-.266-.132-.099-.381-.099h-.588v1.229h.284v-.489h.154l.374.489h.35l-.41-.518a.5.5 0 0 0 .215-.084m-.424-.109h-.26v-.3h.27q.12 0 .184.036a.12.12 0 0 1 .065.116.12.12 0 0 1-.067.111.4.4 0 0 1-.192.037M69.607 9.252h-2.263c-.08 0-.124.044-.124.124v8.56c0 .08.044.123.124.123h2.263c.08 0 .124-.044.124-.123v-8.56c0-.08-.044-.124-.124-.124m-3.333 6.605a2.1 2.1 0 0 1-1.053.257c-.725 0-1.185-.425-1.185-1.362v-3.484h2.211c.08 0 .124-.044.124-.124V9.376c0-.08-.044-.124-.124-.124h-2.21V6.944c0-.097-.063-.15-.15-.08l-3.954 3.113c-.053.044-.07.088-.07.16v1.007c0 .08.044.124.123.124h1.539v3.855c0 2.087 1.203 3.06 2.918 3.06.743 0 1.46-.194 1.884-.442.062-.035.07-.07.07-.133v-1.68c0-.088-.044-.115-.123-.07"
                            transform="translate(-0.95,0)">
                        </path>
                    </svg>

                </a>
            </span>

            <div class="hut">
                <span class="house">
                    <!--<svg  role="img" aria-hidden="true" viewBox="0 0 24 24">
                    <path
                        d="M12.5 3.247a1 1 0 0 0-1 0L4 7.577V20h4.5v-6a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v6H20V7.577l-7.5-4.33zm-2-1.732a3 3 0 0 1 3 0l7.5 4.33a2 2 0 0 1 1 1.732V21a1 1 0 0 1-1 1h-6.5a1 1 0 0 1-1-1v-6h-3v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V7.577a2 2 0 0 1 1-1.732l7.5-4.33z">
                    </path>
                    </svg>-->

                    <svg role="img" aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M13.5 1.515a3 3 0 0 0-3 0L3 5.845a2 2 0 0 0-1 1.732V21a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6h4v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7.577a2 2 0 0 0-1-1.732l-7.5-4.33z"></path>
                    </svg>
                </span>


                 <div class="input7">

                <span class="search">
                    <svg role="img" aria-hidden="true">
                        <path
                            d="M10.533 1.27893C5.35215 1.27893 1.12598 5.41887 1.12598 10.5579C1.12598 15.697 5.35215 19.8369 10.533 19.8369C12.767 19.8369 14.8235 19.0671 16.4402 17.7794L20.7929 22.132C21.1834 22.5226 21.8166 22.5226 22.2071 22.132C22.5976 21.7415 22.5976 21.1083 22.2071 20.7178L17.8634 16.3741C19.1616 14.7849 19.94 12.7634 19.94 10.5579C19.94 5.41887 15.7138 1.27893 10.533 1.27893ZM3.12598 10.5579C3.12598 6.55226 6.42768 3.27893 10.533 3.27893C14.6383 3.27893 17.94 6.55226 17.94 10.5579C17.94 14.5636 14.6383 17.8369 10.533 17.8369C6.42768 17.8369 3.12598 14.5636 3.12598 10.5579Z">
                        </path>
                    </svg>
                </span>

                <input class="search-input" placeholder="O que você quer ouvir?">


                <div class="vertical-line"></div>

                <span class="cest">
                    <svg role="img" aria-hidden="true">
                        <path d="M15 15.5c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"></path>
                        <path
                            d="M1.513 9.37A1 1 0 0 1 2.291 9h19.418a1 1 0 0 1 .979 1.208l-2.339 11a1 1 0 0 1-.978.792H4.63a1 1 0 0 1-.978-.792l-2.339-11a1 1 0 0 1 .201-.837zM3.525 11l1.913 9h13.123l1.913-9H3.525zM4 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v4h-2V3H6v3H4V2z">
                        </path>
                    </svg>
                </span>
            </div>

            </div>



           


            <div class="perfil">
                <?php if ($isAuthenticated): ?>
                    <button class="perfil-btn"><span><?php echo strtoupper(substr($user->display_name, 0, 1)); ?></span></button>
                <?php endif; ?>

            </div>


        </header>




    </div>


    <div class="container7">
        <nav class="sidebar7">
            <div class="nav-library7">
                <div class="dad">
                    <span class="book">
                        <svg role="img" aria-hidden="true"
                            viewBox="0 0 24 24">
                            <path
                                d="M3 22a1 1 0 0 1-1-1V3a1 1 0 0 1 2 0v18a1 1 0 0 1-1 1zM15.5 2.134A1 1 0 0 0 14 3v18a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V6.464a1 1 0 0 0-.5-.866l-6-3.464zM9 2a1 1 0 0 0-1 1v18a1 1 0 1 0 2 0V3a1 1 0 0 0-1-1z">
                            </path>

                        </svg>

                    </span>

                    <span class="book-empty">
                        <svg role="img" aria-hidden="true" viewBox="0 0 24 24">
                            <path
                                d="M14.5 2.134a1 1 0 0 1 1 0l6 3.464a1 1 0 0 1 .5.866V21a1 1 0 0 1-1 1h-6a1 1 0 0 1-1-1V3a1 1 0 0 1 .5-.866zM16 4.732V20h4V7.041l-4-2.309zM3 22a1 1 0 0 1-1-1V3a1 1 0 0 1 2 0v18a1 1 0 0 1-1 1zm6 0a1 1 0 0 1-1-1V3a1 1 0 0 1 2 0v18a1 1 0 0 1-1 1z">
                            </path>
                        </svg>
                    </span>
                    <div class="library">
                        <p>Sua Biblioteca</p>

                    </div>
                </div>



                <span class="plus-simbol">
                    <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                        <path
                            d="M15.25 8a.75.75 0 0 1-.75.75H8.75v5.75a.75.75 0 0 1-1.5 0V8.75H1.5a.75.75 0 0 1 0-1.5h5.75V1.5a.75.75 0 0 1 1.5 0v5.75h5.75a.75.75 0 0 1 .75.75z">
                        </path>
                    </svg>
                </span>


                <span class="arrow">
                    <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                        <path
                            d="M7.19 1A.749.749 0 0 1 8.47.47L16 7.99l-7.53 7.521a.75.75 0 0 1-1.234-.815.75.75 0 0 1 .174-.243l5.72-5.714H.75a.75.75 0 1 1 0-1.498h12.38L7.41 1.529a.749.749 0 0 1-.22-.53z">
                        </path>
                    </svg>
                </span>


            </div>




            <div class="create">
                <ul>
                    <li>
                        <button>
                            <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                                <path
                                    d="M2 0v2H0v1.5h2v2h1.5v-2h2V2h-2V0H2zm11.5 2.5H8.244A5.482 5.482 0 0 0 7.966 1H15v11.75A2.75 2.75 0 1 1 12.25 10h1.25V2.5zm0 9h-1.25a1.25 1.25 0 1 0 1.25 1.25V11.5zM4 8.107a5.465 5.465 0 0 0 1.5-.593v5.236A2.75 2.75 0 1 1 2.75 10H4V8.107zM4 11.5H2.75A1.25 1.25 0 1 0 4 12.75V11.5z">
                                </path>
                            </svg>
                            <span>Criar nova playlist</span>
                        </button>
                    </li>
                    <li>
                        <button>
                            <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                                <path
                                    d="M1.75 1A1.75 1.75 0 0 0 0 2.75v11.5C0 15.216.784 16 1.75 16h12.5A1.75 1.75 0 0 0 16 14.25v-9.5A1.75 1.75 0 0 0 14.25 3H7.82l-.65-1.125A1.75 1.75 0 0 0 5.655 1H1.75zM1.5 2.75a.25.25 0 0 1 .25-.25h3.905a.25.25 0 0 1 .216.125L6.954 4.5h7.296a.25.25 0 0 1 .25.25v9.5a.25.25 0 0 1-.25.25H1.75a.25.25 0 0 1-.25-.25V2.75z">
                                </path>
                            </svg>
                            <span>Criar pasta de playlists</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="nav-playlist">
                <h5>Crie sua primeira Playlist</h5>
                <p>É fácil, vamos te ajudar</p>
                <button> Criar Playlist</button>
            </div>
            <div class="nav-podcast">
                <h5>Que tal Seguir um Podcast?</h5>
                <p>Avisaremos você sobre nossos episódios</p>
                <button>Explore Podcast</button>
            </div>


            <!--<div class="nav-filtro">-->
            <!--<button class="nav-filtro-son">
                        <span class="playlist">Playlists</span>
                    </button>

                    <button class="nav-filtro-son">
                        <span class="artistas">Artistas</span>
                    </button>

                    <button class="nav-filtro-son">
                        <span class="albuns">Álbuns</span>
                    </button>

                    <button class="nav-filtro-son">
                        <span class="podcasts">Podcasts e programas</span>

                    </button>-->

            <!--<div class="general">
                        <button class="button1" >

                            <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                                <path
                                    d="M11.03.47a.75.75 0 0 1 0 1.06L4.56 8l6.47 6.47a.75.75 0 1 1-1.06 1.06L2.44 8 9.97.47a.75.75 0 0 1 1.06 0z">
                                </path>
                            </svg>

                        </button>
                        <button class="button2">

                            <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                                <path
                                    d="M4.97.47a.75.75 0 0 0 0 1.06L11.44 8l-6.47 6.47a.75.75 0 1 0 1.06 1.06L13.56 8 6.03.47a.75.75 0 0 0-1.06 0z">
                                </path>
                            </svg>

                        </button>

                    </div>-->


            <!--</div>-->
            <!--<div class="searchBox">
                    <span class="search2">
                        <svg data-encore-id="icon" role="img" aria-hidden="true" viewBox="0 0 16 16">
                            <path
                                d="M7 1.75a5.25 5.25 0 1 0 0 10.5 5.25 5.25 0 0 0 0-10.5zM.25 7a6.75 6.75 0 1 1 12.096 4.12l3.184 3.185a.75.75 0 1 1-1.06 1.06L11.304 12.2A6.75 6.75 0 0 1 .25 7z">
                            </path>
                        </svg>
                    </span>

                    <div class="recentes">
                        <button>
                            <span class="re">
                                Recentes
                            </span>

                            <span class="IconWrapper">
                                <svg role="img" aria-hidden="true" viewBox="0 0 16 16">
                                    <path
                                        d="M15 14.5H5V13h10v1.5zm0-5.75H5v-1.5h10v1.5zM15 3H5V1.5h10V3zM3 3H1V1.5h2V3zm0 11.5H1V13h2v1.5zm0-5.75H1v-1.5h2v1.5z">
                                    </path>
                                </svg>

                            </span>

                        </button>

                    </div>

                </div>-->
        </nav>
        <div class="resize-border">

            <label class="hidden-visually">
                "Redimensionar navegação principal"
                <input class="resizer-input" type="range" min="72" max="1314" step="10" value="400">

            </label>

        </div>


        <main class="main-content">
            <div class="page-of-artist" style="display: none;">
                <div class="header"></div>
                <div class="artist-banner"></div>
                <div class="followers"></div>
                <div class="popular-songs-by-the-artist"></div>
                <div class="albums-section"></div>
                <div class="discography-section"></div>
                <div class="similar-artists-section"></div>

            </div>






            <div class="menu">
                <a href="#">Conta <span><svg role="img" aria-label="Link externo" aria-hidden="false"
                            viewBox="0 0 16 16">
                            <path
                                d="M1 2.75A.75.75 0 0 1 1.75 2H7v1.5H2.5v11h10.219V9h1.5v6.25a.75.75 0 0 1-.75.75H1.75a.75.75 0 0 1-.75-.75V2.75z">
                            </path>
                            <path
                                d="M15 1v4.993a.75.75 0 1 1-1.5 0V3.56L8.78 8.28a.75.75 0 0 1-1.06-1.06l4.72-4.72h-2.433a.75.75 0 0 1 0-1.5H15z">
                            </path>
                        </svg></span></a>

                <a href="#">Perfil <span></span></a>

                <a href="#">Faça upgrade para o Premium <span><svg role="img" aria-label="Link externo"
                            aria-hidden="false" viewBox="0 0 16 16">
                            <path
                                d="M1 2.75A.75.75 0 0 1 1.75 2H7v1.5H2.5v11h10.219V9h1.5v6.25a.75.75 0 0 1-.75.75H1.75a.75.75 0 0 1-.75-.75V2.75z">
                            </path>
                            <path
                                d="M15 1v4.993a.75.75 0 1 1-1.5 0V3.56L8.78 8.28a.75.75 0 0 1-1.06-1.06l4.72-4.72h-2.433a.75.75 0 0 1 0-1.5H15z">
                            </path>
                        </svg></span></a>

                <a href="#">Suporte <span><svg role="img" aria-label="Link externo" aria-hidden="false"
                            viewBox="0 0 16 16">
                            <path
                                d="M1 2.75A.75.75 0 0 1 1.75 2H7v1.5H2.5v11h10.219V9h1.5v6.25a.75.75 0 0 1-.75.75H1.75a.75.75 0 0 1-.75-.75V2.75z">
                            </path>
                            <path
                                d="M15 1v4.993a.75.75 0 1 1-1.5 0V3.56L8.78 8.28a.75.75 0 0 1-1.06-1.06l4.72-4.72h-2.433a.75.75 0 0 1 0-1.5H15z">
                            </path>
                        </svg></span></a>

                <a href="#">Baixar <span><svg role="img" aria-label="Link externo" aria-hidden="false"
                            viewBox="0 0 16 16">
                            <path
                                d="M1 2.75A.75.75 0 0 1 1.75 2H7v1.5H2.5v11h10.219V9h1.5v6.25a.75.75 0 0 1-.75.75H1.75a.75.75 0 0 1-.75-.75V2.75z">
                            </path>
                            <path
                                d="M15 1v4.993a.75.75 0 1 1-1.5 0V3.56L8.78 8.28a.75.75 0 0 1-1.06-1.06l4.72-4.72h-2.433a.75.75 0 0 1 0-1.5H15z">
                            </path>
                        </svg></span></a>

                <a href="#">Configurações</a>
                <a href="?logout=true" id="clear-storage" class="sair">Sair</a>

            </div>

            <section class="content">
                <div class="info-content">
                    <div>Tudo</div>
                    <div>Música</div>
                    <div>Podcasts</div>

                </div>
                <div class="popular-artists-content"></div>
                <div class="episodes-podcast-content"></div>
                <div class="recently-played-content"></div>
                <div class="hit-parades-content"></div>
                <div class="new-albums-releases-content"></div>



                <div class="search-results">
                    <div id="search-results-grid"></div>
                </div>

                <div class="artists-section"></div>
                <div class="albums-section"></div>
                <div class="playlists-section"></div>

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
                            <a href="https://www.instagram.com/spotify/"><svg data-encore-id="icon" role="img"
                                    aria-hidden="true" class="Svg-sc-ytk21e-0 dCAvla e-9541-icon" viewBox="0 0 16 16">
                                    <path
                                        d="M8 1.44c2.136 0 2.389.009 3.233.047.78.036 1.203.166 1.485.276.348.128.663.332.921.598.266.258.47.573.599.921.11.282.24.706.275 1.485.039.844.047 1.097.047 3.233s-.008 2.389-.047 3.232c-.035.78-.166 1.204-.275 1.486a2.654 2.654 0 0 1-1.518 1.518c-.282.11-.706.24-1.486.275-.843.039-1.097.047-3.233.047s-2.39-.008-3.232-.047c-.78-.035-1.204-.165-1.486-.275a2.477 2.477 0 0 1-.921-.599 2.477 2.477 0 0 1-.599-.92c-.11-.282-.24-.706-.275-1.486-.038-.844-.047-1.096-.047-3.232s.009-2.39.047-3.233c.036-.78.166-1.203.275-1.485.129-.348.333-.663.599-.921a2.49 2.49 0 0 1 .92-.599c.283-.11.707-.24 1.487-.275.843-.038 1.096-.047 3.232-.047L8 1.441zm.001-1.442c-2.172 0-2.445.01-3.298.048-.854.04-1.435.176-1.943.373a3.928 3.928 0 0 0-1.417.923c-.407.4-.722.883-.923 1.417-.198.508-.333 1.09-.372 1.942C.01 5.552 0 5.826 0 8c0 2.172.01 2.445.048 3.298.04.853.174 1.433.372 1.941.2.534.516 1.017.923 1.417.4.407.883.722 1.417.923.508.198 1.09.333 1.942.372.852.039 1.126.048 3.299.048 2.172 0 2.445-.01 3.298-.048.853-.04 1.433-.174 1.94-.372a4.087 4.087 0 0 0 2.34-2.34c.199-.508.334-1.09.373-1.942.039-.851.048-1.125.048-3.298s-.01-2.445-.048-3.298c-.04-.853-.174-1.433-.372-1.94a3.924 3.924 0 0 0-.923-1.418A3.928 3.928 0 0 0 13.24.42c-.508-.197-1.09-.333-1.942-.371-.851-.041-1.125-.05-3.298-.05l.001-.001z">
                                    </path>
                                    <path
                                        d="M8 3.892a4.108 4.108 0 1 0 0 8.216 4.108 4.108 0 0 0 0-8.216zm0 6.775a2.668 2.668 0 1 1 0-5.335 2.668 2.668 0 0 1 0 5.335zm4.27-5.978a.96.96 0 1 0 0-1.92.96.96 0 0 0 0 1.92z">
                                    </path>
                                </svg></a>
                            <a href="https://x.com/spotify?mx=2"><svg data-encore-id="icon" role="img"
                                    aria-hidden="true" class="Svg-sc-ytk21e-0 dCAvla e-9541-icon" viewBox="0 0 16 16">
                                    <path
                                        d="M13.54 3.889a2.968 2.968 0 0 0 1.333-1.683 5.937 5.937 0 0 1-1.929.738 2.992 2.992 0 0 0-.996-.706 2.98 2.98 0 0 0-1.218-.254 2.92 2.92 0 0 0-2.143.889 2.929 2.929 0 0 0-.889 2.15c0 .212.027.442.08.691a8.475 8.475 0 0 1-3.484-.932A8.536 8.536 0 0 1 1.532 2.54a2.993 2.993 0 0 0-.413 1.523c0 .519.12 1 .361 1.445.24.445.57.805.988 1.08a2.873 2.873 0 0 1-1.373-.374v.04c0 .725.23 1.365.69 1.92a2.97 2.97 0 0 0 1.739 1.048 2.937 2.937 0 0 1-1.365.056 2.94 2.94 0 0 0 1.063 1.5 2.945 2.945 0 0 0 1.77.603 5.944 5.944 0 0 1-3.77 1.302c-.243 0-.484-.016-.722-.048A8.414 8.414 0 0 0 5.15 14c.905 0 1.763-.12 2.572-.361.81-.24 1.526-.57 2.147-.988a9.044 9.044 0 0 0 1.683-1.46c.5-.556.911-1.155 1.234-1.798a9.532 9.532 0 0 0 .738-1.988 8.417 8.417 0 0 0 .246-2.429 6.177 6.177 0 0 0 1.508-1.563c-.56.249-1.14.407-1.738.476z">
                                    </path>
                                </svg></a>
                            <a href="https://www.facebook.com/Spotify"><svg data-encore-id="icon" role="img"
                                    aria-hidden="true" class="Svg-sc-ytk21e-0 dYnaPI e-9541-icon" viewBox="0 0 16 16">
                                    <path
                                        d="M16 8a8 8 0 1 0-9.25 7.903v-5.59H4.719V8H6.75V6.237c0-2.005 1.194-3.112 3.022-3.112.875 0 1.79.156 1.79.156V5.25h-1.008c-.994 0-1.304.617-1.304 1.25V8h2.219l-.355 2.313H9.25v5.59A8.002 8.002 0 0 0 16 8z">
                                    </path>
                                </svg></a>

                        </div>

                    </div>


                    <div class="footer-info-cop">

                        <div class="footer-informations">
                            <a href="">Legal</a>
                            <a href="">Segurança e Centro de Privacidade</a>
                            <a href="">Política de privacidade</a>
                            <a href="">Cookies</a>
                            <a href="">Sobre anúncios</a>
                            <a href="">Acessibilidade</a>

                        </div>
                        <div class="footer-copy">
                            <p>@ 2025 Spotify AB</p>
                        </div>



                    </div>

                </footer>


            </section>


        </main>





    </div>
    <div class="media-control">
        <div class="content-info">
            <img class="content-img" src="" alt="Imagem do Álbum" style="display: none;">
            <div class="jun">
                <span class="content-name"></span>
                <a class="content-artista"></a>

            </div>

        </div>
        <div class="control-button-center">
            <div class="align-the-buttons">
                <button>
                    <svg class="random-order" role="img" aria-hidden="true">
                        <path d="M13.151.922a.75.75 0 1 0-1.06 1.06L13.109 3H11.16a3.75 3.75 0 0 0-2.873 1.34l-6.173 7.356A2.25 2.25 0 0 1 .39 12.5H0V14h.391a3.75 3.75 0 0 0 2.873-1.34l6.173-7.356a2.25 2.25 0 0 1 1.724-.804h1.947l-1.017 1.018a.75.75 0 0 0 1.06 1.06L15.98 3.75 13.15.922zM.391 3.5H0V2h.391c1.109 0 2.16.49 2.873 1.34L4.89 5.277l-.979 1.167-1.796-2.14A2.25 2.25 0 0 0 .39 3.5z"></path>
                        <path d="m7.5 10.723.98-1.167.957 1.14a2.25 2.25 0 0 0 1.724.804h1.947l-1.017-1.018a.75.75 0 1 1 1.06-1.06l2.829 2.828-2.829 2.828a.75.75 0 1 1-1.06-1.06L13.109 13H11.16a3.75 3.75 0 0 1-2.873-1.34l-.787-.938z"></path>
                    </svg>


                </button>
                <button>
                    <svg class="back" role="img" aria-hidden="true">
                        <path d="M3.3 1a.7.7 0 0 1 .7.7v5.15l9.95-5.744a.7.7 0 0 1 1.05.606v12.575a.7.7 0 0 1-1.05.607L4 9.149V14.3a.7.7 0 0 1-.7.7H1.7a.7.7 0 0 1-.7-.7V1.7a.7.7 0 0 1 .7-.7h1.6z"></path>
                    </svg>
                </button>

                <button class="play-pause-button">
                    <span class="background-pause-and-play">
                        <svg class="pause" role="img" aria-hidden="true">
                            <path d="M2.7 1a.7.7 0 0 0-.7.7v12.6a.7.7 0 0 0 .7.7h2.6a.7.7 0 0 0 .7-.7V1.7a.7.7 0 0 0-.7-.7H2.7zm8 0a.7.7 0 0 0-.7.7v12.6a.7.7 0 0 0 .7.7h2.6a.7.7 0 0 0 .7-.7V1.7a.7.7 0 0 0-.7-.7h-2.6z"></path>
                        </svg>
                        <svg class="play" style="display: none;" role="img" aria-hidden="true">
                            <path d="M3 1.713a.7.7 0 0 1 1.05-.607l10.89 6.288a.7.7 0 0 1 0 1.212L4.05 14.894A.7.7 0 0 1 3 14.288V1.713z"></path>
                        </svg>

                    </span>

                </button>
                <button>
                    <svg class="next" role="img" aria-hidden="true">
                        <path d="M12.7 1a.7.7 0 0 0-.7.7v5.15L2.05 1.107A.7.7 0 0 0 1 1.712v12.575a.7.7 0 0 0 1.05.607L12 9.149V14.3a.7.7 0 0 0 .7.7h1.6a.7.7 0 0 0 .7-.7V1.7a.7.7 0 0 0-.7-.7h-1.6z"></path>
                    </svg>
                </button>
                <button class="repeat-button" title="Mode de repetição: Desativado" >
                    <svg class="repeat-icon" role="img" aria-hidden="true">
                      
                        <path d="M0 4.75A3.75 3.75 0 0 1 3.75 1h8.5A3.75 3.75 0 0 1 16 4.75v5a3.75 3.75 0 0 1-3.75 3.75H9.81l1.018 1.018a.75.75 0 1 1-1.06 1.06L6.939 12.75l2.829-2.828a.75.75 0 1 1 1.06 1.06L9.811 12h2.439a2.25 2.25 0 0 0 2.25-2.25v-5a2.25 2.25 0 0 0-2.25-2.25h-8.5A2.25 2.25 0 0 0 1.5 4.75v5A2.25 2.25 0 0 0 3.75 12H5v1.5H3.75A3.75 3.75 0 0 1 0 9.75v-5z"></path>
                    </svg>
                    <span class="repeat-mode-indicator" >1</span>


                </button>

            </div>

            <div class="playbacks">
                <div class="playback-position">-:--</div>
                <div class="jil">
                    <div class="playback-progress-bar">
                        <input type="range" min="0" max="0" step="1" class="reproduction-bar" value="0"></input>

                    </div>
                </div>

                <div class="playback-duration">-:--</div>
            </div>

        </div>

        <div class="control-button-right">
            <div class="align-the-buttons-right">
                <button>
                    <svg class="playing-now" role="img" aria-hidden="true">
                        <path d="M11.196 8 6 5v6l5.196-3z"></path>
                        <path d="M15.002 1.75A1.75 1.75 0 0 0 13.252 0h-10.5a1.75 1.75 0 0 0-1.75 1.75v12.5c0 .966.783 1.75 1.75 1.75h10.5a1.75 1.75 0 0 0 1.75-1.75V1.75zm-1.75-.25a.25.25 0 0 1 .25.25v12.5a.25.25 0 0 1-.25.25h-10.5a.25.25 0 0 1-.25-.25V1.75a.25.25 0 0 1 .25-.25h10.5z"></path>
                    </svg>
                </button>
                <button>
                    <svg class="list" role="img" aria-hidden="true">
                        <path d="M15 15H1v-1.5h14V15zm0-4.5H1V9h14v1.5zm-14-7A2.5 2.5 0 0 1 3.5 1h9a2.5 2.5 0 0 1 0 5h-9A2.5 2.5 0 0 1 1 3.5zm2.5-1a1 1 0 0 0 0 2h9a1 1 0 1 0 0-2h-9z"></path>
                    </svg>
                </button>
                <button>
                    <svg class="connect-to-device" role="img" aria-hidden="true">
                        <path d="M6 2.75C6 1.784 6.784 1 7.75 1h6.5c.966 0 1.75.784 1.75 1.75v10.5A1.75 1.75 0 0 1 14.25 15h-6.5A1.75 1.75 0 0 1 6 13.25V2.75zm1.75-.25a.25.25 0 0 0-.25.25v10.5c0 .138.112.25.25.25h6.5a.25.25 0 0 0 .25-.25V2.75a.25.25 0 0 0-.25-.25h-6.5zm-6 0a.25.25 0 0 0-.25.25v6.5c0 .138.112.25.25.25H4V11H1.75A1.75 1.75 0 0 1 0 9.25v-6.5C0 1.784.784 1 1.75 1H4v1.5H1.75zM4 15H2v-1.5h2V15z"></path>
                        <path d="M13 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm-1-5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"></path>
                    </svg>
                </button>
                <div class="align-volume-bar">
                    <button>
                        <svg class="volume-icon" role="presentation" aria-label="Volume alto" aria-hidden="false">
                            <path d="M9.741.85a.75.75 0 0 1 .375.65v13a.75.75 0 0 1-1.125.65l-6.925-4a3.642 3.642 0 0 1-1.33-4.967 3.639 3.639 0 0 1 1.33-1.332l6.925-4a.75.75 0 0 1 .75 0zm-6.924 5.3a2.139 2.139 0 0 0 0 3.7l5.8 3.35V2.8l-5.8 3.35zm8.683 4.29V5.56a2.75 2.75 0 0 1 0 4.88z"></path>
                            <path d="M11.5 13.614a5.752 5.752 0 0 0 0-11.228v1.55a4.252 4.252 0 0 1 0 8.127v1.55z"></path>
                        </svg>
                    </button>
                    <div class="volume-bar-background">
                        <input type="range" min="0" max="100" step="1" class="volume-bar" value="100">
                    </div>

                </div>


                <button>
                    <svg class="full-screen" role="img" aria-hidden="true">
                        <path d="M6.53 9.47a.75.75 0 0 1 0 1.06l-2.72 2.72h1.018a.75.75 0 0 1 0 1.5H1.25v-3.579a.75.75 0 0 1 1.5 0v1.018l2.72-2.72a.75.75 0 0 1 1.06 0zm2.94-2.94a.75.75 0 0 1 0-1.06l2.72-2.72h-1.018a.75.75 0 1 1 0-1.5h3.578v3.579a.75.75 0 0 1-1.5 0V3.81l-2.72 2.72a.75.75 0 0 1-1.06 0z"></path>
                    </svg>
                </button>

            </div>

        </div>


    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.js"
        integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            let sidebar = $('.sidebar7')
            let resizer = $('.resize-border')
            let resizeInput = $('.resize-input')
            let initialLeft = resizer.offset().left
            let library = sidebar.find('.library')
            let arrow = sidebar.find('.arrow')
            let plusSimbol = sidebar.find('.plus-simbol')
            let navPlaylist = sidebar.find('.nav-playlist')
            let navPodcast = sidebar.find('.nav-podcast')
            let isResizing = false
            let create = $('.create')
            let perfilBtn = $('.perfil-btn')
            let menu = $('.menu')
            let book = $('.book')
            let bookEmpty = $('.book-empty')
            let contentLink2 = $('.content__link2')
            let searchInput = $('.search-input')
            let storageClear = $('#clear-storage')




            // Função para iniciar o redimensionamento
            resizer.on('mousedown', function(e) {
                e.preventDefault()
                $(document).on('mousemove.resizing', resizing)
                $(document).on('mouseup.resizing', stopResizing)
            })

            // Função para redimensionar o sidebar
            function resizing(e) {
                let sidebarWidth = e.pageX - sidebar.offset().left + 0;
                let currentWidth = sidebar.width();

                // Se estiver diminuindo e chegar a 210px, vai direto para 72px
                if (currentWidth > sidebarWidth && sidebarWidth <= 210) {
                    sidebarWidth = 72;
                }
                // Se estiver aumentando a partir de 72px, vai direto para 210px
                else if (currentWidth < sidebarWidth && currentWidth === 72) {
                    sidebarWidth = 210;
                }

                // Ajusta a largura dentro dos limites definidos (mínimo 72px e máximo 1314px)
                sidebarWidth = Math.max(72, Math.min(sidebarWidth, 1314));
                sidebar.width(sidebarWidth);

                // Atualiza a posição da borda de redimensionamento
                let newLeft = sidebar.outerWidth() + sidebar.offset().left + 0;
                resizer.css('left', newLeft + 'px');

                // Atualiza o valor do input range
                resizeInput.val(sidebarWidth);

                // Mantém a borda visível durante o redimensionamento
                resizer.css('opacity', '1');

                // Controla a visibilidade da biblioteca e ícones
                toggleVisibility(sidebarWidth);


            }

            // Função para parar o redirecionamento
            function stopResizing() {
                isResizing = false;

                $(document).off('mousemove.resizing');
                $(document).off('mouseup.resizing');

                // Quando não está redimensionando, a borda deve voltar a ser invisível
                if (!resizer.is(':hover')) {
                    resizer.css('opacity', '0');
                }

                // Atualiza a visibilidade baseada na largura final após o redimensionamento
                toggleVisibility(sidebar.width());

                // Atualiza a posição da resize-border para refletir a largura final do sidebar
                let finalWidth = sidebar.width();
                let finalLeft = sidebar.outerWidth() + sidebar.offset().left;
                resizer.css('left', finalLeft + 'px');



            }

            // Mostrar a borda ao passar o mouse sobre a borda de redimensionamento
            resizer.hover(function() {
                $(this).css('opacity', '1')
            }, function() {

                if (!$(document).data('isResizing')) {
                    $(this).css('opacity', '0')
                }
            })

            // Função para controlar a visibilidade da biblioteca e ícones
            function toggleVisibility(width) {
                if (width <= 72) {
                    library.hide()
                    arrow.hide()
                    plusSimbol.hide()
                    navPlaylist.hide()
                    navPodcast.hide()

                } else if (width >= 210) {
                    library.show()
                    arrow.show()
                    plusSimbol.show()
                    navPlaylist.show()
                    navPodcast.show()
                }


            }

            // Inicializa a visibilidade baseada na largura inicial
            toggleVisibility(sidebar.width())
            resizeInput.attr('max', '1314')

            resizeInput.on('input', function() {
                let newWidth = $(this).val()
                sidebar.width(newWidth)
                toggleVisibility(newWidth)

            })


            // Função para exibir ou ocultar menus
            function toggleMenu(button, menu) {
                // Quando o botão for clicado
                $(button).on('click', function(e) {
                    e.stopImmediatePropagation()
                    $(menu).toggle()
                })

                $(document).on('click', function(e) {
                    if (!$(e.target).closest(button).length && !$(e.target).is(menu) && !$(e.target).closest(menu).length) {
                        $(menu).hide()

                    }
                })
            }
            storageClear.on('click', function() {
                localStorage.clear()

            })



            // Aplicação da função para diferentes elementos
            toggleMenu(perfilBtn, menu)
            toggleMenu(plusSimbol, create)





            // Função para gerenciar a visibilidade dos elementos
            function toggleSidebarElements(show) {
                let elementsToToggle = [library, arrow, plusSimbol, navPlaylist, navPodcast]

                elementsToToggle.forEach(function(element) {
                    show ? $(element).show() : $(element).hide()
                })

                // Lógica especial para book e bookEmpty
                if (show) {
                    $(book).show()
                    $(bookEmpty).hide()
                } else {
                    $(book).hide()
                    $(bookEmpty).show()
                }
            }

            // Evento de clique para o ícone do livro (book)
            $(book).on('click', function() {
                $(sidebar).css('width', '72px');
                toggleSidebarElements(false);
            });

            // Evento de clique para o ícone do livro vazio (bookEmpty)
            $(bookEmpty).on('click', function() {
                $(sidebar).css('width', '400px');
                toggleSidebarElements(true);
            });



            // Função para redimensionar o sidebar e girar a seta
            function resizeSidebar(element, targetWidth, rotate) {
                $(sidebar).css('width', targetWidth + 'px') // Define a nova largura do sidebar

                // Atualiza a posição da resize-border para refletir a nova largura
                let newLeft = sidebar.outerWidth() + sidebar.offset().left + 0
                resizer.css('left', newLeft + 'px')

                // Atualiza o input range para refletir a nova largura
                resizeInput.val(targetWidth)

                // Gira a seta se necessário
                if (rotate) {
                    $(element).addClass('rotated') // Gira para a esquerda (180 graus)
                } else {
                    $(element).removeClass('rotated') // Volta para a direita (0 graus)
                }

                // Controla a visibilidade dos elementos com base na nova largura
                toggleVisibility(targetWidth)
            }

            // Evento de clique para a seta (arrow)
            $(arrow).on('click', function() {
                let currentWidth = sidebar.width()
                if (currentWidth <= 400) {
                    resizeSidebar(this, 949, true) // Expande para 949px e gira a seta para a esquerda
                } else {
                    resizeSidebar(this, 400, false) // Reduz para 400px e volta a seta para a direita
                }
            })

        })
    </script>





</body>

</html>