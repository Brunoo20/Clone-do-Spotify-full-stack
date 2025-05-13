$(document).ready(function () {
  const newAlbumsReleases = $(".new-albums-releases-content");
  const episodesPodcast = $(".episodes-podcast-content");
  const hitParades = $(".hit-parades-content");
  const searchInput = $(".search-input");
  const mainContent = $(".main-content");
  const resultsGrid = $("#search-results-grid");
  const popularArtists = $(".popular-artists-content");
  const recentlyPlayedContent = $(".recently-played-content");

  // Função otimizada para renderizar novos álbuns
  function renderNewAlbumsReleases(data) {
    newAlbumsReleases.empty(); // Limpa o conteúdo

    const fragment = document.createDocumentFragment();
    const container = $("<div>").addClass("new-albums-container");
    const title = $("<h2>")
      .text("Novos lançamentos para você")
      .addClass("section-title new-albums");
    const grid = $("<div>").addClass("new-albums-grid");
    const showAllLink = $("<a>")
      .text("Mostrar tudo")
      .addClass("content__link-All-new-albums")
      .attr("href", "#");
    const title2 = $("<h2>")
      .text("Novos lançamentos para você")
      .addClass("section-title new-albums2")
      .hide();

    container.append(title).append(showAllLink).append(title2);

    // Renderiza álbuns com limite
    function renderAlbumsReleases(limit) {
      grid.empty();
      const albums = (data.results.newAlbums || []).slice(0, limit);
      albums.forEach((album) => {
        const card = document.createElement("div");
        card.className = "new-albums-card";
        card.innerHTML = `
                    <div class="popular-podcasts-image">
                        <img src="${album.image}" alt="${album.name}" onerror="this.src='default.jpg'">
                    </div>
                    <div class="play-button-new-albums">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                            <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                        </svg>
                    </div>
                    <a>${album.name}</a>
                    <p>${album.artist}</p>
                `;
        fragment.appendChild(card);
      });
      grid.append(fragment);
    }

    renderAlbumsReleases(8); // Inicialmente 8 álbuns

    function showAllContent(e) {
      e.preventDefault();
      renderAlbumsReleases(20);
      showAllLink.hide();
      $(".info-content").hide();
      mainContent.addClass("searched");
      hitParades.hide();
      popularArtists.hide();
      episodesPodcast.hide();
      recentlyPlayedContent.hide();
      title.hide();
      title2.show();
    }
    // Evento "Mostrar tudo"
    showAllLink.on("click", showAllContent);
    title.on("click", showAllContent);

    newAlbumsReleases.append(container).append(grid).show();
  }

  // Evento de input
  searchInput.on("input", function () {
    const query = $(this).val().trim();
    if (query.length > 0) {
      newAlbumsReleases.hide();
      resultsGrid.show();
      mainContent.addClass("searched");
    } else {
      newAlbumsReleases.show();
      resultsGrid.hide();
      mainContent.removeClass("searched");
    }
  });

  // Função para buscar e renderizar novos álbuns ( apenas renderiza os dados recebidos)
  function fetchNewAlbums(albums) {
    renderNewAlbumsReleases(albums || []); // Renderiza os dados recebidos ou limpa se não houver dados
    return Promise.resolve();
  }

  // Expor a função fetchNewAlbums para ser chamada pelo Initialcontent.js
  window.fetchNewAlbums = fetchNewAlbums;
});
