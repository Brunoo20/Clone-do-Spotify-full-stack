$(document).ready(function () {
    const playlistsSection = $('.playlists-section')
    const mainContent = $('.main-content');


    // Função para renderizar a seção de playlists
    function renderplaylists(playlists) {
        playlistsSection.empty()
        // Filtra playlists válidas (não null e com name)
        const validPlaylists = playlists.filter(playlist => playlist && playlist.name)
        if (validPlaylists.length > 0) {
            const playlistsTitle = $('<h2>').text('Playlists').addClass('section-title playlists');
            const playlistsGrid = $('<div>').addClass('playlists-grid');


            validPlaylists.slice(0, 8).forEach(function (playlist) { // Mostra até 8 playlists
                const playlistsCard = $('<div>').addClass('playlists-card')

                // Verifica se images existe e usa o primeiro item, ou fallback para default.jpg
                const imageUrl = (playlist.images && playlist.images.length > 0) ? playlist.images[0].url : 'default.jpg'
                const ownerName =playlist.owner?.display_name || 'Criador desconhecido'; // Nome do criador da playlist
                playlistsCard.html(`
                    <div class="playlists-image">
                       <img src="${imageUrl}" alt="${playlist.name || 'Playlist sem nome'}">
                    </div>
                    <div class="play-button-playlist">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 384 512">
                            <path
                            d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                        </svg>
                    </div>
                    <a>${playlist.name || 'Playlist sem nome'}</a>
                    <p>De ${ownerName}</p>
                    
               `);
                playlistsGrid.append(playlistsCard);
            });

            playlistsSection.append(playlistsTitle).append(playlistsGrid);
            playlistsSection.show() // Mostra a seção se houver playlists
            mainContent.addClass('searched')


        } else {
            playlistsSection.hide() // Esconde a seção se não houver playlists
            mainContent.removeClass('searched')
        }

    }

    // Função para buscar playlists com base na query
    function fetchPlaylists(playlists) {
        renderplaylists(playlists || [])
        return Promise.resolve()
    }

    window.fetchPlaylists = fetchPlaylists

  


})