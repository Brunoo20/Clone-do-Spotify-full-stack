$(document).ready(function () {
  // Cache para armazenar resultados de buscas anteriores
  const searchCache = new Map(); // Map<query, responseData>
  const cacheTTL = 3600000;

  // Função para verificar se o cache expirou
  function isCacheValid(timestamp) {
    return Date.now() - timestamp < cacheTTL;
  }

  // Estado para controle
  let lastQuery = ""; // Última requisição feita
  let abortController = null; // Controlador para cancelar requisições
  const maxRetries = 3; // Máximo de retentativas
  const baseRetryDelay = 1000; // Delay base para retentativas (1s)

  // Função para buscar dados com retentativa e backoff
  function fetchUnifiedData(retries = 0) {
    const cacheKey = "all_sections"; // Usamos um cache fixo para todas as seções
    const cachedEntry = searchCache.get(cacheKey);

    // Se temos dados em cache e eles ainda são válidos, usá-los
    if (cachedEntry && isCacheValid(cachedEntry.timestamp)) {
      console.log("Resultados obtidos do cache:", cacheKey);
      return Promise.resolve(cachedEntry.data);
    }

    // Se temos dados antigos (mesmo que expirados) e useStaleData é true, usá-los
    if (cachedEntry && useStaleData) {
      console.log(
        "Usando dados antigos do cache enquanto atualizamos em segundo plano:",
        cacheKey
      );
      // Disparar atualização em segundo plano
      updateInBackground();
      return Promise.resolve(cachedEntry.data);
    }

    // Se não temos dados ou não queremos usar dados antigos, fazer uma nova requisição
    if (abortController) {
      abortController.abort();
    }

    abortController = new AbortController();
    const signal = abortController.signal;

    return $.ajax({
      url: "Search/InitialContent.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({ type: "all_sections" }),
      dataType: "json",
      signal: signal,
    })

      .done(function (response) {
        if (response.success) {
         
         
          searchCache.set(cacheKey, {
            data: response.results,
            timestamp: Date.now(),
          });
          return response.results;
        } else {
          throw new Error(response.error || "Erro ao buscar dados");
        }
      })
      .fail(function (jqXHR, textStatus) {
        if (textStatus === "abort") {
          console.log("Requisição cancelada:", cacheKey);
          return Promise.reject("Requisição cancelada");
        }

        console.error("Erro ao buscar dados:", textStatus);
        if (retries < maxRetries && jqXHR.status !== 400) {
          const delay = baseRetryDelay * Math.pow(2, retries);
          console.log(
            `Retentando busca (${retries + 1}/${maxRetries}) após ${delay}ms...`
          );
          return new Promise((resolve) => setTimeout(resolve, delay)).then(() =>
            fetchUnifiedData(retries + 1)
          );
        } else {
          console.error(
            "Máximo de retentativas atingido ou erro irrecuperável:",
            textStatus
          );
          renderError("Erro ao carregar os dados. Tente novamente mais tarde.");
        }
      })
      .always(function () {
        abortController = null;
      });
  }

  // Função para atualizar dados em segundo plano
  function updateInBackground() {
    fetchUnifiedData(0, false) // Não usar dados antigos, forçar nova requisição
      .then((data) => {
        console.log("Dados atualizados em segundo plano");
        renderSections(data);
      })
      .catch((err) => {
        console.error("Erro ao atualizar dados em segundo plano:", err);
      });
  }

  // Função para renderizar erro
  function renderError(message) {
    $(".main-content").html(`<p>${message}</p>`);
  }

  // Função para renderizar todas as seções
  function renderSections(data) {
    if (window.fetchPopularArtists) {
      window.fetchPopularArtists(data);
    }
    if (window.fetchPopularPodcasts) {
      window.fetchPopularPodcasts(data);
    }
    if (window.retrieveRecentlyPlayedContent) {
      window.retrieveRecentlyPlayedContent(data);
    }
    if (window.fetchNewAlbums) {
      window.fetchNewAlbums(data);
    }
    if (window.fetchHitParades) {
      window.fetchHitParades(data);
    }
  }

  // Função para lidar com a busca
  function handleUnifiedSearch() {
    // Evita requisições repetidas
    if (lastQuery === "all_sections") {
      console.log("Requisição repetida, ignorando:", lastQuery);
      return;
    }

    lastQuery = "all_sections";

    // Faz uma única requisição ao backend e distribui os resultados
    fetchUnifiedData(0, true) // Permitir uso de dados antigos
      .then((data) => {
        renderSections(data);
      })
      .catch((err) => {
        console.error("Erro ao buscar dados:", err);
        // Limpa as seções em caso de erro
        if (window.fetchPopularArtists) window.fetchPopularArtists([]);
        if (window.fetchPopularPodcasts) window.fetchPopularPodcasts([]);
        if (window.retrieveRecentlyPlayedContent)window.retrieveRecentlyPlayedContent([]);
        if (window.fetchNewAlbums) window.fetchNewAlbums([]);
        if (window.fetchHitParades) window.fetchHitParades([]);
      });
  }

  // Função de inicialização
  function initUnifiedSearch() {
    // Executa a busca imediatamente para carregar os dados iniciais
    handleUnifiedSearch();
  }

  // Expor a função initUnifiedSearch no escopo global
  window.initUnifiedSearch = initUnifiedSearch;

  // Inicializa automaticamente
  initUnifiedSearch();
});
