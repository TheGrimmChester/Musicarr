import { Controller } from "@hotwired/stimulus"

/**
 * Artist management Stimulus controller
 * Replaces the legacy ArtistManager class and all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = [
        "container",
        "searchInput",
        "libraryFilter",
        "albumFilter",
        "loadingIndicator",
        "infiniteScrollTrigger",
        "searchResultsInfo",
        "searchResultsText"
    ]

    static values = {
        currentPage: Number,
        isLoading: Boolean,
        hasMoreData: Boolean,
        limit: Number,
        lastSearchQuery: String
    }

    connect() {
        console.log("Artist controller connected")
        console.log('Available targets:', Object.keys(this.targets || {}))
        console.log('hasAlbumFilterTarget:', this.hasAlbumFilterTarget)

        // Initialize default values
        this.currentPageValue = 1
        this.isLoadingValue = false
        this.hasMoreDataValue = true
        this.limitValue = 50
        this.lastSearchQueryValue = ''

        // Initialize search timeout
        this.searchTimeout = null

        // Initialize intersection observer for infinite scroll
        this.infiniteScrollObserver = null

        // Modal state for add artist functionality
        this.selectedArtist = null
        this.libraries = []

        // Initialize modal event handlers
        this.initializeModalHandlers()

        // Load initial data
        this.initialize()
    }

    disconnect() {
        // Clean up observers
        if (this.infiniteScrollObserver) {
            this.infiniteScrollObserver.disconnect()
        }

        // Clear timeouts
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }

        // Clean up modal event listeners
        this.removeModalHandlers()
    }

    initializeModalHandlers() {
        // Add event listeners for modal elements
        const searchBtn = document.getElementById('searchMusicBrainzBtn')
        const saveBtn = document.getElementById('saveArtistBtn')
        const artistSearch = document.getElementById('artistSearch')
        const modal = document.getElementById('addArtistModal')

        if (searchBtn) {
            this.searchMusicBrainzHandler = this.searchMusicBrainz.bind(this)
            searchBtn.addEventListener('click', this.searchMusicBrainzHandler)
        }

        if (saveBtn) {
            this.saveArtistHandler = this.saveArtist.bind(this)
            saveBtn.addEventListener('click', this.saveArtistHandler)
        }

        if (artistSearch) {
            this.artistSearchHandler = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault()
                    this.searchMusicBrainz()
                }
            }
            artistSearch.addEventListener('keypress', this.artistSearchHandler)
        }

        if (modal) {
            this.modalShownHandler = this.onModalShown.bind(this)
            this.modalHiddenHandler = this.onModalHidden.bind(this)
            modal.addEventListener('shown.bs.modal', this.modalShownHandler)
            modal.addEventListener('hidden.bs.modal', this.modalHiddenHandler)
        }
    }

    removeModalHandlers() {
        const searchBtn = document.getElementById('searchMusicBrainzBtn')
        const saveBtn = document.getElementById('saveArtistBtn')
        const artistSearch = document.getElementById('artistSearch')
        const modal = document.getElementById('addArtistModal')

        if (searchBtn && this.searchMusicBrainzHandler) {
            searchBtn.removeEventListener('click', this.searchMusicBrainzHandler)
        }

        if (saveBtn && this.saveArtistHandler) {
            saveBtn.removeEventListener('click', this.saveArtistHandler)
        }

        if (artistSearch && this.artistSearchHandler) {
            artistSearch.removeEventListener('keypress', this.artistSearchHandler)
        }

        if (modal) {
            if (this.modalShownHandler) {
                modal.removeEventListener('shown.bs.modal', this.modalShownHandler)
            }
            if (this.modalHiddenHandler) {
                modal.removeEventListener('hidden.bs.modal', this.modalHiddenHandler)
            }
        }
    }

    // Modal event handlers
    onModalShown() {
        this.populateLibrarySelect()
        const artistSearch = document.getElementById('artistSearch')
        if (artistSearch) {
            artistSearch.focus()
        }
    }

    onModalHidden() {
        this.resetModal()
    }

    // Add artist modal functionality
    async searchMusicBrainz() {
        const searchInput = document.getElementById('artistSearch')
        const query = searchInput?.value?.trim()

        if (!query) {
            this.showError('Please enter an artist name to search')
            return
        }

        const searchBtn = document.getElementById('searchMusicBrainzBtn')
        const resultsDiv = document.getElementById('searchResults')
        const artistResults = document.getElementById('artistResults')

        // Show loading state
        if (searchBtn) {
            searchBtn.disabled = true
            searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...'
        }

        try {
            const response = await fetch(`/artist/search-musicbrainz?q=${encodeURIComponent(query)}`)
            const data = await response.json()

            if (data.success && data.artists) {
                this.displaySearchResults(data.artists)
                if (resultsDiv) {
                    resultsDiv.style.display = 'block'
                }
            } else {
                throw new Error(data.error || 'Search failed')
            }
        } catch (error) {
            console.error('Error searching MusicBrainz:', error)
            this.showError('Error searching for artists: ' + error.message)
            if (artistResults) {
                artistResults.innerHTML = '<div class="alert alert-danger">Error searching for artists</div>'
            }
            if (resultsDiv) {
                resultsDiv.style.display = 'block'
            }
        } finally {
            // Reset search button
            if (searchBtn) {
                searchBtn.disabled = false
                searchBtn.innerHTML = '<i class="fas fa-search"></i> Search'
            }
        }
    }

    displaySearchResults(artists) {
        const artistResults = document.getElementById('artistResults')
        if (!artistResults) return

        artistResults.innerHTML = ''

        if (artists.length === 0) {
            artistResults.innerHTML = '<div class="alert alert-info">No artists found</div>'
            return
        }

        artists.forEach(artist => {
            const artistItem = document.createElement('div')
            artistItem.className = 'list-group-item list-group-item-action'
            artistItem.style.cursor = 'pointer'

            const disambiguation = artist.disambiguation ? ` (${artist.disambiguation})` : ''
            const lifeSpan = artist.life_span ? ` • ${artist.life_span.begin || '?'}-${artist.life_span.end || 'present'}` : ''
            const country = artist.country ? ` • ${artist.country}` : ''
            const type = artist.type ? ` • ${artist.type}` : ''

            artistItem.innerHTML = `
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${this.escapeHtml(artist.name)}${disambiguation}</h6>
                    <small>${this.escapeHtml(artist.id)}</small>
                </div>
                <p class="mb-1">
                    <small class="text-muted">${type}${country}${lifeSpan}</small>
                </p>
            `

            artistItem.addEventListener('click', (event) => {
                this.selectArtist(artist, event)
            })

            artistResults.appendChild(artistItem)
        })
    }

    selectArtist(artist, event = null) {
        this.selectedArtist = artist

        const selectedInfo = document.getElementById('selectedArtistInfo')
        const artistInfo = document.getElementById('artistInfo')
        const saveBtn = document.getElementById('saveArtistBtn')

        if (artistInfo) {
            const disambiguation = artist.disambiguation ? ` (${artist.disambiguation})` : ''
            const lifeSpan = artist.life_span ? `${artist.life_span.begin || '?'}-${artist.life_span.end || 'present'}` : ''
            const country = artist.country || 'Unknown'
            const type = artist.type || 'Unknown'

            artistInfo.innerHTML = `
                <h6>${this.escapeHtml(artist.name)}${disambiguation}</h6>
                <p><strong>MusicBrainz ID:</strong> ${this.escapeHtml(artist.id)}</p>
                <p><strong>Type:</strong> ${this.escapeHtml(type)}</p>
                <p><strong>Country:</strong> ${this.escapeHtml(country)}</p>
                ${lifeSpan ? `<p><strong>Active:</strong> ${this.escapeHtml(lifeSpan)}</p>` : ''}
            `
        }

        if (selectedInfo) {
            selectedInfo.style.display = 'block'
        }

        // Enable save button if library is also selected
        this.updateSaveButtonState()

        // Clear previous selections in search results
        const artistResults = document.getElementById('artistResults')
        if (artistResults && event) {
            const items = artistResults.querySelectorAll('.list-group-item')
            items.forEach(item => item.classList.remove('active'))
            event.currentTarget.classList.add('active')
        }
    }

    async saveArtist() {
        if (!this.selectedArtist) {
            this.showError('Please select an artist first')
            return
        }

        const librarySelect = document.getElementById('artistLibrary')
        const libraryId = librarySelect?.value

        if (!libraryId) {
            this.showError('Please select a library')
            return
        }

        const saveBtn = document.getElementById('saveArtistBtn')
        const originalText = saveBtn?.innerHTML

        // Show loading state
        if (saveBtn) {
            saveBtn.disabled = true
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...'
        }

        try {
            const response = await fetch('/artist/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    mbid: this.selectedArtist.id,
                    name: this.selectedArtist.name,
                    libraryId: libraryId
                })
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess('Artist added successfully!')
                this.resetModal()
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addArtistModal'))
                if (modal) {
                    modal.hide()
                }

                // Refresh artist list if we're on the artist index page
                if (this.hasContainerTarget) {
                    this.loadArtists()
                }
            } else {
                throw new Error(result.error || 'Failed to add artist')
            }
        } catch (error) {
            console.error('Error saving artist:', error)
            this.showError('Error saving artist: ' + error.message)
        } finally {
            // Reset save button
            if (saveBtn) {
                saveBtn.disabled = false
                saveBtn.innerHTML = originalText
            }
        }
    }

    // Library management
    async populateLibrarySelect() {
        const librarySelect = document.getElementById('artistLibrary')
        if (!librarySelect) return

        try {
            const response = await fetch('/library/list')
            const libraries = await response.json()

            librarySelect.innerHTML = '<option value="">Select a library...</option>'
            libraries.forEach(library => {
                const option = document.createElement('option')
                option.value = library.id
                option.textContent = library.name
                librarySelect.appendChild(option)
            })

            this.libraries = libraries
        } catch (error) {
            console.error('Error loading libraries:', error)
            this.showError('Error loading libraries')
        }
    }

    updateSaveButtonState() {
        const saveBtn = document.getElementById('saveArtistBtn')
        const librarySelect = document.getElementById('artistLibrary')
        
        if (saveBtn && librarySelect) {
            saveBtn.disabled = !(this.selectedArtist && librarySelect.value)
        }
    }

    resetModal() {
        this.selectedArtist = null
        
        const artistSearch = document.getElementById('artistSearch')
        const artistInfo = document.getElementById('artistInfo')
        const selectedInfo = document.getElementById('selectedArtistInfo')
        const artistResults = document.getElementById('artistResults')
        const resultsDiv = document.getElementById('searchResults')
        const librarySelect = document.getElementById('artistLibrary')

        if (artistSearch) artistSearch.value = ''
        if (artistInfo) artistInfo.innerHTML = ''
        if (selectedInfo) selectedInfo.style.display = 'none'
        if (artistResults) artistResults.innerHTML = ''
        if (resultsDiv) resultsDiv.style.display = 'none'
        if (librarySelect) librarySelect.value = ''

        this.updateSaveButtonState()
    }

    // Artist list management
    initialize() {
        this.setupInfiniteScroll()
        this.loadArtists()
    }

    setupInfiniteScroll() {
        if (!this.hasInfiniteScrollTriggerTarget) return

        this.infiniteScrollObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.isLoadingValue && this.hasMoreDataValue) {
                        this.loadMoreArtists()
                    }
                })
            },
            { threshold: 0.1 }
        )

        this.infiniteScrollObserver.observe(this.infiniteScrollTriggerTarget)
    }

    async loadArtists() {
        if (this.isLoadingValue) return

        this.isLoadingValue = true
        this.currentPageValue = 1
        this.hasMoreDataValue = true

        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = 'block'
        }

        try {
            await this.fetchArtists()
        } catch (error) {
            console.error('Error loading artists:', error)
            this.showError('Error loading artists')
        } finally {
            this.isLoadingValue = false
            if (this.hasLoadingIndicatorTarget) {
                this.loadingIndicatorTarget.style.display = 'none'
            }
        }
    }

    async loadMoreArtists() {
        if (this.isLoadingValue || !this.hasMoreDataValue) return

        this.isLoadingValue = true
        this.currentPageValue++

        try {
            await this.fetchArtists(true)
        } catch (error) {
            console.error('Error loading more artists:', error)
            this.currentPageValue-- // Revert page increment on error
        } finally {
            this.isLoadingValue = false
        }
    }

    async fetchArtists(append = false) {
        const params = new URLSearchParams({
            page: this.currentPageValue,
            limit: this.limitValue
        })

        // Add search query if exists
        if (this.lastSearchQueryValue) {
            params.append('q', this.lastSearchQueryValue)
        }

        // Add filters if they exist
        if (this.hasLibraryFilterTarget && this.libraryFilterTarget.value) {
            params.append('library', this.libraryFilterTarget.value)
        }

        if (this.hasAlbumFilterTarget && this.albumFilterTarget.value) {
            params.append('album', this.albumFilterTarget.value)
        }

        const response = await fetch(`/artist/list?${params}`)
        const data = await response.json()

        if (data.success) {
            if (append) {
                this.appendArtists(data.artists)
            } else {
                this.displayArtists(data.artists)
            }

            this.hasMoreDataValue = data.hasMore
            this.updateSearchResultsInfo(data.total, data.artists.length)
        } else {
            throw new Error(data.error || 'Failed to load artists')
        }
    }

    displayArtists(artists) {
        if (!this.hasContainerTarget) return

        if (artists.length === 0) {
            this.containerTarget.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        No artists found.
                    </div>
                </div>
            `
            return
        }

        const artistsHtml = artists.map(artist => this.createArtistCard(artist)).join('')
        this.containerTarget.innerHTML = artistsHtml
    }

    appendArtists(artists) {
        if (!this.hasContainerTarget) return

        const artistsHtml = artists.map(artist => this.createArtistCard(artist)).join('')
        this.containerTarget.insertAdjacentHTML('beforeend', artistsHtml)
    }

    createArtistCard(artist) {
        const stats = artist.stats || {}
        const lastScan = artist.lastScan ? new Date(artist.lastScan).toLocaleDateString() : 'Never'
        
        return `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-user me-2"></i>${this.escapeHtml(artist.name)}
                        </h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   ${artist.enabled ? 'checked' : ''} 
                                   data-action="change->artist#toggleArtist"
                                   data-artist-id="${artist.id}">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>MBID:</strong><br>
                            <code class="small">${this.escapeHtml(artist.mbid || 'N/A')}</code>
                        </div>
                        
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="h6 mb-0">${stats.albums || 0}</div>
                                <small class="text-muted">Albums</small>
                            </div>
                            <div class="col-4">
                                <div class="h6 mb-0">${stats.tracks || 0}</div>
                                <small class="text-muted">Tracks</small>
                            </div>
                            <div class="col-4">
                                <div class="h6 mb-0">${stats.downloaded || 0}</div>
                                <small class="text-muted">Downloaded</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Last Scan:</strong> ${lastScan}
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100" role="group">
                            <a href="/artist/${artist.id}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                            <button class="btn btn-outline-secondary btn-sm" 
                                    data-action="click->artist#scanArtist"
                                    data-artist-id="${artist.id}">
                                <i class="fas fa-sync me-1"></i>Scan
                            </button>
                            <button class="btn btn-outline-danger btn-sm" 
                                    data-action="click->artist#deleteArtist"
                                    data-artist-id="${artist.id}"
                                    data-confirm="Are you sure you want to delete this artist?">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `
    }

    // Search functionality
    search(event) {
        const query = event.target.value.trim()
        
        // Clear previous timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }

        // Set new timeout for debounced search
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query)
        }, 300)
    }

    async performSearch(query) {
        this.lastSearchQueryValue = query
        this.currentPageValue = 1
        this.hasMoreDataValue = true

        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = 'block'
        }

        try {
            await this.fetchArtists()
        } catch (error) {
            console.error('Error searching artists:', error)
            this.showError('Error searching artists')
        } finally {
            if (this.hasLoadingIndicatorTarget) {
                this.loadingIndicatorTarget.style.display = 'none'
            }
        }
    }

    // Filter functionality
    filter() {
        this.currentPageValue = 1
        this.hasMoreDataValue = true
        this.loadArtists()
    }

    // Artist actions
    async toggleArtist(event) {
        const artistId = event.currentTarget.dataset.artistId
        const enabled = event.currentTarget.checked

        try {
            const response = await fetch(`/artist/${artistId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ enabled })
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess(`Artist ${enabled ? 'enabled' : 'disabled'} successfully`)
            } else {
                // Revert the toggle on error
                event.currentTarget.checked = !enabled
                throw new Error(result.error || 'Failed to toggle artist')
            }
        } catch (error) {
            console.error('Error toggling artist:', error)
            this.showError('Error toggling artist: ' + error.message)
        }
    }

    async scanArtist(event) {
        const artistId = event.currentTarget.dataset.artistId
        const button = event.currentTarget
        const originalText = button.innerHTML

        // Show loading state
        button.disabled = true
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Scanning...'

        try {
            const response = await fetch(`/artist/${artistId}/scan`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess('Artist scan started successfully')
                // Refresh the artist data
                this.loadArtists()
            } else {
                throw new Error(result.error || 'Failed to start scan')
            }
        } catch (error) {
            console.error('Error scanning artist:', error)
            this.showError('Error scanning artist: ' + error.message)
        } finally {
            // Restore button state
            button.disabled = false
            button.innerHTML = originalText
        }
    }

    async deleteArtist(event) {
        const artistId = event.currentTarget.dataset.artistId
        
        if (!confirm('Are you sure you want to delete this artist? This action cannot be undone.')) {
            return
        }

        const button = event.currentTarget
        const originalText = button.innerHTML

        // Show loading state
        button.disabled = true
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...'

        try {
            const response = await fetch(`/artist/${artistId}/delete`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess('Artist deleted successfully')
                // Remove the artist card from the DOM
                const artistCard = button.closest('.col-md-6')
                if (artistCard) {
                    artistCard.remove()
                }
            } else {
                throw new Error(result.error || 'Failed to delete artist')
            }
        } catch (error) {
            console.error('Error deleting artist:', error)
            this.showError('Error deleting artist: ' + error.message)
        } finally {
            // Restore button state
            button.disabled = false
            button.innerHTML = originalText
        }
    }

    // Utility methods
    updateSearchResultsInfo(total, current) {
        if (this.hasSearchResultsInfoTarget) {
            this.searchResultsInfoTarget.style.display = 'block'
            this.searchResultsInfoTarget.innerHTML = `Showing ${current} of ${total} artists`
        }
    }

    showSuccess(message) {
        this.showAlert(message, 'success')
    }

    showError(message) {
        this.showAlert(message, 'danger')
    }

    showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `
        this.element.insertAdjacentHTML('afterbegin', alertHtml)

        // Auto-hide after 5 seconds
        setTimeout(() => {
            const alerts = this.element.querySelectorAll('.alert')
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s'
                alert.style.opacity = '0'
                setTimeout(() => alert.remove(), 500)
            })
        }, 5000)
    }

    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }
}
