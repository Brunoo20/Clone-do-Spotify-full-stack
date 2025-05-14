// Intercepta requisições para cpapi.spotify.com para ignorar erros 403 e 404
// Salva a função fetch original do navegador
const originalFetch = window.fetch;
// Substitui a função fetch global para interceptar chamadas específicas
window.fetch = async function (input, init) {
  // Extrai a URL da requisição (string ou objeto Request)
  const url = typeof input === "string" ? input : input.url;
  // Verifica se a URL contém "cpapi.spotify.com"
  if (url.includes("cpapi.spotify.com")) {
    try {
      // Executa a requisição original
      const response = await originalFetch(input, init);
      // Ignora erros 403 (Proibido) ou 404 (Não encontrado), retornando uma resposta mock
      if (response.status === 403 || response.status === 404) {
        console.log(`Ignorando erro ${response.status} para cpapi.spotify.com`);
        return new Response(JSON.stringify({ success: true }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        });
      }
      return response;
    } catch (error) {
      // Captura qualquer erro na requisição e retorna uma resposta mock
      console.log("Ignorando erro de fetch para cpapi.spotify.com");
      return new Response(JSON.stringify({ success: true }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }
  }
  // Executa a requisição original para URLs não relacionadas a cpapi.spotify.com
  return originalFetch(input, init);
};

// Variáveis globais

const volumeBar = $(".volume-bar"); // Seleciona o elemento da barra de volume
const reproductionBar = $(".playback-progress-bar input.reproduction-bar"); // Seleciona o input da barra de reprodução dentro do elemento de progresso
// Botões de avançar e retroceder
const next = $(".next");
const back = $(".back");
const repeatButton = $(".repeat-button"); // Botão de modo de repetição

let currentRepeatMode = "off"; // Estado inicial do modo de repetição
let previousVolume = volumeBar.val(); // Armazena o volume inicial da barra
let isVolumeHovered = false; // Indica se o mouse está sobre a barra de volume
let isReproductionHovered = false; // Indica se o mouse está sobre a barra de reprodução
let player = null; // Objeto do player Spotify (inicializado posteriormente)
let deviceId = null; // ID do dispositivo associado ao player
let isPlayingPodcast = false; // Indica se um podcast está sendo reproduzido
let isPlayingBestResult = false; // Indica se o conteúdo do "melhor resultado" está sendo reproduzido
let isPlaying2 = false; // Evita múltiplas requisições simultâneas
let currentPodcastUri = null; // URI do podcast atualmente em reprodução
let previousPodcastUri = null; // URI do podcast reproduzido anteriormente
let previousTrackUri; // URI da faixa reproduzida anteriormente
let positionUpdateInterval = null; // Intervalo para atualização da posição de reprodução
let podcastPositions = {}; // Objeto para armazenar posições de reprodução de podcasts
let trackPositions = {}; // Objeto para armazenar posições de reprodução de faixas
let justPaused = false; // Indica se a reprodução foi pausada recentemente
let isSeeking = false; // Indica se o usuário está ajustando a posição de reprodução
let lastSeekPosition = null; // Última posição de busca (seek) realizada
let localVolume = 100; // Volume local armazenado (padrão: 100%)
let isVolumeInitialized = false; // Indica se o volume foi inicializado
let isFirstPlay = true; // Indica se é a primeira reprodução
let lastTrackUri = null; // URI da última faixa exibida
let lastPodcastUri = null; // URI do último podcast exibido
let currentTrackUri = null; // URI da faixa atual
let isPlayingTracks = false; // Indica se faixas estão sendo reproduzidas
let topTracks = []; // Lista de faixas principais (top tracks)
let currentTrackIndex = 0; // Índice da faixa atual na lista topTracks

// Função para formatar o tempo (milissegundos para MM:SS ou H:MM:SS)
function formatTime(ms) {
  // Converte milissegundos para segundos
  const totalSeconds = Math.floor(ms / 1000);
  // Calcula horas
  const hours = Math.floor(totalSeconds / 3600);
  // Calcula minutos restantes
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  // Calcula segundos restantes
  const seconds = totalSeconds % 60;

  // Retorna formato H:MM:SS se houver horas
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, "0")}:${seconds
      .toString()
      .padStart(2, "0")}`;
  }
  // Retorna formato MM:SS para durações menores
  return `${minutes}:${seconds.toString().padStart(2, "0")}`;
}

// Função para atualizar o gradiente da barra de reprodução
function updateGradientReproductionBar() {
  // Obtém a posição atual e a duração total da barra
  const position = parseInt(reproductionBar.val(), 10);
  const duration = parseInt(reproductionBar.attr("max"), 10) || 1;
  // Calcula a porcentagem de progresso
  const percentage = (position / duration) * 100;
  // Define a cor com base no estado de hover
  const color = isReproductionHovered ? "#1db954" : "#fff";
  // Aplica o gradiente à barra
  reproductionBar.css(
    "background",
    `linear-gradient(to right, ${color} ${percentage}%, #666 ${percentage}%)`
  );
}

// Função para atualizar a posição e a barra de progresso durante a reprodução
function updatePlaybackPosition() {
  // Sai se o player não está definido, não está reproduzindo ou está buscando
  if (!player || !isPlayingPodcast || !isPlayingBestResult || isSeeking) return;

  // Obtém o estado atual do player
  player
    .getCurrentState()
    .then((state) => {
      if (!state) return;

      // Obtém posição e duração do conteúdo
      let position = state.position || 0;
      const duration = state.duration || 0;
      const currentUri = currentPodcastUri || currentTrackUri;
      const isPodcast = currentUri?.startsWith("spotify:episode:");

      // Se pausado recentemente, usa a posição salva
      if (justPaused && position === 0 && currentUri) {
        position = isPodcast
          ? podcastPositions[currentUri]
          : trackPositions[currentUri];
      }

      // Ignora atualização se a posição diverge muito de um seek recente
      if (
        lastSeekPosition !== null &&
        Math.abs(position - lastSeekPosition) > 1000
      ) {
        return;
      }

      // Formata a posição e duração para exibição
      const formattedPosition = formatTime(position);
      const formattedDuration = formatTime(duration);

      // Atualiza os elementos de interface
      $(".playback-position").text(formattedPosition);
      $(".playback-duration").text(formattedDuration);
      reproductionBar.attr("max", duration);
      reproductionBar.val(position);
      updateGradientReproductionBar();
    })
    .catch((err) => {
      console.error("Erro ao obter estado do player:", err);
    });
}

// Função para ajustar a posição de reprodução (seek)
function seek(positionMs) {
  // Verifica se o player e o deviceId estão disponíveis
  if (!player || !deviceId) {
    console.error("Player ou deviceId não estão disponíveis.");
    return;
  }

  // Atualiza a interface imediatamente
  const formattedPosition = formatTime(positionMs);
  $(".playback-position").text(formattedPosition);
  reproductionBar.val(positionMs);
  // Determina se é podcast ou faixa
  const currentUri = currentPodcastUri || currentTrackUri;
  const isPodcast = currentUri?.startsWith("spotify:episode:");
  // Armazena a posição atual
  if (currentUri) {
    if (isPodcast) {
      podcastPositions[currentUri] = positionMs;
    } else {
      trackPositions[currentUri] = positionMs;
    }
  }

  // Define a última posição de busca
  lastSeekPosition = positionMs;

  // Envia a requisição de seek ao backend
  sendSpotifyRequest("seek", { device_id: deviceId, position_ms: positionMs })
    .done(function (response) {
      if (response.success) {
        console.log(`Busca realizada com sucesso para ${positionMs}ms`);
        // Verifica o estado atual para confirmar a posição
        player.getCurrentState().then((state) => {
          if (state) {
            if (!isPodcast) trackPositions[currentTrackUri] = state.position;
            lastSeekPosition = null; // Reseta após confirmação
          }
        });
      } else {
        console.error("Erro ao buscar posição:", response.error);
        lastSeekPosition = null;
      }
    })
    .fail(function (jqXHR, textStatus) {
      console.error("Falha na requisição seek:", textStatus);
      console.log("Resposta do servidor:", jqXHR.responseText);
      lastSeekPosition = null;
    });
}

// Função para atualizar os ícones de play/pause na interface
function updateIcons(isPlaying) {
  const pauseIconMain = $(".play-pause-button .pause");
  const playIconMain = $(".play-pause-button .play");

  // Atualiza o botão principal de play/pause
  if (isPlaying) {
    playIconMain.hide();
    pauseIconMain.show();
  } else {
    playIconMain.show();
    pauseIconMain.hide();
  }

  // Atualiza os ícones nos botões de "melhor resultado"
  $(".best-result-button").each(function () {
    const playIcon = $(this).find(".play-icon-best-result");
    const pauseIcon = $(this).find(".pause-icon-best-result");
    if (isPlaying) {
      playIcon.removeClass("active");
      pauseIcon.addClass("active");
    } else {
      playIcon.addClass("active");
      pauseIcon.removeClass("active");
    }
  });
}

// Função para reproduzir conteúdo (podcast ou faixa)
function playContent(uri, positionMs, contentData = null) {
  // Verifica se o deviceId está disponível
  if (!deviceId) {
    console.error("Device ID não disponível.");
    return;
  }

  // Evita chamadas duplicadas
  if (isPlaying2) {
    console.log("Reprodução já em andamento, ignorando chamada duplicada.");
    return;
  }

  isPlaying2 = true;

  // Determina o tipo de conteúdo
  const isPodcast = uri.startsWith("spotify:episode:");
  const contentType = isPodcast ? "podcast" : "track";

  // Envia a requisição de play ao backend
  sendSpotifyRequest("play", {
    device_id: deviceId,
    uris: [uri],
    position_ms: positionMs,
  })
    .done((response) => {
      console.log("Resposta do play:", response);
      if (response.success) {
        // Atualiza estados de reprodução
        isPlayingPodcast = true;
        isPlayingBestResult = true;
        // Atualiza URIs com base no tipo de conteúdo
        if (isPodcast) {
          currentPodcastUri = uri;
          currentTrackUri = null;
          lastPodcastUri = uri;
          podcastPositions[uri] = podcastPositions[uri] || positionMs || 0;
        } else {
          currentTrackUri = uri;
          currentPodcastUri = null;
          lastTrackUri = uri;
          trackPositions[uri] = trackPositions[uri] || positionMs || 0;
        }

        // Usa os dados fornecidos ou retornados para atualizar a interface
        const dataToUse = response.data || contentData;
        console.log(dataToUse);
        if (dataToUse) {
          $(".content-name").text(dataToUse.name);
          $(".content-artista").text(dataToUse.artist);
          if (dataToUse.album_image) {
            $(".content-img").attr("src", dataToUse.album_image).show();
          } else {
            $(".content-img").hide();
          }
        } else {
          console.warn(
            `Nenhum dado retornado para ${contentType}, tentando buscar manualmente`
          );
          getCurrentContent();
        }

        updateIcons(true);
      } else {
        console.error("Erro ao iniciar reprodução:", response.message);
        updateIcons(false);
      }
    })
    .fail(function (textStatus) {
      console.error("Falha na requisição play:", textStatus);
    })
    .always(function () {
      isPlaying2 = false;
    });
}

// Função para obter informações do conteúdo atual
function getCurrentContent() {
  // Verifica se o deviceId está disponível
  if (!deviceId) {
    console.error("Device ID não disponível.");
    return;
  }

  // Envia a requisição para obter a faixa atual
  sendSpotifyRequest("get_current_track", { device_id: deviceId })
    .done(function (response) {
      if (response.success && response.data) {
        const contentData = response.data;
        const isPodcast = contentData.uri.startsWith("spotify:episode: ");

        // Atualiza URIs globais
        if (isPodcast) {
          currentPodcastUri = contentData.uri;
          currentTrackUri = null;
          lastPodcastUri = contentData.uri;
        } else {
          currentTrackUri = contentData.uri;
          currentPodcastUri = null;
          lastTrackUri = contentData.uri;
        }

        // Atualiza a interface
        $(".content-name").text(contentData.name);
        $(".content-artista").text(contentData.artist);
        if (contentData.album_image) {
          $(".content-img").attr("src", contentData.album_image).show();
        } else {
          $(".content-img").attr("src", "").hide();
        }
      } else {
        console.error("Erro ao obter faixa atual:", response.message);
        $(".content-name").text("Nenhuma faixa em reprodução");
        $(".content-artista").text("");
        $(".content-img").hide();
      }
    })
    .fail(function (jqXHR, textStatus) {
      console.error("Falha na requisição get_current_track:", textStatus);
      console.log("Resposta bruta:", jqXHR.responseText);
      $(".content-name").text("Erro ao carregar faixa");
      $(".content-artista").text("");
      $(".content-img").hide();
    });
}

// Função chamada quando o Spotify Web Playback SDK está pronto
window.onSpotifyWebPlaybackSDKReady = () => {
  // Obtém o token de autenticação
  sendSpotifyRequest("get_token").done(function (response) {
    if (response.success) {
      const token = response.token;

      // Inicializa o player Spotify
      player = new Spotify.Player({
        name: "web Playback SDK Player",
        getOAuthToken: (cb) => {
          cb(token);
        },
        volume: 1.0, // Volume inicial em 100%
      });

      // Listener para quando o dispositivo está pronto
      player.addListener("ready", ({ device_id }) => {
        deviceId = device_id;
      });

      // Listener para quando o dispositivo fica offline
      player.addListener("not_ready", ({ device_id }) => {
        console.log("Dispositivo offline com ID:", device_id);
      });

      // Listener para mudanças no estado do player
      player.addListener("player_state_changed", (state) => {
        if (!state || state.loading || isPlaying2) return;

        const currentUri = state.track_window.current_track.uri;
        const isPodcast = currentUri.startsWith("spotify:episode:");

        // Atualiza URIs globais e sincroniza currentTrackIndex
        if (isPodcast) {
          currentPodcastUri = currentUri;
          currentTrackUri = null;
          if (currentUri !== lastPodcastUri) {
            lastPodcastUri = currentUri;
            getCurrentContent();
          }
        } else {
          currentTrackUri = currentUri;
          currentPodcastUri = null;
          if (currentUri !== lastTrackUri) {
            lastTrackUri = currentUri;
            // Sincroniza currentTrackIndex com a faixa atual
            const newIndex = topTracks.findIndex(
              (track) => track.uri === currentUri
            );
            if (newIndex !== -1) {
              currentTrackIndex = newIndex;
              console.log(
                "currentTrackIndex atualizado para:",
                currentTrackIndex
              );
            }
            getCurrentContent();
          }
        }

        // Atualiza estados de reprodução
        isPlayingPodcast = !state.paused;
        isPlayingBestResult = !state.paused;

        // Armazena a posição atual, exceto durante seek
        if (!isSeeking && state.position > 0) {
          if (isPodcast) {
            podcastPositions[currentUri] = state.position;
          } else {
            trackPositions[currentUri] = state.position;
          }
        }

        // Verifica se a faixa terminou e toca a próxima
        if (
          !isPodcast &&
          state.paused &&
          !state.loading &&
          topTracks.length > 0
        ) {
          const currentIndex = topTracks.findIndex(
            (track) => track.uri === currentTrackUri
          );
          console.log("Verificando fim da faixa:", {
            currentIndex: currentIndex,
            position: state.position,
            duration: state.duration,
            conditionMet: state.position >= state.duration - 20000,
          });
          if (currentIndex !== -1 && state.position >= state.duration - 20000) {
            if (currentIndex + 1 < topTracks.length) {
              currentTrackIndex = currentIndex + 1;
              const nextTrack = topTracks[currentTrackIndex];
              isPlayingPodcast = true;
              isPlayingBestResult = true;
              playContent(nextTrack.uri, 0, nextTrack).then(() => {
                updateIcons(true);
              });
              return;
            } else {
              console.log("Fim da fila de faixas.");
              isPlayingPodcast = false;
              isPlayingBestResult = false;
              updateIcons(false);
            }
          }
        }

        // Sincroniza o volume
        const stateVolume = Math.round(state.playback_volume * 100);
        if (!isVolumeInitialized) {
          if (isFirstPlay && stateVolume !== 100) {
            player.setVolume(1.0).then(() => {
              localVolume = 100;
              volumeBar.val(100).trigger("input");
            });
          } else {
            localVolume = stateVolume;
            volumeBar.val(localVolume).trigger("input");
          }
          isVolumeInitialized = true;
        } else if (Math.abs(stateVolume - localVolume) > 5) {
          localVolume = stateVolume;
          volumeBar.val(localVolume).trigger("input");
        }

        // Atualiza o botão principal de play/pause
        const pauseIconMain = $(".play-pause-button .pause");
        const playIconMain = $(".play-pause-button .play");
        if (isPlayingPodcast && isPlayingBestResult) {
          playIconMain.hide();
          pauseIconMain.show();
          if (isFirstPlay) isFirstPlay = false;
        } else {
          pauseIconMain.hide();
          playIconMain.show();
        }

        // Atualiza ícones dos cards de podcast
        if (previousPodcastUri && previousPodcastUri !== currentPodcastUri) {
          const previousCard = $(
            `.episodes-podcasts-card[data-podcast-uri="${previousPodcastUri}"]`
          );
          if (previousCard.length) {
            previousCard.find(".play-icon-podcasts").addClass("active");
            previousCard.find(".pause-icon-podcasts").removeClass("active");
          }
        }

        if (currentPodcastUri) {
          const currentCard = $(
            `.episodes-podcasts-card[data-podcast-uri="${currentPodcastUri}"]`
          );
          if (currentCard.length) {
            const currentPlayIcon = currentCard.find(".play-icon-podcasts");
            const currentPauseIcon = currentCard.find(".pause-icon-podcasts");
            if (isPlayingPodcast && isPlayingBestResult) {
              currentPlayIcon.removeClass("active");
              currentPauseIcon.addClass("active");
            } else {
              currentPlayIcon.addClass("active");
              currentPauseIcon.removeClass("active");
            }
          }
        }

        // Atualiza ícones de todos os botões de "melhor resultado"
        $(".best-result-button").each(function () {
          const playIcon = $(this).find(".play-icon-best-result");
          const pauseIcon = $(this).find(".pause-icon-best-result");
          if (isPlayingBestResult) {
            playIcon.removeClass("active");
            pauseIcon.addClass("active");
          } else {
            playIcon.addClass("active");
            pauseIcon.removeClass("active");
          }
        });

        // Atualiza a posição de reprodução
        if (!isSeeking && lastSeekPosition === null) {
          let position = state.position;
          const duration = state.duration;
          const currentUriToUse = currentPodcastUri || currentTrackUri;
          const isPodcastToUse =
            currentUriToUse?.startsWith("spotify:episode:");

          // Usa posição salva se pausado recentemente
          if (justPaused && position === 0 && currentUriToUse) {
            position = isPodcastToUse
              ? podcastPositions[currentUriToUse] || 0
              : trackPositions[currentUriToUse] || 0;
          }

          // Atualiza a interface
          const formattedPosition = formatTime(position);
          const formattedDuration = formatTime(duration);
          $(".playback-position").text(formattedPosition);
          $(".playback-duration").text(formattedDuration);
          reproductionBar.attr("max", duration);
          reproductionBar.val(position);

          // Gerencia o intervalo de atualização da posição
          if (
            isPlayingPodcast &&
            isPlayingBestResult &&
            !positionUpdateInterval
          ) {
            positionUpdateInterval = setInterval(updatePlaybackPosition, 1000);
            justPaused = false;
          } else if (
            !isPlayingPodcast &&
            isPlayingBestResult &&
            positionUpdateInterval
          ) {
            clearInterval(positionUpdateInterval);
            positionUpdateInterval = null;
            justPaused = true;
          }

          // Força seek para posição salva, se necessário
          if (
            position === 0 &&
            currentUriToUse &&
            isPlayingPodcast &&
            isPlayingBestResult &&
            currentRepeatMode !== "track"
          ) {
            const savedPosition = isPodcast
              ? podcastPositions[currentUriToUse]
              : trackPositions[currentUriToUse];
            if (savedPosition > 0) {
              console.log(
                `Forçando seek para posição salva: ${savedPosition}ms`
              );
              seek(savedPosition);
            }
          }
        }
      });

      // Listener para erros de autenticação
      player.addListener("authentication_error", ({ message }) => {
        console.error("Erro de autenticação:", message);
        // Tenta atualizar o token
        sendSpotifyRequest("refresh_token").done(function (response) {
          if (response.success) {
            player.setOAuthToken(response.token);
          }
        });
      });

      // Conecta o player
      player.connect();
    } else {
      // Redireciona para a página inicial se o token não for obtido
      window.location.replace("../firstPage.php");
    }
  });
};

// Função para enviar requisições ao backend
function sendSpotifyRequest(action, data = []) {
  return $.ajax({
    url: "Search/Get_player.php",
    method: "POST",
    contentType: "application/json",
    data: JSON.stringify({ action, ...data }),
    dataType: "json",
  });
}

// Código executado quando o DOM está pronto
$(document).ready(function () {
  // Inicializa a funcionalidade de busca, se disponível
  if (typeof window.initSearch === "function") {
    window.initSearch();
  } else {
    console.error(
      "initSearch não está definido. Verifique se searchHandler.js foi carregado corretamente."
    );
  }

  // Seleciona elementos da interface
  const volumeIcon = $(".volume-icon");
  const alignVolumeBar = $(".align-volume-bar");
  const playAndPause = $(".play-pause-button");

  // Função para atualizar o gradiente da barra de volume
  function updateGradientVolumeBar() {
    const volume = volumeBar.val();
    const color = isVolumeHovered ? "#1db954" : "#fff";
    volumeBar.css(
      "background",
      `linear-gradient(to right, ${color} ${volume}%, #666 ${volume}%)`
    );
  }

  // Eventos de hover para a barra de reprodução
  $(".playback-progress-bar").on("mouseenter", function () {
    isReproductionHovered = true;
    updateGradientReproductionBar();
  });

  $(".playback-progress-bar").on("mouseleave", function () {
    isReproductionHovered = false;
    updateGradientReproductionBar();
  });

  // Eventos para ajustar a posição de reprodução
  reproductionBar.on("mousedown", function () {
    isSeeking = true;
  });

  reproductionBar.on("input", function () {
    const positionMs = parseInt($(this).val(), 10);
    const formattedPosition = formatTime(positionMs);
    $(".playback-position").text(formattedPosition);
    updateGradientReproductionBar();
  });

  reproductionBar.on("change", function () {
    const positionMs = parseInt($(this).val(), 10);
    seek(positionMs);
    isSeeking = false;
  });

  reproductionBar.on("click", function (e) {
    e.preventDefault();
    // Calcula a posição clicada na barra
    const rect = this.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    const width = rect.width;
    const duration = parseInt($(this).attr("max"), 10) || 0;
    const positionMs = Math.floor((clickX / width) * duration);

    // Atualiza a interface e realiza o seek
    $(this).val(positionMs);
    const formattedPosition = formatTime(positionMs);
    $(".playback-position").text(formattedPosition);
    updateGradientReproductionBar();
    seek(positionMs);
  });

  // Evento de input para a barra de volume
  volumeBar.on("input", function () {
    let volume = $(this).val();
    localVolume = volume;
    updateGradientVolumeBar();

    // Ajusta o volume no player
    if (player) {
      player
        .setVolume(volume / 100)
        .then(() => {})
        .catch((err) => {
          console.error("Erro ao ajustar volume no player:", err);
        });
    }

    // Atualiza o ícone de volume com base no nível
    if (volume == 0) {
      volumeIcon.html(`
        <svg role="presentation" aria-label="Sem som" aria-hidden="false">
            <path d="M13.86 5.47a.75.75 0 0 0-1.061 0l-1.47 1.47-1.47-1.47A.75.75 0 0 0 8.8 6.53L10.269 8l-1.47 1.47a.75.75 0 1 0 1.06 1.06l1.47-1.47 1.47 1.47a.75.75 0 0 0 1.06-1.06L12.39 8l1.47-1.47a.75.75 0 0 0 0-1.06z"></path>
            <path d="M10.116 1.5A.75.75 0 0 0 8.991.85l-6.925 4a3.642 3.642 0 0 0-1.33 4.967 3.639 3.639 0 0 0 1.33 1.332l6.925 4a.75.75 0 0 0 1.125-.649v-1.906a4.73 4.73 0 0 1-1.5-.694v1.3L2.817 9.852a2.141 2.141 0 0 1-.781-2.92c.187-.324.456-.594.78-.782l5.8-3.35v1.3c.45-.313.956-.55 1.5-.694V1.5z"></path>
        </svg>
      `);
    } else if (volume > 0 && volume <= 30) {
      volumeIcon.html(`
        <svg role="presentation" aria-label="Volume baixo" aria-hidden="false">
            <path d="M9.741.85a.75.75 0 0 1 .375.65v13a.75.75 0 0 1-1.125.65l-6.925-4a3.642 3.642 0 0 1-1.33-4.967 3.639 3.639 0 0 1 1.33-1.332l6.925-4a.75.75 0 0 1 .75 0zm-6.924 5.3a2.139 2.139 0 0 0 0 3.7l5.8 3.35V2.8l-5.8 3.35zm8.683 4.29V5.56a2.75 2.75 0 0 1 0 4.88z"></path>
        </svg>
      `);
    } else if (volume > 30 && volume <= 65) {
      volumeIcon.html(`
        <svg role="presentation" aria-label="Volume médio" aria-hidden="false">
            <path d="M9.741.85a.75.75 0 0 1 .375.65v13a.75.75 0 0 1-1.125.65l-6.925-4a3.642 3.642 0 0 1-1.33-4.967 3.639 3.639 0 0 1 1.33-1.332l6.925-4a.75.75 0 0 1 .75 0zm-6.924 5.3a2.139 2.139 0 0 0 0 3.7l5.8 3.35V2.8l-5.8 3.35zm8.683 6.087a4.502 4.502 0 0 0 0-8.474v1.65a2.999 2.999 0 0 1 0 5.175v1.649z"></path>
        </svg>
      `);
    } else if (volume > 65 && volume <= 100) {
      volumeIcon.html(`
        <svg role="presentation" aria-label="Volume alto" aria-hidden="false">
            <path d="M9.741.85a.75.75 0 0 1 .375.65v13a.75.75 0 0 1-1.125.65l-6.925-4a3.642 3.642 0 0 1-1.33-4.967 3.639 3.639 0 0 1 1.33-1.332l6.925-4a.75.75 0 0 1 .75 0zm-6.924 5.3a2.139 2.139 0 0 0 0 3.7l5.8 3.35V2.8l-5.8 3.35zm8.683 4.29V5.56a2.75 2.75 0 0 1 0 4.88z"></path>
            <path d="M11.5 13.614a5.752 5.752 0 0 0 0-11.228v1.55a4.252 4.252 0 0 1 0 8.127v1.55z"></path>
        </svg>
      `);
    }
  });

  // Evento de clique no ícone de volume para mutar/desmutar
  volumeIcon.parent().on("click", function () {
    let currentVolume = volumeBar.val();
    if (currentVolume > 0) {
      // Salva o volume atual e muta
      previousVolume = currentVolume;
      volumeBar.val(0).trigger("input");
      volumeIcon.html(`
        <svg role="presentation" aria-label="Sem som" aria-hidden="false">
            <path d="M13.86 5.47a.75.75 0 0 0-1.061 0l-1.47 1.47-1.47-1.47A.75.75 0 0 0 8.8 6.53L10.269 8l-1.47 1.47a.75.75 0 1 0 1.06 1.06l1.47-1.47 1.47 1.47a.75.75 0 0 0 1.06-1.06L12.39 8l1.47-1.47a.75.75 0 0 0 0-1.06z"></path>
            <path d="M10.116 1.5A.75.75 0 0 0 8.991.85l-6.925 4a3.642 3.642 0 0 0-1.33 4.967 3.639 3.639 0 0 0 1.33 1.332l6.925 4a.75.75 0 0 0 1.125-.649v-1.906a4.73 4.73 0 0 1-1.5-.694v1.3L2.817 9.852a2.141 2.141 0 0 1-.781-2.92c.187-.324.456-.594.78-.782l5.8-3.35v1.3c.45-.313.956-.55 1.5-.694V1.5z"></path>
        </svg>
      `);
    } else {
      // Restaura o volume anterior
      volumeBar.val(previousVolume).trigger("input");
      volumeIcon.html(`
        <svg role="presentation" aria-label="Volume alto" aria-hidden="false">
            <path d="M9.741.85a.75.75 0 0 1 .375.65v13a.75.75 0 0 1-1.125.65l-6.925-4a3.642 3.642 0 0 1-1.33-4.967 3.639 3.639 0 0 1 1.33-1.332l6.925-4a.75.75 0 0 1 .75 0zm-6.924 5.3a2.139 2.139 0 0 0 0 3.7l5.8 3.35V2.8l-5.8 3.35zm8.683 4.29V5.56a2.75 2.75 0 0 1 0 4.88z"></path>
            <path d="M11.5 13.614a5.752 5.752 0 0 0 0-11.228v1.55a4.252 4.252 0 0 1 0 8.127v1.55z"></path>
        </svg>
      `);
    }
  });

  // Eventos de hover para a barra de volume
  alignVolumeBar.on("mouseenter", function () {
    isVolumeHovered = true;
    updateGradientVolumeBar();
  });

  alignVolumeBar.on("mouseleave", function () {
    isVolumeHovered = false;
    updateGradientVolumeBar();
  });

  // Configuração inicial do volume
  volumeBar.val(localVolume).trigger("input");

  // Função para alternar entre play e pause
  function togglePlayPause() {
    if (!deviceId) {
      console.error("ID do dispositivo não está disponível.");
      return;
    }
    if (isPlayingPodcast && isPlayingBestResult) {
      // Pausa a reprodução
      sendSpotifyRequest("pause", { device_id: deviceId })
        .done(function (response) {
          if (response.success) {
            console.log("Reprodução pausada com sucesso");
            isPlayingPodcast = false;
            isPlayingBestResult = false;
          } else {
            console.error("Erro ao pausar a reprodução:", response.error);
          }
        })
        .fail(function (jqXHR, textStatus) {
          console.error("Falha na requisição pause:", textStatus);
          console.log("Resposta do servidor:", jqXHR.responseText);
        });
    } else {
      // Retoma a reprodução
      const currentUri = currentTrackUri || currentPodcastUri;
      if (!currentUri) {
        console.error("Nenhuma URI de faixa/podcast encontrada.");
        return;
      }
      const isPodcast = currentUri.startsWith("spotify:episode:");
      const positionMs = isPodcast
        ? podcastPositions[currentUri] || 0
        : trackPositions[currentUri] || 0;

      sendSpotifyRequest("play", {
        device_id: deviceId,
        uris: [currentUri],
        position_ms: positionMs,
      })
        .done(function (response) {
          if (response.success) {
            console.log(
              "Reprodução retomada com sucesso na posição:",
              positionMs
            );
            isPlayingPodcast = true;
            isPlayingBestResult = true;
            // Restaura o volume local
            player.setVolume(localVolume / 100).then(() => {
              console.log("Volume restaurado ao retomar:", localVolume);
              volumeBar.val(localVolume).trigger("input");
            });
          } else {
            console.error("Erro ao retomar a reprodução:", response.error);
          }
        })
        .fail(function (jqXHR, textStatus) {
          console.error("Falha na requisição play:", textStatus);
          console.log("Resposta do servidor:", jqXHR.responseText);
        });
    }
  }

  // Evento de clique no botão de retroceder
  back.on("click", function (e) {
    e.preventDefault();
    if (!deviceId) {
      console.error("Device ID não disponível.");
      return;
    }

    if (isPlaying2) {
      console.log("Ação em andamento, ignorando clique duplicado");
      return;
    }

    isPlaying2 = true;

    // Verifica o contexto de reprodução
    player
      .getCurrentState()
      .then((state) => {
        if (topTracks && topTracks.length > 0 && currentTrackIndex > 0) {
          isPlayingBestResult = true;
          isPlayingPodcast = true;
          previousTrackManualy();
        } else {
          isPlayingBestResult = false;
          isPlayingPodcast = false;
          updateIcons(false);
          isPlaying2 = false;
        }
      })
      .catch((err) => {
        console.error("Erro ao verificar estado do player:", err);
        if (topTracks && currentTrackIndex > 0) {
          previousTrackManualy();
        } else {
          isPlaying2 = false;
        }
      });
  });

  // Função para reproduzir a faixa anterior manualmente
  function previousTrackManualy() {
    if (topTracks && topTracks.length > 1) {
      // Calcula o índice da faixa anterior
      const backIndex = (currentTrackIndex - 1) % topTracks.length;
      sendSpotifyRequest("play", {
        device_id: deviceId,
        uris: [topTracks[backIndex].uri],
      })
        .done(function (playResponse) {
          console.log("Reprodução manual bem-sucedida:", playResponse);
          currentTrackIndex = backIndex;
          console.log("currentTrackIndex atualizado para:", currentTrackIndex);
          isPlayingPodcast = true;
          isPlayingBestResult = true;
          syncPlayerState();
          updateIcons(true);
          getCurrentContent();
        })
        .fail(function (jqXHR, textStatus) {
          console.error("Falha ao reproduzir manualmente:", textStatus);
          isPlayingBestResult = false;
          isPlayingPodcast = false;
          updateIcons(false);
          getCurrentContent();
        })
        .always(function () {
          isPlaying2 = false;
        });
    } else {
      console.error("Nenhuma faixa disponível para avançar.");
      isPlayingBestResult = false;
      isPlayingPodcast = false;
      updateIcons(false);
      getCurrentContent();
      isPlaying2 = false;
    }
  }

  // Evento de clique no botão de avançar
  next.on("click", function (e) {
    e.preventDefault();
    if (!deviceId) {
      console.error("Device ID não disponível.");
      return;
    }

    if (isPlaying2) {
      console.log("Ação em andamento, ignorando clique duplicado");
      return;
    }

    isPlaying2 = true;

    // Verifica o contexto de reprodução
    player
      .getCurrentState()
      .then((state) => {
        if (
          topTracks &&
          topTracks.length > 1 &&
          currentTrackIndex + 1 < topTracks.length
        ) {
          isPlayingBestResult = true;
          isPlayingPodcast = true;
          playNextTrackManually();
        } else {
          isPlayingBestResult = false;
          isPlayingPodcast = false;
          updateIcons(false);
          isPlaying2 = false;
        }
      })
      .catch((err) => {
        console.error("Erro ao verificar estado do player:", err);
        playNextTrackManually();
      });
  });

  // Função para reproduzir a próxima faixa manualmente
  function playNextTrackManually() {
    if (topTracks && topTracks.length > 1) {
      // Calcula o índice da próxima faixa
      const nextIndex = (currentTrackIndex + 1) % topTracks.length;
      sendSpotifyRequest("play", {
        device_id: deviceId,
        uris: [topTracks[nextIndex].uri],
      })
        .done(function (playResponse) {
          console.log("Reprodução manual bem-sucedida:", playResponse);
          currentTrackIndex = nextIndex;
          console.log("currentTrackIndex atualizado para:", currentTrackIndex);
          isPlayingPodcast = true;
          isPlayingBestResult = true;
          syncPlayerState();
          updateIcons(true);
          getCurrentContent();
        })
        .fail(function (jqXHR, textStatus) {
          console.error("Falha ao reproduzir manualmente:", textStatus);
          isPlayingBestResult = false;
          isPlayingPodcast = false;
          updateIcons(false);
          getCurrentContent();
        })
        .always(function () {
          isPlaying2 = false;
        });
    } else {
      console.error("Nenhuma faixa disponível para avançar.");
      isPlayingBestResult = false;
      isPlayingPodcast = false;
      updateIcons(false);
      getCurrentContent();
      isPlaying2 = false;
    }
  }

  // Função para sincronizar o estado do player
  function syncPlayerState() {
    player
      .getCurrentState()
      .then((state) => {
        if (state && state.track_window.current_track) {
          const currentUri = state.track_window.current_track.uri;
          const newIndex = topTracks.findIndex(
            (track) => track.uri === currentUri
          );
          if (newIndex !== -1) {
            currentTrackIndex = newIndex;
            console.log("currentTrackIndex sincronizado:", currentTrackIndex);
          }

          if (topTracks && currentTrackIndex + 1 < topTracks.length) {
            updateIcons(true);
          } else {
            updateIcons(false);
          }

          getCurrentContent();
        } else {
          console.warn("Nenhum estado do player disponível.");
          getCurrentContent();
        }
      })
      .catch((err) => {
        console.error("Erro ao sincronizar estado:", err);
        getCurrentContent();
      });
  }

  // Função para atualizar a interface do botão de repetição
  function updateRepeatButton() {
    const repeatIcon = repeatButton.find(".repeat-icon");

    // Remove estado ativo e define título padrão
    repeatButton.removeClass("active");
    repeatButton.attr("title", "Modo de repetição: Desativado");

    // Atualiza para modo ativo, se necessário
    if (currentRepeatMode === "track") {
      repeatButton.addClass("active");
      repeatButton.attr("title", "Modo de repetição: Ativado");
    }
  }

  // Função para configurar o modo de repetição
  function setRepeatMode(state) {
    if (!deviceId) {
      console.error("Device ID não disponível.");
      return;
    }

    // Envia a requisição para configurar o modo de repetição
    sendSpotifyRequest("repeat_mode", {
      device_id: deviceId,
      state: state,
    })
      .done(function (response) {
        if (response.success) {
          console.log(`Modo de repetição ajustado para: ${state}`);
          currentRepeatMode = state;
          updateRepeatButton();
        } else {
          console.error(
            "Erro ao configurar modo de repetição:",
            response.error
          );
        }
      })
      .fail(function (jqXHR, textStatus) {
        console.error("Falha na requisição repeat_mode:", textStatus);
        console.log("Resposta do servidor:", jqXHR.responseText);
      });
  }

  // Evento de clique no botão de repetição
  repeatButton.on("click", function () {
    // Alterna entre os modos de repetição
    const nextMode = currentRepeatMode === "track" ? "off" : "track";
    setRepeatMode(nextMode);
  });

  // Função para sincronizar o modo de repetição inicial
  function syncRepeatMode() {
    if (!player) return;
    player
      .getCurrentState()
      .then((state) => {
        if (state && state.repeat_mode !== undefined) {
          // Mapeia o estado numérico do Spotify SDK para string
          const modeMap = {
            0: "off",
            2: "track",
          };
          currentRepeatMode = modeMap[state.repeat_mode] || "off";
          updateRepeatButton();
        }
      })
      .catch((err) => {
        console.error("Erro ao sincronizar modo de repetição:", err);
      });
  }

  // Sincroniza o modo de repetição quando o dispositivo está pronto
  player?.addListener("ready", ({ device_id }) => {
    deviceId = device_id;
    console.log("Dispositivo conectado, deviceId:", deviceId);
    syncRepeatMode();
  });

  // Atualiza o modo de repetição quando o estado do player muda
  player?.addListener("player_state_changed", (state) => {
    if (state && state.repeat_mode !== undefined) {
      const modeMap = {
        0: "off",
        2: "track",
      };
      currentRepeatMode = modeMap[state.repeat_mode] || "off";
      updateRepeatButton();
    }
  });

  // Configuração inicial do botão de repetição
  updateRepeatButton();

  // Evento global para a barra de espaço
  $(document).on("keydown", function (e) {
    // Ignora se o foco está em um campo de entrada
    const isTyping = $("input, textarea").is(":focus");
    if (e.code === "Space" && !isTyping) {
      e.preventDefault();
      togglePlayPause();
    }
  });

  // Evento de clique no botão de play/pause
  playAndPause.on("click", function () {
    togglePlayPause();
  });
});
