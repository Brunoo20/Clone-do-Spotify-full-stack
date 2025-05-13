$(document).ready(function () {
    const resultsGrid = $('#search-results-grid');
    const mainContent = $('.main-content');
    const pageOfArtist = $('.page-of-artist');

    // Função para inicializar os ícones de um botão específico
    function initializeIcons(button) {
        const playIcon = button.find('.play-icon-best-result');
        const pauseIcon = button.find('.pause-icon-best-result');
        playIcon.addClass('active'); // Define o play como ativo inicialmente
        pauseIcon.removeClass('active');
    }


    // Função para renderizar bestResult e tracks
    function renderBestResultAndTracks(data) {
        resultsGrid.empty()
        pageOfArtist.hide()

        if (!data || (!data.bestResult && !data.tracks)) {
            mainContent.removeClass('searched')
            return
        }

        const bestResult = data.bestResult || null
        const tracks = data.tracks || []
        const container = $('<div>').addClass('search-container');

        // Contêiner para os títulos
        const titlesContainer = $('<div>').addClass('titles-container');

        // Título "Melhor resultado"
        const bestTitle = $('<h2>').text('Melhor resultado').addClass('section-title best');
        titlesContainer.append($('<div>').addClass('title-box').append(bestTitle));

        // Título "Músicas" (só se houver mais resultados)
        if (bestResult || tracks.length > 0) {
            const tracksTitle = $('<h2>').text('Músicas').addClass('section-title tracks');
            titlesContainer.append($('<div>').addClass('title-box').append(tracksTitle));
        }

        resultsGrid.append(titlesContainer)

        const resultsContainer = $('<div>').addClass('results-container');

        // Renderizar o "Melhor Resultado"
        if (bestResult && bestResult.type === 'artist') {
            let artistImageUrl = 'default.jpg'; // Fallback
            if (bestResult.images && bestResult.images.length > 0) {
                artistImageUrl = bestResult.images[0].url;
            }

            const bestResultDiv = $('<div>').addClass('best-result').data('artist-id', bestResult.id);
            bestResultDiv.html(`
                            <div class="best-result-image">
                                <img src="${artistImageUrl}" alt="${bestResult.name}" class="artist-image">
                            </div>
                            <div class="best-result-info">
                                <h2>${bestResult.name}</h2>
                                <p class="best-result-type">Artista</p>
                            </div>
                            <div class="best-result-button">
                                <svg class="play-icon-best-result" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                    <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                                </svg>
                                <svg class="pause-icon-best-result" role="img" aria-hidden="true">
                                    <path d="M5.7 3a.7.7 0 0 0-.7.7v16.6a.7.7 0 0 0 .7.7h2.6a.7.7 0 0 0 .7-.7V3.7a.7.7 0 0 0-.7-.7H5.7zm10 0a.7.7 0 0 0-.7.7v16.6a.7.7 0 0 0 .7.7h2.6a.7.7 0 0 0 .7-.7V3.7a.7.7 0 0 0-.7-.7h-2.6z"></path>
                                </svg>
                            </div>
                        `);
            resultsContainer.append(bestResultDiv)


            // Inicializa os ícones do botão recém-criado
            initializeIcons(bestResultDiv.find('.best-result-button'));
        }

        // Renderizar as músicas (tracks)
        if (tracks.length > 0) {
            const tracksSection = $('<div>').addClass('tracks-section');
            const maxTracks = Math.min(4, tracks.length); // Limita a 4 músicas

            tracks.slice(0, maxTracks).forEach(function (track) {
                let trackImageUrl = 'default.jpg'; // Fallback
                if (track.images && track.images.length > 0) {
                    trackImageUrl = track.images[0].url;
                }

                const div = $('<div>').addClass('track-card');
                div.html(`
                    <div class="track-image-container">
                        <img src="${trackImageUrl}" alt="${track.name}">
                        <div class="track-play-button" onclick="playTrack('${track.id}')">
                            <svg class="play-icon-tracks" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                            </svg>
                           
                        </div>
                    </div>
                    <div class="track-info">
                        <p class="track-name">${track.name}</p>
                            <p class="track-artist">${track.artists[0].name}</p>
                    </div>
                    <span class="track-duration">${formatDuration(track.duration_ms)}</span>
                `);
                tracksSection.append(div);
            });

            resultsContainer.append(tracksSection);
        } else {
            resultsContainer.append('<p>Nenhum resultado adicional encontrado.</p>');
        }

        container.append(resultsContainer)
        resultsGrid.append(container);
        mainContent.addClass('searched');

    }
 


       // Função para "buscar" bestResult e tracks (apenas renderiza os dados recebidos)
       function fetchBestResultAndTracks(data) {
        if (!data || (!data.bestResult && !data.tracks)) {
            renderBestResultAndTracks(null) // limpa os resultados
        } else {
            renderBestResultAndTracks(data) // Renderiza os dados recebidos
        }

        return Promise.resolve()
    }

    // Expor a função fetchBestResultAndTracks para ser chamada pelo searchHandler.js
    window.fetchBestResultAndTracks = fetchBestResultAndTracks

  




    $(document).on('click', '.track-play-button', function (e) {
        e.stopPropagation()
        alert('Music')
    })

    $(document).on('click', '.best-result-button', function (e) {
        e.stopPropagation();
        const artistId = $(this).closest('.best-result').data('artist-id');

        if (!deviceId) {
            console.error('Device ID não disponível.');
            return;
        }

        // Verifica se topTracks corresponde ao artistId
        const isSameArtist = topTracks.length > 0 && topTracks.some(track => track.artist_id === artistId)


        if (isPlayingBestResult) {
            sendSpotifyRequest('pause', { device_id: deviceId })
                .done(function (response) {
                    if (response.success) {
                        console.log('Reprodução pausada');

                        isPlayingBestResult = false
                    }
                })
                .fail(function (jqXHR, textStatus) {
                    console.error('Erro ao pausar:', textStatus, jqXHR.responseText);
                });
        } else if (isSameArtist && currentTrackUri) {
            const positionMs = trackPositions[currentTrackUri] || 0;
            console.log('Retomando:', { uri: currentTrackUri, position: formatTime(positionMs) });

            sendSpotifyRequest('play', {
                device_id: deviceId,
                uris: [currentTrackUri],
                position_ms: positionMs
            })
                .always(function (response, textStatus, jqXHR) {
                    console.log('Resposta completa (always):', { response, textStatus, jqXHR }); // Log em qualquer caso
                })
                .done(function (response) {
                    if (response.success) {
                        console.log('Reprodução retomada na posição:', formatTime(positionMs));

                        isPlayingBestResult = true

                        const currentTrack = topTracks.find(t => t.uri === currentTrackUri);
                        if (currentTrack) {
                            $('.content-name').text(currentTrack.name);
                            $('.content-artista').text(currentTrack.artist);
                            if (currentTrack.album_image) {
                                $('.content-img').attr('src', currentTrack.album_image).show();
                            } else {
                                $('.content-img').hide();
                            }
                        }
                    } else {
                        console.error('Erro ao retomar reprodução:', response.error);
                    }
                })
                .fail(function (jqXHR, textStatus) {
                    console.error('Erro ao retomar:', textStatus, jqXHR.responseText);
                });
        } else {
           
            sendSpotifyRequest('get_artist_top_tracks', { artist_id: artistId })
                .done(function (data) {
                    if (data.success && data.tracks?.length > 0) {
                        topTracks = data.tracks;
                        currentTrackIndex = 0;
                        const track = topTracks[currentTrackIndex];
                        const trackUris = topTracks.map(t => t.uri);

                        sendSpotifyRequest('play', {
                            device_id: deviceId,
                            uris: trackUris,
                            offset: { position: currentTrackIndex },
                            position_ms: 0
                        })
                            .done(function (response) {
                                if (response.success) {

                                    isPlayingBestResult = true
                                    currentTrackUri = track.uri;
                                    $('.content-name').text(track.name);
                                    $('.content-artista').text(track.artist);
                                    if (track.album_image) {
                                        $('.content-img').attr('src', track.album_image).show();
                                    } else {
                                        $('.content-img').hide();
                                    }
                                } else {
                                    console.error('Erro ao iniciar reprodução:', response.error);
                                }
                            })
                            .fail(function (jqXHR, textStatus) {
                                console.error('Erro ao iniciar reprodução:', textStatus, jqXHR.responseText);
                            });
                    }
                })
                .fail(function (jqXHR, textStatus) {
                    console.error('Erro ao buscar top tracks:', textStatus);
                });
        }


    });



    // Evento de clique no .best-result para disparar o evento personalizado
    $(document).on('click', '.best-result', function (e) {
        if ($(e.target).closest('.best-result-button').length) return;

        const artistId = $(this).data('artist-id');
        if (artistId) {
            console.log('Disparando evento showArtistPage com artistId:', artistId);
            $(document).trigger('showArtistPage', [artistId]);
        } else {
            console.error("ID do artista não encontrado!");
        }
    });

    function formatDuration(ms) {
        const minutes = Math.floor(ms / 60000);
        const seconds = ((ms % 60000) / 1000).toFixed(0);
        return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }


   
});