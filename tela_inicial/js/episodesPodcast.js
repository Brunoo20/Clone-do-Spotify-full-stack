$(document).ready(function () {
    const episodesPodcast = $('.episodes-podcast-content')
    const hitParades = $('.hit-parades-content')
    const searchInput = $('.search-input')
    const mainContent = $('.main-content')
    const resultsGrid = $('#search-results-grid')
    const popularArtists = $('.popular-artists-content')
    const newAlbumsReleases = $('.new-albums-releases-content')
    const pageOfArtist = $('.page-of-artist')
    const recentlyPlayedContent = $('.recently-played-content')

    isPlaying = false
    currentPodcastUri = null

    if (typeof isPlaying === 'undefined' || typeof currentPodcastUri === 'undefined') {
        console.error('Erro: isPlaying ou currentPodcastUri não estão definidos. Verifique a ordem de carregamento dos scripts.')
        return;
    }

    // Função para renderizar os "Episódios dos Podcasts"
    function renderEpisodesPodcast(data) {
        episodesPodcast.empty() // Limpa o conteúdo

        const fragment = document.createDocumentFragment()
        const container = $('<div>').addClass('episodes-podcasts-container')
        const title = $('<h2>').text('Episódios para você').addClass('section-title episodes-podcasts')
        const grid = $('<div>').addClass('episodes-podcasts-grid')
        const showAllLink = $('<a>').text('Mostrar tudo').addClass('content__link-All-episodes-podcasts').attr('href', '#')
        const title2 = $('<h2>').text('Episódios para você').addClass('section-title episodes-podcasts2').hide()

        container.append(title).append(showAllLink).append(title2)

        // Função para renderizar episódios
        function renderEpisodes(limit) {
            grid.empty();
            const podcasts = (data.results.episodesPodcast || []).slice(0, limit)
            podcasts.forEach(podcast => {
                const card = document.createElement('div')
                card.className = 'episodes-podcasts-card'
                card.setAttribute('data-podcast-uri', podcast.uri)

                // Converter duração para minutos
                const minutes = Math.floor(podcast.duration_ms / 60000)
                const formattedDuration = `${minutes}`

                // Formatar data de lançamento
                const releaseDate = new Date(podcast.release_date);
                const year = releaseDate.getFullYear();
                const month = releaseDate.toLocaleString('pt-BR', { month: 'short' });
                const day = releaseDate.getDate(); // Corrigido: getDay() retorna o dia da semana
                const formattedDate = (year === 2025) ? `${day} de ${month}` : `${month} de ${year}`
                const truncatedName = podcast.name.length > 33
                ? podcast.name.slice(0, 33) + '...'
                : podcast.name

                card.innerHTML = `
                    <div class="popular-podcasts-image">
                        <img src="${podcast.image}" alt="${truncatedName}" onerror="this.src='default.jpg'">
                    </div>
                    <div class="play-button-episodes-podcasts">
                        <svg class="play-icon-podcasts" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                            <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                        </svg>
                        <svg class="pause-icon-podcasts"  role="img" aria-hidden="true" >
                            <path d="M5.7 3a.7.7 0 0 0-.7.7v16.6a.7.7 0 0 0 .7.7h2.6a.7.7 0 0 0 .7-.7V3.7a.7.7 0 0 0-.7-.7H5.7zm10 0a.7.7 0 0 0-.7.7v16.6a.7.7 0 0 0 .7.7h2.6a.7.7 0 0 0 .7-.7V3.7a.7.7 0 0 0-.7-.7h-2.6z"></path>
                        </svg>
                     
                    </div>
                   
                    <a>${truncatedName}</a>
                    <div class="episodes-podcasts-details">
                        <p>${formattedDate}</p> <span class="big-dot2"></span> <p>${formattedDuration}min</p>
                    </div>
                `;
                // Define o estado inicial do ícone com base no estado de reprodução
                const card1 = $(card)
                const playIcon = card1.find('.play-icon-podcasts')
                const pauseIcon = card1.find('.pause-icon-podcasts')
                if (podcasts.uri === currentPodcastUri && isPlaying) {
                    playIcon.removeClass('active')
                    pauseIcon.addClass('active')
                } else {
                    playIcon.addClass('active')
                    pauseIcon.removeClass('active')
                }
                // Adiciona evento de clique para reproduzir o episódio
                $(card).on('click', function () {
                  
                    if (player) {
                        if (currentPodcastUri === podcast.uri && isPlaying) {
                            // Pausar a reprodução
                            sendSpotifyRequest('pause', { device_id: deviceId }).done(function (response) {
                                if (response.success) {
                                    console.log('Reprodução pausada com sucesso:', podcast.name);
                                    isPlaying = false
                                    previousPodcastUri = currentPodcastUri  
                                    //currentPodcastUri = null;
                                    $('.play-button-episodes-podcasts').each(function () {

                                        // Atualiza o card atual
                                        const currentCard = $(this);
                                        const currentPlayIcon = currentCard.find('.play-icon-podcasts')
                                        const currentPauseIcon = currentCard.find('.pause-icon-podcasts')
                                        currentPlayIcon.addClass('active')
                                        currentPauseIcon.removeClass('active')

                                    });
                                } else {
                                    console.error('Erro ao pausar a reprodução:', response.error);
                                }
                            }).fail(function (jqXHR, textStatus) {
                                console.error('Falha na requisição pause:', textStatus)
                                console.log('Resposta do servidor:', jqXHR.responseText)
                            });
                        } else {
                            // Iniciar ou retomar a reprodução
                            const playRequest = {
                                device_id: deviceId,
                                episode_uri: podcast.uri
                            }


                            if (currentPodcastUri === podcast.uri && podcastPositions[podcast.uri]) {
                                playRequest.position_ms = podcastPositions[podcast.uri];
                            }
                            sendSpotifyRequest('play', { device_id: deviceId, episode_uri: podcast.uri }).done(function (response) {
                                if (response.success) {
                                   
                                    // Atualiza o podcast anterior, se houver
                                    if (currentPodcastUri && currentPodcastUri !== podcast.uri) {
                                        previousPodcastUri = currentPodcastUri;
                                        const previousCard = $(`.episodes-podcasts-card[data-podcast-uri="${previousPodcastUri}"]`);
                                        if (previousCard.length) {
                                            const previousPlayIcon = previousCard.find('.play-icon-podcasts');
                                            const previousPauseIcon = previousCard.find('.pause-icon-podcasts');
                                            previousPlayIcon.addClass('active');
                                            previousPauseIcon.removeClass('active');
                                        }
                                    }

                                    // Atualiza o estado atual
                                    isPlaying = true;
                                    currentPodcastUri = podcast.uri;

                                    // Atualiza o card atual
                                    const currentCard = $(this);
                                    const currentPlayIcon = currentCard.find('.play-icon-podcasts');
                                    const currentPauseIcon = currentCard.find('.pause-icon-podcasts');
                                    currentPlayIcon.removeClass('active');
                                    currentPauseIcon.addClass('active');
                                } else {
                                    console.error('Erro ao iniciar reprodução do episódio:', response.error);
                                }
                            }).fail(function (jqXHR, textStatus) {
                                console.error('Falha na requisição play:', textStatus);
                                console.log('Resposta do servidor:', jqXHR.responseText);
                            });
                        }
                    }
                });


                fragment.appendChild(card);
            })


            grid.append(fragment);
        }

        renderEpisodes(8); // Inicialmente 8 episódios

        // Evento "Mostrar tudo"
        showAllLink.on('click', function (e) {
            e.preventDefault();
            renderEpisodes(20);
            showAllLink.hide();
            $('.info-content').hide();
            mainContent.addClass('searched');
            hitParades.hide();
            newAlbumsReleases.hide()
            popularArtists.hide()
            recentlyPlayedContent.hide()
            title.hide();
            title2.show();
           
        })

        episodesPodcast.append(container).append(grid).show();
    }

    

    // Evento de input
    searchInput.on('input', function () {
        const query = $(this).val().trim();
        if (query.length > 0) {
            episodesPodcast.hide();
            resultsGrid.show();
            mainContent.addClass('searched');
        } else {
            episodesPodcast.show();
            resultsGrid.hide();
            pageOfArtist.hide()
            mainContent.removeClass('searched');
           
        }
    })

    function fetchPopularPodcasts(podcast){
        renderEpisodesPodcast(podcast || [])
        return Promise.resolve()
    }

    window.fetchPopularPodcasts = fetchPopularPodcasts

   
})