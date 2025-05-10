$(document).ready(function () {
    const albumsSection = $('.albums-section')
    const mainContent = $('.main-content');


    // Função para renderizar a seção de artistas
    function renderAlbums(albums) {
        albumsSection.empty()
        if (albums.length > 0) {
            const albumsTitle = $('<h2>').text('Albúns').addClass('section-title albums');
            const albumsGrid = $('<div>').addClass('albums-grid');


            albums.slice(0, 8).forEach(function (albums) { // Mostra até 8 álbuns
                const artistName = albums.artists && albums.artists.length > 0 ? albums.artists[0].name : 'Artista Desconhecido'
                const releaseDate = albums.release_date ? new Date(albums.release_date).getFullYear() : 'Data Desconhecida'
                const albumsCard = $('<div>').addClass('albums-card');
                albumsCard.html(`
                    <div class="albums-image">
                        <img src="${albums.images[0]?.url || 'default.jpg'}" alt="${albums.name}">
                    </div>
                    <div class="play-button-album">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 384 512">
                            <path
                            d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                        </svg>
                    </div>
                    <a>${albums.name}</a>
                    <div class="album-details">
                        <p>${releaseDate}</p> <span class="big-dot"></span><a>${artistName}</a>
                      
                    </div>
               `);
                albumsGrid.append(albumsCard);
            });

            albumsSection.append(albumsTitle).append(albumsGrid);
            albumsSection.show() // Mostra a seção se houver álbuns
            mainContent.addClass('searched')


        } else {
            albumsSection.hide() // Esconde a seção se não houver álbuns
            mainContent.removeClass('searched')
        }

    }

    // Função para "buscar" álbuns (agora apenas renderiza os dados recebidos)
    function fetchAlbums(albums) {
        renderAlbums(albums || [])  // Renderiza os dados recebidos ou limpa se não houver dados
        return Promise.resolve()
    }

    // Expor a função fetchAlbums para ser chamada pelo searchHandler.js
    window.fetchAlbums = fetchAlbums

  


})