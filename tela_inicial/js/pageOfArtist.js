$(document).ready(function () {
    const pageOfArtist = $('.page-of-artist');
    const header = $('.header');
    const artistBanner = $('.artist-banner');
    const followers = $('.followers');
    const popularSongsByTheArtist = $('.popular-songs-by-the-artist');
    const discographySection = $('.discography-section'); // Novo elemento para discografia
    const similarArtistsSection = $('.similar-artists-section');
    const albumsSection = $('.albums-section'); // Usado para exibir álbuns, singles e compilações
    const playlistsSection = $('.playlists-section');
    const artistsSection = $('.artists-section');
    const infonContent = $('.info-content');
    const resultsGrid = $('#search-results-grid');

    let allTracks = [] // Armazena todas as faixas carregadas
    let allAlbums = {} // Armazena os álbuns por tipo
    let showAllTracks = false // Estado para alternar entre 5 e 10 faixas
    let currentDiscographyView = 'popular_releases' // Estado para controlar a visão da discografia
    let similarArtists = []
    let showAllSimilarArtists = false

    // Escuta o evento personalizado disparado pelo bestResult.js
    $(document).on('showArtistPage', function (_, artistId) {
        console.log('Evento showArtistPage capturado com artistId:', artistId);

        resultsGrid.hide();
        playlistsSection.hide();
        artistsSection.hide();
        albumsSection.hide();
        infonContent.hide();
        pageOfArtist.show();

        // Função para renderizar as faixas
        function renderTracks(limit) {
            popularSongsByTheArtist.empty();
            let tracksHtml = '<h2>Populares</h2><ul>';
            const tracksToShow = allTracks.slice(0, limit);
            if (tracksToShow.length > 0) {
                tracksToShow.forEach((track, index) => {
                    const globalIndex = index + 1;
                    tracksHtml += `
                        <li class="track-item">
                            <svg class="play-icon" role="img" aria-hidden="true" viewBox="0 0 24 24">
                                <path d="m7.05 3.606 13.49 7.788a.7.7 0 0 1 0 1.212L7.05 20.394A.7.7 0 0 1 6 19.788V4.212a.7.7 0 0 1 1.05-.606z"></path>
                            </svg>
                            <span class="track-number2">${globalIndex}</span>
                            <img src="${track.album.images[2]?.url || 'default.jpg'}" alt="${track.name}" class="track-image2">
                            <span class="track-name2">${track.name}</span>
                            <span class="track-duration2">${formatDuration(track.duration_ms)}</span>
                        </li>
                    `;
                });
                tracksHtml += '</ul>';

                if (allTracks.length > 5) {
                    tracksHtml += `<button class="remaining-popular-music">${showAllTracks ? 'Mostrar menos' : 'Ver mais'}</button>`;
                }
            } else {
                tracksHtml += '<li>Nenhuma música popular encontrada.</li></ul>';
            }

            // Renderiza apenas as músicas populares no elemento correto
            popularSongsByTheArtist.html(tracksHtml).show()

            // Renderiza as outras seções separadamente
            renderDiscographySection()
            renderSimilarArtistsSection()

        }

        // Função para renderizar a discografia (lançamentos, álbuns, singles, compilações)
        function renderDiscographySection() {
            discographySection.empty();

            let discographyHtml = `
                <div class="discography">
                    <h2>Discografia</h2>
                    <span class="show-all-discography">Mostrar tudo</span>
                </div>
                <div class="discography-content">
                    <span class="discography-option ${currentDiscographyView === 'popular_releases' ? 'active' : ''}" data-type="popular_releases">Lançamentos populares</span>
                    <span class="discography-option ${currentDiscographyView === 'albums' ? 'active' : ''}" data-type="albums">Álbuns</span>
                    <span class="discography-option ${currentDiscographyView === 'singles' ? 'active' : ''}" data-type="singles">Singles e EPs</span>
                    <span class="discography-option ${currentDiscographyView === 'compilations' ? 'active' : ''}" data-type="compilations">Compilações</span>
                </div>
                <div class="discography-items"></div>
            `
            discographySection.html(discographyHtml).show()
            renderDiscography(currentDiscographyView)
        }

        function renderSimilarArtistsSection() {
            let similarArtistsHtml = ''
            // Seção artistas parecidos
            similarArtistsHtml += `
                <div class="similar-artists">
                    <h2 class="title">Os fãs também curtem</h2>
                    <h2 class="title2" style="display: none">Os fãs também curtem</h2>
                    <span class="show-all-similar-artists"> Mostrar tudo</span>

                </div> 
                <div class="similar-artists-container"></div>
    
            `
            similarArtistsSection.html(similarArtistsHtml).show()
            renderSimilarArtists()
        }
        function renderDiscography(type) {
            const discographyItems = $('.discography-items')
            discographyItems.empty()
            let itemsHtml = ''

            switch (type) {
                case 'popular_releases':
                    if (allAlbums.popular_releases && allAlbums.popular_releases.length > 0) {
                        itemsHtml += '<div class="discography-container">'
                        allAlbums.popular_releases.forEach((release, index) => {

                            // Formatar data de lançamento
                            const releaseDate = new Date(release.release_date);
                            const year = releaseDate.getFullYear();
                            const formattedDate = year

                            itemsHtml += `
                                <div class="discography-grid">
                                    <div class="discography-card">
                                        <img src="${release.image_url}" alt="${release.name || 'Imagem sem título'}" class="discography-image" onerror="this.src='default.jpg'">
                                        <div class="play-button-discography">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                            </svg>
                                        </div>
                                        <a>${release.name}</a>
                                        <div class="discography-details">
                                            <p>${formattedDate}</p><span class="big-dot3"></span><p>Álbum</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        itemsHtml += '</div>'
                    } else {
                        itemsHtml += '<p>Nenhum lançamento popular encontrado.</p>';
                    }
                    break;
                case 'albums':
                    if (allAlbums.albums && allAlbums.albums.length > 0) {
                        itemsHtml += '<div class="discography-container">'
                        allAlbums.albums.forEach((album, index) => {

                            // Formatar data de lançamento
                            const releaseDate = new Date(album.release_date);
                            const year = releaseDate.getFullYear();
                            const formattedDate = year

                            itemsHtml += `
                                 <div class="discography-grid">
                                    <div class="discography-card">
                                        <img src="${album.image_url}" alt="${album.name}" onerror="this.src='default.jpg'">
                                        <div class="play-button-discography">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                            </svg>
                                        </div>
                                        <a>${album.name}</a>
                                        <div class="discography-details">
                                            <p>${formattedDate}</p><span class="big-dot3"></span><p>Álbum</p>
                                        </div>
                                    </div>
                                </div>
                                `;
                        });
                        itemsHtml += '</div>'
                    } else {
                        itemsHtml += '<p>Nenhum álbum encontrado.</p>';
                    }
                    break;
                case 'singles':
                    if (allAlbums.single && allAlbums.single.length > 0) {
                        itemsHtml += '<div class="discography-container">'
                        allAlbums.single.forEach((single, index) => {

                            // Formatar data de lançamento
                            const releaseDate = new Date(single.release_date);
                            const year = releaseDate.getFullYear();
                            const formattedDate = year

                            itemsHtml += `
                                <div class="discography-grid">
                                    <div class="discography-card">
                                        <img src="${single.image_url}" alt="${single.name}" onerror="this.src='default.jpg'">
                                        <div class="play-button-discography">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                            </svg>
                                        </div>
                                        <a>${single.name}</a>
                                        <div class="discography-details">
                                            <p>${formattedDate}</p><span class="big-dot3"></span><p>Singles</p>
                                        </div>
                                    </div>
                                </div>
                                `;
                        });
                        itemsHtml += '</div>'
                    } else {
                        itemsHtml += '<p>Nenhum single ou EP encontrado.</p>';
                    }
                    break;
                case 'compilations':
                    if (allAlbums.compilation && allAlbums.compilation.length > 0) {
                        itemsHtml += '<div class="discography-container">'
                        allAlbums.compilation.forEach((compilation, index) => {

                            // Formatar data de lançamento
                            const releaseDate = new Date(compilation.release_date);
                            const year = releaseDate.getFullYear();
                            const formattedDate = year

                            itemsHtml += `
                                <div class="discography-grid">
                                    <div class="discography-card">
                                        <img src="${compilation.image_url}" alt="${compilation.name}"  onerror="this.src='default.jpg'">
                                        <div class="play-button-discography">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                            </svg>
                                        </div>
                                        <a>${compilation.name}</a>
                                        <div class="discography-details">
                                            <p>${formattedDate}</p><span class="big-dot3"></span><p>Compilações</p>
                                        </div>
                                    </div>
                                </div>
                                `;
                        });
                        itemsHtml += '</div>'
                    } else {
                        itemsHtml += '<p>Nenhuma compilação encontrada.</p>';
                    }
                    break;
            }

            discographyItems.html(itemsHtml);
            $('.discography-option').removeClass('active');
            $(`.discography-option[data-type="${type}"]`).addClass('active');

        }

        // Função para renderizar artistas similares
        function renderSimilarArtists() {
            const similarArtistsContainer = $('.similar-artists-container')
            similarArtistsContainer.empty()
            let artistsHtml = ''

            if (similarArtists && similarArtists.length > 0) {
                artistsHtml += '<div class="similar-artists-grid">'
                // Define o limite com base no estado showAllSimilarArtists
                const displayLimit = showAllSimilarArtists ? 40 : 8
                const artistsToShow = similarArtists.slice(0, displayLimit)
                artistsToShow.forEach((artist) => {
                    artistsHtml += `
                        <div class="similar-artist-card">
                            <img src="${artist.image_url}" alt="${artist.name}" onerror="this.src='default.jpg'">
                            <div class="play-button-similar-artists">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                    <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                </svg>
                            </div>
                            <a>${artist.name}</a>
                            <p>Artista</p>
                        </div>
                    `

                })

                artistsHtml += '</div>'
            } else {
                artistsHtml += '<p>Nenhum artista similiar encontrado</p>'
            }

            similarArtistsContainer.html(artistsHtml)
        }

        // Função para carregar faixas, álbuns e lançamentos (apenas uma vez)
        function loadData() {
            $.ajax({
                url: 'Search/Get_artist_data.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ artist_id: artistId }),
                dataType: 'json',
                success: function (response) {
                    console.log('Resposta da API:', response);
                    if (response.success && response.data) {
                        const artistData = response.data;

                        // Define o background da .artist-banner com a imagem do artista
                        artistBanner.css({
                            'background-image': `url(${artistData.image_url})`,

                        })

                        // Preenche os elementos da página do artista com dados dinâmicos
                        header.html(`
                            <div class="play-button-page-of-artist">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                    <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                </svg>
                            </div>
                            <span class="artist-name">${artistData.name}</span>
                        `);

                        artistBanner.html(`
                            <div class="artist-info">
                                <span class="artist-name2">${artistData.name}</span>
                                <span class="listeners">${artistData.followers.toLocaleString('pt-BR')} ouvintes mensais</span>
                            </div>
                        `).show();

                        followers.html(`
                            <div class="play-button-page-of-artist2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                    <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                </svg>
                            </div>
                            <span class="follow">Seguir</span>
                            <div class="icon-menu"> 
                                <svg role="img" aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="M4.5 13.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm15 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm-7.5 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"></path>
                                </svg>
                            </div>
                        `).show();

                        // Armazena todas as faixas, álbuns e lançamentos
                        allTracks = artistData.tracks.slice(0, 10);
                        allAlbums = artistData.albums || {}; // Garante que allAlbums não seja undefined
                        if (artistData.popular_releases) {
                            allAlbums.popular_releases = artistData.popular_releases; // Atribui popular_releases
                        }
                        similarArtists = artistData.similar_artists || [] // Armazena os artistas similares
                        console.log('allAlbums após atribuição:', allAlbums); // Log para depuração
                        console.log('similarArtists após atribuição:', similarArtists);


                        // Renderiza inicialmente com 5 faixas e discografia (lançamentos populares)
                        showAllTracks = false;
                        currentDiscographyView = 'popular_releases';
                        renderTracks(5);


                        // Adiciona evento de clique ao botão para alternar entre 5 e 10 faixas
                        $(document).off('click', '.remaining-popular-music');
                        $(document).on('click', '.remaining-popular-music', function () {
                            showAllTracks = !showAllTracks;
                            renderTracks(showAllTracks ? 10 : 5);
                        });

                        // Adiciona evento de clique para as opções de discografia
                        $(document).off('click', '.discography-option');
                        $(document).on('click', '.discography-option', function () {
                            currentDiscographyView = $(this).data('type');
                            renderDiscographySection()
                        });

                        // Adiciona evento de clique para "Mostrar tudo" na seção de artistas similares
                        function showSimilarArtists() {
                            showAllSimilarArtists = true
                            artistBanner.hide()
                            popularSongsByTheArtist.hide()
                            followers.hide()
                            discographySection.hide()
                            renderSimilarArtistsSection()
                            $('.title2').css('display', 'block')
                            $('.title').css('display', 'none')
                            $('.show-all-similar-artists').css('display', 'none')

                        }
                        $(document).on('click', '.show-all-similar-artists, .title ', function () {
                            showSimilarArtists()

                        })


                    } else {
                        console.error('Erro ao buscar dados do artista:', response.error);
                        popularSongsByTheArtist.html('<h2>Populares</h2><p>Erro ao carregar músicas populares.</p>').show();
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Erro na requisição de detalhes:", error);
                    popularSongsByTheArtist.html('<h2>Populares</h2><p>Erro ao carregar músicas populares.</p>').show();
                }
            });
        }

        // Reseta o estado e carrega os dados
        allTracks = [];
        allAlbums = {};
        showAllTracks = false;
        currentDiscographyView = 'popular_releases';
        similarArtists = []
        showAllSimilarArtists = false
        loadData();
    });

    function formatDuration(ms) {
        const minutes = Math.floor(ms / 60000);
        const seconds = ((ms % 60000) / 1000).toFixed(0);
        return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    // Observador para detectar quando .best-result aparece no DOM
    const observer = new MutationObserver(() => {
        if ($('.best-result').length) {
            console.log('Elemento .best-result encontrado no DOM');
            observer.disconnect();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
});