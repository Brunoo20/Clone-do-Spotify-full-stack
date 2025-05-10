$(document).ready(function () {
  const artistsSection = $('.artists-section')
  const mainContent = $('.main-content');


  // Função para renderizar a seção de artistas
  function renderArtists(artists) {
    artistsSection.empty()
    if (artists.length > 0) {
      const artistsTitle = $('<h2>').text('Artistas').addClass('section-title artists');
      const artistsGrid = $('<div>').addClass('artists-grid');


      artists.slice(0, 8).forEach(function (artist) { // Mostra até 8 artistas
        const artistCard = $('<div>').addClass('artist-card');
        artistCard.html(`
          <div class="artist-image">
            <img src="${artist.images[0]?.url || 'default.jpg'}" alt="${artist.name}">
          </div>
          <div class="play-button-artist">
            <svg xmlns="http://www.w3.org/2000/svg"viewBox="0 0 384 512">
              <path
              d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
            </svg>
          </div>
          <a class="artist-name">${artist.name}</a>
          <p>Artista</p>
        `);
        artistsGrid.append(artistCard);
      });

      artistsSection.append(artistsTitle).append(artistsGrid);
      artistsSection.show()
      mainContent.addClass('searched')
    } else {
      artistsSection.hide()
      mainContent.removeClass('searched')
    }

  }

  // Função para "buscar" álbuns (apenas renderiza os dados recebidos)
  function fetchArtists(artists) {
    renderArtists(artists || []) // Renderiza os dados recebidos ou limpa se não houver dados
    return Promise.resolve()


  }

  // Expor a função fetchArtists para ser chamada pelo searchHandler.js
  window.fetchArtists = fetchArtists





})