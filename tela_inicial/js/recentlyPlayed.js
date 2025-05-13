$(document).ready(function () {
  const recentlyPlayedContent = $(".recently-played-content");
  const popularArtists = $(".popular-artists-content");
  const episodesPodcast = $(".episodes-podcast-content");
  const newAlbumsReleases = $(".new-albums-releases-content");
  const hitParades = $(".hit-parades-content");
  const mainContent = $(".main-content");
  const resultsGrid = $("#search-results-grid");
  const searchInput = $(".search-input");

  // Função otimizada para renderizar artistas com renderização incremental
  function renderRecentlyPlayedContent(data) {
    recentlyPlayedContent.empty(); // Limpa o conteúdo

    const fragment = document.createDocumentFragment(); // Usar fragment para melhor performance
    const container = $("<div>").addClass("recently-played-container");
    const title = $("<h2>")
      .text("Tocados recentemente")
      .addClass("section-title recently-played");
    const grid = $("<div>").addClass("recently-played-grid");
    const showAllLink = $("<a>")
      .text("Mostrar tudo")
      .addClass("content__link-All-recently-played")
      .attr("href", "#");
    const title2 = $("<h2>")
      .text("Tocados recentemente")
      .addClass("section-title recently-played2")
      .hide();

    container.append(title).append(showAllLink).append(title2);

    // Renderiza artistas com limite e em lotes
    function renderRecentlyPlayed(limit) {
      grid.empty();
      const items = (data.results.recentlyPlayedResult || []).slice(0, limit);
      const batchSize = 4; // Renderiza em lotes de 4 artistas
      let index = 0;

      function renderBatch() {
        const endIndex = Math.min(index + batchSize, items.length);
        const batch = items.slice(index, endIndex);

        batch.forEach((items) => {
          const card = document.createElement("div");
          card.className = "recently-played-card";
          card.innerHTML = `
                        <div >
                            <img src="${items.image}" alt="${items.name}">
                        </div>
                        <div class="play-button-recently-played-card">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" />
                            </svg>
                        </div>
                        <a href="${items.uri}">${items.name}</a>
                        <p>${items.description}</p>
                      
                    `;

          fragment.appendChild(card);
        });

        grid.append(fragment);
        index += batchSize;

        if (index < items.length) {
          requestAnimationFrame(renderBatch);
        }
      }

      renderBatch();
    }

    renderRecentlyPlayed(8); // Inicialmente 8 artistas

    function showAllContent(e) {
      e.preventDefault();
      renderRecentlyPlayed(20);
      showAllLink.hide();
      $(".info-content").hide();
      mainContent.addClass("searched");
      episodesPodcast.hide();
      hitParades.hide();
      newAlbumsReleases.hide();
      popularArtists.hide();
      title.hide();
      title2.show();
    }

    recentlyPlayedContent.append(container).append(grid).show();

    showAllLink.on("click", showAllContent);

    title.on("click", showAllContent);
  }

  // Evento de input
  searchInput.on("input", function () {
    const query = $(this).val().trim();
    if (query.length > 0) {
      recentlyPlayedContent.hide();
      resultsGrid.show();
      mainContent.addClass("searched");
    } else {
      recentlyPlayedContent.show();
      resultsGrid.hide();
      mainContent.removeClass("searched");
    }
  });
  // Função para buscar e renderizar artistas populares ( apenas renderiza os dados recebidos)
  function retrieveRecentlyPlayedContent(recentlyPlayed) {
    renderRecentlyPlayedContent(recentlyPlayed || []); // Renderiza os dados recebidos ou limpa se não houver dados
    return Promise.resolve();
  }

  // Expor a função fetchPopularArtists para ser chamada pelo Initialcontent.js
  window.retrieveRecentlyPlayedContent = retrieveRecentlyPlayedContent;
});
