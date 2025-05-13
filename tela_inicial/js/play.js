// Intercepta requisições para cpapi.spotify.com para ignorar erros 403 e 404
const originalFetch = window.fetch;
window.fetch = async function (input, init) {
  const url = typeof input === "string" ? input : input.url;
  if (url.includes("cpapi.spotify.com")) {
    try {
      const response = await originalFetch(input, init);
      if (response.status === 403 || response.status === 404) {
        console.log(`Ignorando erro ${response.status} para cpapi.spotify.com`);
        return new Response(JSON.stringify({ success: true }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        });
      }
      return response;
    } catch (error) {
      console.log("Ignorando erro de fetch para cpapi.spotify.com");
      return new Response(JSON.stringify({ success: true }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }
  }
  return originalFetch(input, init);
};

// Variáveis globais

const volumeBar = $(".volume-bar"); // Elemento da barra de volume
const reproductionBar = $(".playback-progress-bar input.reproduction-bar"); // Elemento da barra de reprodução (input dentro da barra de progresso)
const next = $(".next");
const back = $(".back");
const repeatButton = $(".repeat-button");

let currentRepeatMode = "off"; // Estado inicial do modo de repetição
let previousVolume = volumeBar.val(); // Armazena o volume inicial da barra de volume
let isVolumeHovered = false; // Variável para rastrear se o mouse está sobre a barra de volume
let isReproductionHovered = false; // Variável para rastrear se o mouse está sobre a barra de reprodução
let player = null; // Objeto do player (será inicializado posteriormente)
let deviceId = null; // ID do dispositivo associado ao player
let isPlayingPodcast = false; // Estado da reprodução (true = reproduzindo, false = pausado)
let isPlayingBestResult = false;
let isPlaying2 = false; // Variável para evitar múltiplas requisições simultâneas
let currentPodcastUri = null; // URI do podcast que está sendo reproduzido no momento

let previousPodcastUri = null; // URI do podcast que foi reproduzido anteriormente
let previousTrackUri;
let positionUpdateInterval = null; // Intervalo para atualização da posição de reprodução
let podcastPositions = {}; // Objeto que armazena a posição de reprodução de diferentes podcasts
let trackPositions = {}; // objeto para armazenar posições de faixas
let justPaused = false; // Indica se a reprodução acabou de ser pausada (para evitar ações indesejadas)
let isSeeking = false; // Indica se o usuário está buscando uma posição específica na reprodução (seeking)
let lastSeekPosition = null; // Armazena a última posição de busca (seek) realizada
let localVolume = 100; // Volume local armazenado para controle (padrão: 100%)
let isVolumeInitialized = false;
let isFirstPlay = true; // Indica se é a primeira vez que um podcast está sendo reproduzido
let lastTrackUri = null; //Variável para armazenar a URI da última faixa exibida
let lastPodcastUri = null;
let currentTrackUri = null;
let isPlayingTracks = false;
let topTracks = [];
let currentTrackIndex = 0;

// Função para formatar o tempo (milissegundos para MM:SS ou H:MM:SS)
function formatTime(ms) {
  // Converte milissegundos para segundos
  const totalSeconds = Math.floor(ms / 1000);

  //Divide o total de segundos por 3600 (1h = 3600s)
  const hours = Math.floor(totalSeconds / 3600);

  //Obtém os minutos restantes dividindo por 60
  const minutes = Math.floor((totalSeconds % 3600) / 60);

  //Pega o que sobra após dividir por 60
  const seconds = totalSeconds % 60;

  // Se a duração for maior que 1 hora, usa o formato H:MM:SS
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, "0")}:${seconds
      .toString()
      .padStart(2, "0")}`;
  }
  // Caso contrário, usa o formato MM:SS
  return `${minutes}:${seconds.toString().padStart(2, "0")}`;
}

// Função para atualizar o gradiente da barra de reprodução
function updateGradientReproductionBar() {
  const position = parseInt(reproductionBar.val(), 10);
  const duration = parseInt(reproductionBar.attr("max"), 10) || 1;
  const percentage = (position / duration) * 100;
  const color = isReproductionHovered ? "#1db954" : "#fff";
  reproductionBar.css(
    "background",
    `linear-gradient(to right, ${color} ${percentage}%, #666 ${percentage}%)`
  );
}

// Função para atualizar a posição e a barra de progresso (usada apenas durante reprodução normal)
function updatePlaybackPosition() {
  if (!player || !isPlayingPodcast || !isPlayingBestResult || isSeeking) return;

  player
    .getCurrentState()
    .then((state) => {
      if (!state) return;

      let position = state.position || 0;
      const duration = state.duration || 0;
      const currentUri = currentPodcastUri || currentTrackUri;
      const isPodcast = currentUri?.startsWith("spotify:episode:");

      if (justPaused && position === 0 && currentUri) {
        position = isPodcast
          ? podcastPositions[currentUri]
          : trackPositions[currentUri];
      }

      // Se houve um seek recente, ignorar a posição do estado até que ela esteja próxima da buscada
      if (
        lastSeekPosition !== null &&
        Math.abs(position - lastSeekPosition) > 1000
      ) {
        return; // Ignora atualização automática até o seek ser refletido
      }

      const formattedPosition = formatTime(position);
      const formattedDuration = formatTime(duration);

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
  if (!player || !deviceId) {
    console.error("Player ou deviceId não estão disponíveis.");
    return;
  }

  // Atualiza a interface imediatamente
  const formattedPosition = formatTime(positionMs);
  $(".playback-position").text(formattedPosition);
  reproductionBar.val(positionMs);
  const currentUri = currentPodcastUri || currentTrackUri;
  const isPodcast = currentUri?.startsWith("spotify:episode:");
  if (currentUri) {
    if (isPodcast) {
      podcastPositions[currentUri] = positionMs; // Atualiza a posição armazenada
    } else {
      trackPositions[currentUri] = positionMs;
    }
  }

  lastSeekPosition = positionMs;

  // Envia a requisição ao backend
  sendSpotifyRequest("seek", { device_id: deviceId, position_ms: positionMs })
    .done(function (response) {
      if (response.success) {
        console.log(`Busca realizada com sucesso para ${positionMs}ms`);
        player.getCurrentState().then((state) => {
          if (state) {
            if (!isPodcast) trackPositions[currentTrackUri] = state.position;
            lastSeekPosition = null; // Reseta imediatamente após confirmação
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

function updateIcons(isPlaying) {
  const pauseIconMain = $(".play-pause-button .pause");
  const playIconMain = $(".play-pause-button .play");

  // Verifica o estado atual do player para maior consistência

  if (isPlaying) {
    playIconMain.hide();
    pauseIconMain.show();
  } else {
    playIconMain.show();
    pauseIconMain.hide();
  }

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

function playContent(uri, positionMs, contentData = null) {
  if (!deviceId) {
    console.error("Device ID não disponível.");
    return;
  }

  if (isPlaying2) {
    console.log("Reprodução já em andamento, ignorando chamada duplicada.");
    return;
  }

  isPlaying2 = true;

  // Determina se é podcast ou faixa com base no URI
  const isPodcast = uri.startsWith("spotify:episode:");
  const contentType = isPodcast ? "podcast" : "track";

  sendSpotifyRequest("play", {
    device_id: deviceId,
    uris: [uri], // Envia como array para suportar filas
    position_ms: positionMs,
  })
    .done((response) => {
      console.log("Resposta do play:", response);
      if (response.success) {
        isPlayingPodcast = true;
        isPlayingBestResult = true;
        // Atualiza a URI atual com base no tipo de conteúdo
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

        // Usa os dados fornecidos ou os retornados pelo backend
        const dataToUse = response.data || contentData;
        console.log(dataToUse);
        if (dataToUse) {
          $(".content-name").text(dataToUse.name);
          $(".content-artista").text(dataToUse.artist); // Para podcasts, "artist" é o nome do show
          if (dataToUse.album_image) {
            $(".content-img").attr("src", dataToUse.album_image).show();
          } else {
            $(".content-img").hide();
          }
        } else {
          console.warn(
            `Nenhum dado retornado para ${contentType}, tentando buscar manualmente`
          );
          // Caso o backend não retorne dados, tenta buscar manualmente
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

function getCurrentContent() {
  if (!deviceId) {
    console.error("Device ID não disponível.");
    return;
  }

  sendSpotifyRequest("get_current_track", { device_id: deviceId })
    .done(function (response) {
      if (response.success && response.data) {
        const contentData = response.data;
        const isPodcast = contentData.uri.startsWith("spotify:episode: ");

        // Atualiza as URIs globais com base no tipo
        if (isPodcast) {
          currentPodcastUri = contentData.uri;
          currentTrackUri = null;
          lastPodcastUri = contentData.uri;
        } else {
          currentTrackUri = contentData.uri;
          currentPodcastUri = null;
          lastTrackUri = contentData.uri;
        }

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

window.onSpotifyWebPlaybackSDKReady = () => {
  sendSpotifyRequest("get_token").done(function (response) {
    if (response.success) {
      const token = response.token;

      player = new Spotify.Player({
        name: "web Playback SDK Player",
        getOAuthToken: (cb) => {
          cb(token);
        },
        volume: 1.0, // Volume inicial em 100%
      });

      player.addListener("ready", ({ device_id }) => {
        deviceId = device_id;
      });

      player.addListener("not_ready", ({ device_id }) => {
        console.log("Dispositivo offline com ID:", device_id);
      });

      player.addListener("player_state_changed", (state) => {
        if (!state || state.loading || isPlaying2) return;

        const currentUri = state.track_window.current_track.uri;
        const isPodcast = currentUri.startsWith("spotify:episode:");

        // Atualiza as URIs globais e sincroniza currentTrackIndex
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
            // Sincroniza currentTrackIndex com a faixa atual em topTracks
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

        isPlayingPodcast = !state.paused;
        isPlayingBestResult = !state.paused;

        // Atualiza posições
        if (!isSeeking && state.position > 0) {
          if (isPodcast) {
            podcastPositions[currentUri] = state.position;
          } else {
            trackPositions[currentUri] = state.position;
          }
        }

        // Verifica se a faixa terminou e toca a próxima da fila
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
            conditionMet: state.position >= state.duration - 20000, // 20 segundos
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
              return; // Evita duplicação de lógica
            } else {
              console.log("Fim da fila de faixas.");
              isPlayingPodcast = false; // Para a reprodução ao final da fila
              isPlayingBestResult = false;

              updateIcons(false);
            }
          }
        }

        // Atualiza o volume
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

        // Atualiza os ícones dos cards de podcast
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

        // Atualiza ícones de todos os .best-result-button
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

        // Atualiza a posição
        if (!isSeeking && lastSeekPosition === null) {
          let position = state.position;
          const duration = state.duration;
          const currentUriToUse = currentPodcastUri || currentTrackUri;
          const isPodcastToUse =
            currentUriToUse?.startsWith("spotify:episode:");

          if (justPaused && position === 0 && currentUriToUse) {
            position = isPodcastToUse
              ? podcastPositions[currentUriToUse] || 0
              : trackPositions[currentUriToUse] || 0;
          }

          const formattedPosition = formatTime(position);
          const formattedDuration = formatTime(duration);

          $(".playback-position").text(formattedPosition);
          $(".playback-duration").text(formattedDuration);

          reproductionBar.attr("max", duration);
          reproductionBar.val(position);

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

          // Verifica se deve forçar seek para posição salva
          if (
            position === 0 &&
            currentUriToUse &&
            isPlayingPodcast &&
            isPlayingBestResult&&
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

      player.addListener("authentication_error", ({ message }) => {
        console.error("Erro de autenticação:", message);
        sendSpotifyRequest("refresh_token").done(function (response) {
          if (response.success) {
            player.setOAuthToken(response.token);
          }
        });
      });

      player.connect();
    } else {
      window.location.replace("../firstPage.php");
    }
  });
};

function sendSpotifyRequest(action, data = []) {
  return $.ajax({
    url: "Search/Get_player.php",
    method: "POST",
    contentType: "application/json",
    data: JSON.stringify({ action, ...data }),
    dataType: "json",
  });
}

$(document).ready(function () {
  if (typeof window.initSearch === "function") {
    window.initSearch();
  } else {
    console.error(
      "initSearch não está definido. Verifique se searchHandler.js foi carregado corretamente."
    );
  }

  const volumeIcon = $(".volume-icon");
  const alignVolumeBar = $(".align-volume-bar");
  const playAndPause = $(".play-pause-button");

  // Função para atualizar o gradiente da barra
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

  // Eventos para ajustar a posição ao interagir com a barra
  reproductionBar.on("mousedown", function () {
    isSeeking = true;
  });

  reproductionBar.on("input", function () {
    const positionMs = parseInt($(this).val(), 10);
    const formattedPosition = formatTime(positionMs);
    $(".playback-position").text(formattedPosition);
    updateGradientReproductionBar(); // Atualiza o gradiente enquanto arrasta
  });

  reproductionBar.on("change", function () {
    const positionMs = parseInt($(this).val(), 10);
    seek(positionMs);
    isSeeking = false;
  });

  reproductionBar.on("click", function (e) {
    e.preventDefault();
    const rect = this.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    const width = rect.width;
    const duration = parseInt($(this).attr("max"), 10) || 0;
    const positionMs = Math.floor((clickX / width) * duration);

    $(this).val(positionMs);
    const formattedPosition = formatTime(positionMs);
    $(".playback-position").text(formattedPosition);
    updateGradientReproductionBar();
    seek(positionMs);
  });

  // Evento de input para o slider
  volumeBar.on("input", function () {
    let volume = $(this).val();
    localVolume = volume;
    updateGradientVolumeBar();

    if (player) {
      player
        .setVolume(volume / 100)
        .then(() => {})
        .catch((err) => {
          console.error("Erro ao ajustar volume no player:", err);
        });
    }

    // Lógica para trocar o ícone com base no volume
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

  // Evento de clique no ícone para mutar/desmutar
  volumeIcon.parent().on("click", function () {
    let currentVolume = volumeBar.val();
    if (currentVolume > 0) {
      // Se o volume for maior que 0, armazenamos o valor e mutamos
      previousVolume = currentVolume; // Salva o volume antes de mutar
      volumeBar.val(0).trigger("input");

      volumeIcon.html(`
                <svg role="presentation" aria-label="Sem som" aria-hidden="false">
                    <path d="M13.86 5.47a.75.75 0 0 0-1.061 0l-1.47 1.47-1.47-1.47A.75.75 0 0 0 8.8 6.53L10.269 8l-1.47 1.47a.75.75 0 1 0 1.06 1.06l1.47-1.47 1.47 1.47a.75.75 0 0 0 1.06-1.06L12.39 8l1.47-1.47a.75.75 0 0 0 0-1.06z"></path>
                    <path d="M10.116 1.5A.75.75 0 0 0 8.991.85l-6.925 4a3.642 3.642 0 0 0-1.33 4.967 3.639 3.639 0 0 0 1.33 1.332l6.925 4a.75.75 0 0 0 1.125-.649v-1.906a4.73 4.73 0 0 1-1.5-.694v1.3L2.817 9.852a2.141 2.141 0 0 1-.781-2.92c.187-.324.456-.594.78-.782l5.8-3.35v1.3c.45-.313.956-.55 1.5-.694V1.5z"></path>
                </svg>
            `);
    } else {
      // Se o volume já está 0, restauramos o volume anterior
      volumeBar.val(previousVolume).trigger("input"); // Restaura o volume salvo

      volumeIcon.html(`
                <svg role="presentation" aria-label="Volume alto" aria-hidden="false">
                    <path d="M9.741.85a.75.75 0 0 1 .375.65v13a.75.75 0 0 1-1.125.65l-6.925-4a3.642 3.642 0 0 1-1.33-4.967 3.639 3.639 0 0 1 1.33-1.332l6.925-4a.75.75 0 0 1 .75 0zm-6.924 5.3a2.139 2.139 0 0 0 0 3.7l5.8 3.35V2.8l-5.8 3.35zm8.683 4.29V5.56a2.75 2.75 0 0 1 0 4.88z"></path>
                    <path d="M11.5 13.614a5.752 5.752 0 0 0 0-11.228v1.55a4.252 4.252 0 0 1 0 8.127v1.55z"></path>
                </svg>
            `);
    }
  });

  // Eventos de hover para mudar a cor do gradiente
  alignVolumeBar.on("mouseenter", function () {
    isVolumeHovered = true;
    updateGradientVolumeBar();
  });

  alignVolumeBar.on("mouseleave", function () {
    isVolumeHovered = false;
    updateGradientVolumeBar();
  });

  // Configuração inicial
  volumeBar.val(localVolume).trigger("input");

  // Função para o play e pause
  function togglePlayPause() {
    if (!deviceId) {
      console.error("ID do dispositivo não está disponível.");
      return;
    }
    if (isPlayingPodcast && isPlayingBestResult) {
      // Pausar a reprodução
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
      // Retomar a reprodução da posição atual
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
        position_ms: positionMs, // Adiciona a posição atual
      })
        .done(function (response) {
          if (response.success) {
            console.log(
              "Reprodução retomada com sucesso na posição:",
              positionMs
            );
            isPlayingPodcast = true;
            isPlayingBestResult = true;

            // Garante que o volume local seja mantido ao retomar
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

    // Verificar contexto de reprodução
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

  function previousTrackManualy() {
    if (topTracks && topTracks.length > 1) {
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

    // Verificar contexto de reprodução
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

  function playNextTrackManually() {
    if (topTracks && topTracks.length > 1) {
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

    repeatButton.removeClass("active");
    repeatButton.attr("title", "Modo de repetição: Desativado");

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
    const nextMode = currentRepeatMode === "track" ? "off" : "track";
    setRepeatMode(nextMode);
  });

  // Sincronizar o estado inicial do modo de repetição
  function syncRepeatMode() {
    if (!player) return;
    player
      .getCurrentState()
      .then((state) => {
        if (state && state.repeat_mode !== undefined) {
          // Converte o estado numérico do Spotify SDK para string
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

  // Chamar a sincronização inicial ao conectar o player
  player?.addListener("ready", ({device_id}) => {
    deviceId = device_id
     console.log("Dispositivo conectado, deviceId:", deviceId);
    syncRepeatMode();
  });

  // Atualizar o estado do modo de repetição quando o estado do player mudar
  player?.addListener("player_state_changed", (state) => {
    if (state && state.repeat_mode !== undefined) {
      // Converte o estado numérico do Spotify SDK para string
      const modeMap = {
        0: "off",
        2: "track",
      };
      currentRepeatMode = modeMap[state.repeat_mode] || "off";
      updateRepeatButton();
    }
  });

  // Configuração inicial do botão
  updateRepeatButton();

  // Evento global de pressionamento da barra de espaço
  $(document).on("keydown", function (e) {
    // Verifica se o foco está em um campo de entrada
    const isTyping = $("input , textarea").is(":focus");

    if (e.code === "Space" && !isTyping) {
      e.preventDefault(); // Impede a rolagem da página, mas apenas se não estiver digitando
      togglePlayPause();
    }
  });

  // Evento de clique no botão
  playAndPause.on("click", function () {
    togglePlayPause();
  });
});
