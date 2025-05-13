$(document).ready(function () {
    // Função debounce 
    function debounce(func, wait) {
        let timeout
        return function (...args) {
            clearTimeout(timeout)
            timeout = setTimeout(() => func.apply(this, args), wait)
        }
    }
    window.debounce = debounce

    // Cache para armazenar resultados de buscas anteriores
    const searchCache = new Map() // Map<query, responseData>
    const cacheTTL = 300000 // 5 minutos em milissegundos

    // Função para verificar se o cache expirou
    function isCacheValid(timestamp) {
        return (Date.now() - timestamp) < cacheTTL
    }

    // Estado para controle
    let lastQuery = '' // Último termo buscado
    let abortController = null // Controlador para cancelar requisições
    const maxRetries = 3 // Máximo de retentativas
    const baseRetryDelay = 1000 // Delay base para retentativas (1s)

    // Função para buscar dados com retentativa e backoff
    function fetchSearchData(query, retries = 0) {
        const cachedEntry = searchCache.get(query)
        if (cachedEntry && isCacheValid(cachedEntry.timestamp)) {
            console.log('Resultado obtido do cache:', query);
            return Promise.resolve(cachedEntry.data)
        }

        if (abortController) {
            abortController.abort()
        }

        abortController = new AbortController()
        const signal = abortController.signal

        return $.ajax({
            url: 'Search/Search.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ query: query }),
            dataType: 'json',
            signal: signal
        })
            .done(function (response) {
                if (response.success) {
                  
                    searchCache.set(query, { data: response.results, timestamp: Date.now() })
                    return response.results
                } else {
                    throw new Error(response.error || 'Erro na busca')
                }
            })
            .fail(function (jqXHR, textStatus) {
                if (textStatus === 'abort') {
                    console.log('Requisição de busca cancelada:', query);
                    return Promise.reject('Requisição cancelada')
                }

                console.error('Erro na busca:', textStatus)
                if (retries < maxRetries && jqXHR.status !== 400) {
                    const delay = baseRetryDelay * Math.pow(2, retries)
                    console.log(`Retendo busca (${retries + 1}/${maxRetries}) após ${delay}ms...`)
                    return new Promise(resolve => setTimeout(resolve, delay))
                        .then(() => fetchSearchData(query, retries + 1))

                } else {
                    console.error('Máximo de retentativas atingido ou erro irrecuperável:', textStatus)
                    renderError('Erro ao buscar resultados. Tente novamente mais tarde.')
                }

            })
            .always(function () {
                abortController = null
            })
    }

    // Função para lidar com a busca
    function handlerSearch(query) {
        query = query.trim()

        // Se o input estiver vazio, limpa os resultados e não faz requisição
        if (query === '') {
          
            // Chama as funções de busca com dados vazios para limpar os resultados
            if (window.fetchArtists) window.fetchArtists([])
            if (window.fetchAlbums) window.fetchAlbums([])
            if (window.fetchPlaylists) window.fetchPlaylists([])
            if (window.fetchBestResultAndTracks) window.fetchBestResultAndTracks({ bestResult: null, tracks: [] })
            lastQuery = ''
            return
        }

        // Evita busca para termos repetidos
        if (query === lastQuery) {
            console.log('Termo repetido, ignorando busca:', query)
            return
        }

        lastQuery = query
       


        // Faz uma única requisição ao backend e distribui os resultados
        fetchSearchData(query)
            .then(data => {
                // Chama as funções de busca de cada página com os dados já obtidos
                if (window.fetchArtists) window.fetchArtists(data.results.artists || [])
                if (window.fetchAlbums) window.fetchAlbums(data.results.albums || [])
                if (window.fetchPlaylists) window.fetchPlaylists(data.results.playlists || [])
                if (window.fetchBestResultAndTracks) window.fetchBestResultAndTracks({ 
                    bestResult: data.results.best_result || null, 
                    tracks: data.results.tracks || [] 
                })

            })
            .catch(err => {
                console.error('Erro ao realizar busca:', err)
                // Limpa os resultados em caso de erro
                if (window.fetchArtists) window.fetchArtists([])
                if (window.fetchAlbums) window.fetchAlbums([])
                if (window.fetchPlaylists) window.fetchPlaylists([])
                if (window.fetchBestResultAndTracks) window.fetchBestResultAndTracks({ bestResult: null, tracks: []})
            })

    }

    // Função de inicialização da busca
    function initSearch() {
        const searchInput = $('.search-input')
        if (!searchInput.length) {
            console.log('Campo de busca não encontrado. ')
            return
        }

        const debouncedSearch = window.debounce(handlerSearch, 500)
        searchInput.on('input', function () {
            const query = $(this).val()
            debouncedSearch(query)
        })

        // Garante que os cards padrão sejam exibidos inicialmente
        handlerSearch('')
    }

    // Expor a função initSearch no escopo global
    window.initSearch = initSearch

})





























