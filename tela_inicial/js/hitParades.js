$(document).ready(function () {
  const hitParades = $(".hit-parades-content");
  const searchInput = $(".search-input");
  const mainContent = $(".main-content");
  const resultsGrid = $("#search-results-grid");
  const popularArtists = $(".popular-artists-content");
  const episodesPodcast = $(".episodes-podcast-content");
  const newAlbumsReleases = $(".new-albums-releases-content");
  const recentlyPlayedContent = $(".recently-played-content");

  // Função otimizada para renderizar os "Hit Parades"
  function renderHitParades(data) {
    hitParades.empty(); // Limpa o conteúdo

    const fragment = document.createDocumentFragment();
    const container = $("<div>").addClass("episodes-podcasts-container");
    const title = $("<h2>")
      .text("Paradas")
      .addClass("section-title episodes-podcasts");
    const grid = $("<div>").addClass("episodes-podcasts-grid");
    const showAllLink = $("<a>")
      .text("Mostrar tudo")
      .addClass("content__link-All-episodes-podcasts")
      .attr("href", "#");
    const title2 = $("<h2>")
      .text("Paradas")
      .addClass("section-title episodes-podcasts2")
      .hide();

    container.append(title).append(showAllLink).append(title2);

    // Renderiza artistas com limite e em lotes
    function renderHits(limit) {
      grid.empty();
      const parades = (data.results.hitParades || []).slice(0, limit);
      const batchSize = 4; // Renderiza em lotes de 4 artistas
      let index = 0;
      function renderBatch() {
        const endIndex = Math.min(index + batchSize, parades.length);
        const batch = parades.slice(index, endIndex);

        batch.forEach((parade) => {
          const card = document.createElement("div");
          card.className = "episodes-podcasts-card";
          const truncatedDescription =
            parade.description.length > 44
              ? parade.description.slice(0, 44) + "..."
              : parade.description;
          card.innerHTML = `
                        <div class="popular-podcasts-image">
                            <img src="${parade.image}" alt="${parade.name}" onerror="this.src='default.jpg'">
                        </div>
                        <div class="play-button-episodes-podcasts">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                            </svg>
                        </div>
                        <a>${parade.name}</a>
                        <p>${truncatedDescription}</p>
                    `;
          fragment.appendChild(card);
        });
        grid.append(fragment);
        index += batchSize;

        if (index < parades.length) {
          requestAnimationFrame(renderBatch);
        }
      }
      renderBatch();
    }

    renderHits(8); // Inicialmente 8 paradas

    function showAllContent(e) {
      e.preventDefault();
      renderHits(20);
      showAllLink.hide();
      $(".info-content").hide();
      mainContent.addClass("searched");
      title.hide();
      title2.show();
      newAlbumsReleases.hide();
      popularArtists.hide();
      episodesPodcast.hide();
      recentlyPlayedContent.hide();
    }

    // Evento "Mostrar tudo"
    showAllLink.on("click", showAllContent);
    title.on("click", showAllContent);

    hitParades.append(container).append(grid).show();
  }

  // Evento de input
  searchInput.on("input", function () {
    const query = $(this).val().trim();
    if (query.length > 0) {
      hitParades.hide();
      resultsGrid.show();
      mainContent.addClass("searched");
    } else {
      hitParades.show();
      resultsGrid.hide();
      mainContent.removeClass("searched");
    }
  });

  // Função para buscar e renderizar paradas de sucesso ( apenas renderiza os dados recebidos)
  function fetchHitParades(paredes) {
    renderHitParades(paredes || []);
    return Promise.resolve();
  }

  // Expor a função fetchHitParades para ser chamada pelo Initialcontent.js
  window.fetchHitParades = fetchHitParades;
});
