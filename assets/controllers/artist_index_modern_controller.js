import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['searchInput', 'artistsContainer', 'searchResults', 'searchResultsText', 'artistResults', 'selectedArtistInfo', 'artistInfo', 'statusFilter', 'infiniteScrollTrigger', 'loadingIndicator', 'searchResultsInfo', 'endOfDataIndicator', 'albumFilter', 'filterStatus', 'filterStatusText']

    connect() {
        console.log('Artist Index Modern controller connected')

        // Ensure all required targets are available
        if (!this.hasSearchInputTarget || !this.hasArtistsContainerTarget) {
            console.error('Required targets missing for artist index controller')
            return
        }

        this.initializeFilters()
        this.restoreFiltersFromStorage()
        this.loadArtists()
        this.initializeModalHandlers()
    }

    initializeFilters() {
        console.log('initializeFilters - Starting...')

        // Check if filter targets exist before adding event listeners
        if (!this.hasStatusFilterTarget || !this.hasAlbumFilterTarget) {
            console.warn('Filter targets missing, skipping filter initialization')
            return
        }

        console.log('initializeFilters - All filter targets found, adding event listeners')

        // Add event listeners for filters
        this.statusFilterTarget.addEventListener('change', () => {
            console.log('Status filter changed, calling applyFilters')
            this.applyFilters()
        })
        this.albumFilterTarget.addEventListener('change', () => {
            console.log('Album filter changed, calling applyFilters')
            this.applyFilters()
        })

        // Add event listener for search input to search as user types
        this.searchInputTarget.addEventListener('input', () => {
            console.log('Search input changed, calling handleSearchInput')
            this.handleSearchInput()
        })

        // Add event listener for Enter key to search immediately
        this.searchInputTarget.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault()
                console.log('Enter key pressed in search input')
                // Clear any pending timeout and search immediately
                if (this.searchTimeout) {
                    clearTimeout(this.searchTimeout)
                }
                // Save filters before searching
                this.saveFiltersToStorage()
                this.searchArtists()
            }
        })

        console.log('initializeFilters - Event listeners added successfully')
    }

    saveFiltersToStorage() {
        const filters = {
            search: this.hasSearchInputTarget ? this.searchInputTarget.value : '',
            status: this.hasStatusFilterTarget ? this.statusFilterTarget.value : '',
            albums: this.hasAlbumFilterTarget ? this.albumFilterTarget.value : ''
        }
        localStorage.setItem('artist_filters', JSON.stringify(filters))

        // Update the filter status indicator (if available)
        try {
            this.updateFilterStatusIndicator()
        } catch (error) {
            console.warn('Could not update filter status indicator:', error)
        }
    }

    restoreFiltersFromStorage() {
        try {
            const savedFilters = localStorage.getItem('artist_filters')
            if (savedFilters) {
                const filters = JSON.parse(savedFilters)

                // Check if targets exist before restoring
                if (this.hasSearchInputTarget) {
                    if (filters.search) {
                        this.searchInputTarget.value = filters.search
                    }
                }
                if (this.hasStatusFilterTarget) {
                    if (filters.status) {
                        this.statusFilterTarget.value = filters.status
                    }
                }
                if (this.hasAlbumFilterTarget) {
                    if (filters.albums) {
                        this.albumFilterTarget.value = filters.albums
                    }
                }

                // If there are any active filters, we'll apply them after the initial load
                // The loadArtists method will be called after this, and then we'll apply filters

                // Update the filter status indicator
                try {
                    this.updateFilterStatusIndicator()
                } catch (error) {
                    console.warn('Could not update filter status indicator:', error)
                }
            }
        } catch (error) {
            console.error('Error restoring filters from storage:', error)
            // Clear corrupted storage
            localStorage.removeItem('artist_filters')
        }
    }

    clearFiltersFromStorage() {
        localStorage.removeItem('artist_filters')
    }





    updateFilterStatusIndicator() {
        // Check if the filter status targets exist before using them
        if (!this.hasFilterStatusTarget || !this.hasFilterStatusTextTarget) {
            return
        }

        const search = this.hasSearchInputTarget ? this.searchInputTarget.value.trim() : ''
        const status = this.hasStatusFilterTarget ? this.statusFilterTarget.value : ''
        const albums = this.hasAlbumFilterTarget ? this.albumFilterTarget.value : ''

        const hasActiveFilters = search || status || albums

        if (hasActiveFilters) {
            const filterDescriptions = []
            if (search) filterDescriptions.push(`Search: "${search}"`)
            if (status) filterDescriptions.push(`Status: ${status}`)
            if (albums) filterDescriptions.push(`Albums: ${albums}`)

            this.filterStatusTextTarget.textContent = `Active filters: ${filterDescriptions.join(', ')} - Filters are automatically saved`
            this.filterStatusTarget.style.display = 'block'
        } else {
            this.filterStatusTarget.style.display = 'none'
        }
    }



    initializeModalHandlers() {
        // Add event listeners for modal elements
        const searchBtn = document.getElementById('searchMusicBrainzBtn')
        const saveBtn = document.getElementById('saveArtistBtn')
        const artistSearch = document.getElementById('artistSearch')
        const modal = document.getElementById('addArtistModal')

        if (searchBtn) {
            searchBtn.addEventListener('click', () => this.searchMusicBrainz())
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveArtist())
        }

        if (artistSearch) {
            artistSearch.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault()
                    this.searchMusicBrainz()
                }
            })
        }

        if (modal) {
            modal.addEventListener('shown.bs.modal', () => this.onModalShown())
            modal.addEventListener('hidden.bs.modal', () => this.onModalHidden())
        }
    }



    loadArtists() {
        // Check if we have restored filters that should be applied
        const hasActiveFilters = (this.hasSearchInputTarget && this.searchInputTarget.value.trim()) ||
                               (this.hasStatusFilterTarget && this.statusFilterTarget.value) ||
                               (this.hasAlbumFilterTarget && this.albumFilterTarget.value)

        if (hasActiveFilters) {
            // Apply the restored filters instead of loading all artists
            this.applyFilters()
            return
        }

        this.artistsContainerTarget.innerHTML = `
            <div class="col-12 text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `

        fetch('/artist/paginated')
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data && response.data.items) {
                    this.displayArtists(response.data.items)
                } else {
                    this.displayArtists([])
                }
            })
            .catch(error => {
                console.error('Error loading artists:', error)
                this.artistsContainerTarget.innerHTML = `
                    <div class="col-12 text-center text-danger">
                        <p>Error loading artists</p>
                    </div>
                `
            })
    }

    displayArtists(artists) {
        if (artists.length === 0) {
            this.artistsContainerTarget.innerHTML = `
                <div class="col-12 text-center text-muted">
                    <p>No artists found</p>
                </div>
            `
            return
        }

        let html = ''
        artists.forEach(artist => {
            html += this.createArtistCard(artist)
        })
        this.artistsContainerTarget.innerHTML = html
    }

    createArtistCard(artist) {
        const statusBadge = artist.status === 'active'
            ? `<span class="badge bg-success">${window.translations?.['artist.js.status_active'] || 'Active'}</span>`
            : `<span class="badge bg-secondary">${window.translations?.['artist.js.status_ended'] || 'Ended'}</span>`

        // Get counts from the artist data
        const albumCount = artist.albumCount || 0
        const tracksCount = artist.tracksCount || 0
        const filesCount = artist.filesCount || 0

        // Create compact artist image HTML
        let artistImageHtml = ''
        if (artist.artistImageUrl && artist.hasArtistImage) {
            artistImageHtml = `
                <div class="text-center mb-2">
                    <img src="${artist.artistImageUrl}"
                         alt="${this.escapeHtml(artist.name)}"
                         class="img-fluid rounded"
                         style="max-width: 80px; max-height: 80px; object-fit: cover;">
                </div>
            `
        } else {
            // Fallback to icon if no image
            artistImageHtml = `
                <div class="text-center mb-2">
                    <div class="bg-light rounded d-flex align-items-center justify-content-center"
                         style="width: 80px; height: 80px; margin: 0 auto;">
                        <i class="fas fa-user fa-2x text-muted"></i>
                    </div>
                </div>
            `
        }

        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-user me-2"></i>${this.escapeHtml(artist.name)}
                        </h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   ${artist.monitored ? 'checked' : ''}
                                   data-action="change->artist-index-modern#toggleArtist"
                                   data-artist-id="${artist.id}">
                        </div>
                    </div>
                    <div class="card-body py-2">
                        ${artistImageHtml}

                        <div class="mb-2">
                            ${statusBadge}
                        </div>

                        <div class="row text-center mb-2">
                            <div class="col-4">
                                <div class="h6 mb-0">${albumCount}</div>
                                <small class="text-muted">${window.translations?.['artist.js.albums'] || 'Albums'}</small>
                            </div>
                            <div class="col-4">
                                <div class="h6 mb-0">${tracksCount}</div>
                                <small class="text-muted">${window.translations?.['artist.js.tracks'] || 'Tracks'}</small>
                            </div>
                            <div class="col-4">
                                <div class="h6 mb-0">${filesCount}</div>
                                <small class="text-muted">${window.translations?.['artist.js.files'] || 'Files'}</small>
                            </div>
                        </div>

                        ${artist.country ? `
                            <div class="mb-2 text-center">
                                <small class="text-muted">
                                    <i class="fas fa-globe me-1"></i>${this.escapeHtml(artist.country)}
                                </small>
                            </div>
                        ` : ''}
                    </div>
                    <div class="card-footer py-2">
                        <div class="btn-group w-100" role="group">
                            <a href="/artist/${artist.id}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>${window.translations?.['artist.js.view'] || 'View'}
                            </a>
                            <button class="btn btn-outline-danger btn-sm"
                                    data-action="click->artist-index-modern#deleteArtist"
                                    data-artist-id="${artist.id}">
                                <i class="fas fa-trash me-1"></i>${window.translations?.['artist.js.delete'] || 'Delete'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `
    }

    searchArtists() {
        const query = this.searchInputTarget.value.trim()
        console.log('searchArtists - Query:', query)

        if (!query) {
            console.log('searchArtists - Empty query, calling applyFilters')
            this.applyFilters()
            this.hideSearchResultsInfo()
            return
        }

        this.artistsContainerTarget.innerHTML = `
            <div class="col-12 text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `

        // Build URL with search query and current filters
        let url = `/artist/paginated?search=${encodeURIComponent(query)}`
        const status = this.hasStatusFilterTarget ? this.statusFilterTarget.value : ''
        const albumsFilter = this.hasAlbumFilterTarget ? this.albumFilterTarget.value : ''

        if (status) url += `&status=${status}`
        if (albumsFilter) url += `&albums=${albumsFilter}`

        console.log('searchArtists - URL:', url)

        fetch(url)
            .then(response => response.json())
            .then(response => {
                // Remove loading indicator
                this.searchInputTarget.classList.remove('searching')

                if (response.success && response.data && response.data.items) {
                    this.displayArtists(response.data.items)
                    this.showSearchResultsInfo(query, response.data.items.length)
                } else {
                    this.displayArtists([])
                    this.showSearchResultsInfo(query, 0)
                }
            })
            .catch(error => {
                // Remove loading indicator on error
                this.searchInputTarget.classList.remove('searching')

                console.error('Error searching artists:', error)
                this.artistsContainerTarget.innerHTML = `
                    <div class="col-12 text-center text-danger">
                        <p>Search error</p>
                    </div>
                `
            })
    }

    clearSearch() {
        if (this.hasSearchInputTarget) this.searchInputTarget.value = ''
        // Reset filter dropdowns to default values
        if (this.hasStatusFilterTarget) this.statusFilterTarget.value = ''
        if (this.hasAlbumFilterTarget) this.albumFilterTarget.value = ''

        // Clear filters from storage
        this.clearFiltersFromStorage()

        // Load all artists without filters
        this.loadArtists()
        this.hideSearchResultsInfo()
    }

    clearAllFilters() {
        if (this.hasSearchInputTarget) this.searchInputTarget.value = ''
        if (this.hasStatusFilterTarget) this.statusFilterTarget.value = ''
        if (this.hasAlbumFilterTarget) this.albumFilterTarget.value = ''

        // Clear filters from storage
        this.clearFiltersFromStorage()

        this.loadArtists()
        this.hideSearchResultsInfo()
    }

    searchMusicBrainz() {
        const query = document.getElementById('artistSearch').value.trim()
        if (!query) {
            const message = window.translations?.['artist.js.enter_search_query'] || 'Please enter a search query'
            this.showAlert(message, 'warning')
            return
        }

        this.searchResultsTarget.classList.remove('hidden')
        this.artistResultsTarget.innerHTML = `
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `

        fetch(`/artist/search-musicbrainz?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(response => {
                if (response.success && response.artists) {
                    this.displaySearchResults(response.artists)
                } else {
                    this.artistResultsTarget.innerHTML = `
                        <div class="alert alert-warning">No results found</div>
                    `
                }
            })
            .catch(error => {
                console.error('Search failed:', error)
                this.artistResultsTarget.innerHTML = `
                    <div class="alert alert-danger">Search error</div>
                `
            })
    }

    displaySearchResults(artists) {
        if (artists.length === 0) {
            this.artistResultsTarget.innerHTML = `
                <div class="alert alert-info">No results found</div>
            `
            return
        }

        let html = ''
        artists.forEach((artist, index) => {
            const country = artist.country ? ` - ${artist.country}` : ''
            const type = artist.type ? ` (${artist.type})` : ''
            const disambiguation = artist.disambiguation ? ` - ${artist.disambiguation}` : ''

            html += `
                <div class="list-group-item list-group-item-action" data-artist-index="${index}" data-artist='${JSON.stringify(artist)}'>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${artist.name}${country}${type}</h6>
                            <small class="text-muted">${artist.id}${disambiguation}</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </div>
            `
        })

        this.artistResultsTarget.innerHTML = html

        // Add click handlers
        console.log('Adding click handlers to', this.artistResultsTarget.querySelectorAll('.list-group-item').length, 'artist items')
        this.artistResultsTarget.querySelectorAll('.list-group-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                console.log('Artist item clicked, index:', index)
                this.selectArtist(index)
            })
        })
    }

    selectArtist(index) {
        console.log('selectArtist called with index:', index)

        const artistElement = this.artistResultsTarget.querySelectorAll('.list-group-item')[index]
        if (!artistElement) {
            console.error('Artist element not found for index:', index)
            return
        }

        const artist = JSON.parse(artistElement.dataset.artist)
        console.log('Selected artist data:', artist)

        // Update selection appearance
        this.artistResultsTarget.querySelectorAll('.list-group-item').forEach(item => item.classList.remove('active'))
        artistElement.classList.add('active')

        // Display artist information
        this.displaySelectedArtist(artist)

        // Show the selected artist info section if target exists
        if (this.hasSelectedArtistInfoTarget) {
            this.selectedArtistInfoTarget.classList.remove('hidden')
        } else {
            console.warn('selectedArtistInfo target not found')
        }

        // Enable save button
        const saveBtn = document.getElementById('saveArtistBtn')
        console.log('Save button element:', saveBtn)
        if (saveBtn) {
            saveBtn.disabled = false
            console.log('Save button enabled for artist:', artist.name)

            // Double-check the button state after a short delay
            setTimeout(() => {
                if (saveBtn.disabled) {
                    console.warn('Save button was re-disabled, forcing it enabled again')
                    saveBtn.disabled = false
                }
            }, 100)
        } else {
            console.warn('Save button not found')
        }
    }

    displaySelectedArtist(artist) {
        // Check if the artistInfo target exists before using it
        if (!this.hasArtistInfoTarget) {
            console.warn('artistInfo target not found, cannot display artist information')
            return
        }

        const country = artist.country ? `<strong>Country:</strong> ${artist.country}<br>` : ''
        const type = artist.type ? `<strong>Type:</strong> ${artist.type}<br>` : ''
        const disambiguation = artist.disambiguation ? `<strong>Description:</strong> ${artist.disambiguation}<br>` : ''

        const info = `
            <strong>Name:</strong> ${artist.name}<br>
            <strong>MBID:</strong> ${artist.id}<br>
            ${country}
            ${type}
            ${disambiguation}
        `

        this.artistInfoTarget.innerHTML = info
        console.log('Artist information displayed for:', artist.name)
    }

    applyFilters() {
        const search = this.hasSearchInputTarget ? this.searchInputTarget.value.trim() : ''
        const status = this.hasStatusFilterTarget ? this.statusFilterTarget.value : ''
        const albumsFilter = this.hasAlbumFilterTarget ? this.albumFilterTarget.value : ''

        // Save filters to storage
        this.saveFiltersToStorage()

        let url = '/artist/paginated?'
        if (search) url += `search=${encodeURIComponent(search)}&`
        if (status) url += `status=${status}&`
        if (albumsFilter) url += `albums=${albumsFilter}&`

        // Debug logging
        console.log('applyFilters - Search:', search, 'Status:', status, 'Albums:', albumsFilter)
        console.log('applyFilters - URL:', url)

        this.artistsContainerTarget.innerHTML = `
            <div class="col-12 text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `

        fetch(url)
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data && response.data.items) {
                    this.displayArtists(response.data.items)
                } else {
                    this.displayArtists([])
                }
            })
            .catch(error => {
                console.error('Error applying filters:', error)
                this.artistsContainerTarget.innerHTML = `
                    <div class="col-12 text-center text-danger">
                        <p>Error loading artists</p>
                    </div>
                `
            })
    }

    handleSearchInput() {
        const query = this.searchInputTarget.value.trim()

        console.log('handleSearchInput - Query:', query)

        // Save filters to storage
        this.saveFiltersToStorage()

        // Clear any existing timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }

        // If query is empty, load all artists with current filters
        if (!query) {
            console.log('handleSearchInput - Empty query, calling applyFilters')
            this.applyFilters()
            this.hideSearchResultsInfo()
            return
        }

        // Show subtle loading indicator in search input
        this.searchInputTarget.classList.add('searching')

        // Debounce the search request (wait 300ms after user stops typing)
        this.searchTimeout = setTimeout(() => {
            console.log('handleSearchInput - Timeout expired, calling searchArtists')
            this.searchArtists()
        }, 300)
    }

    showSearchResultsInfo(query, count) {
        if (this.hasSearchResultsInfoTarget && this.hasSearchResultsTextTarget) {
            this.searchResultsInfoTarget.classList.remove('hidden')
            this.searchResultsTextTarget.textContent = `Search results for "${query}": ${count} artist(s) found`
        }
    }

    hideSearchResultsInfo() {
        if (this.hasSearchResultsInfoTarget) {
            this.searchResultsInfoTarget.classList.add('hidden')
        }
    }

    // Artist actions
    async toggleArtist(event) {
        const artistId = event.currentTarget.dataset.artistId
        const enabled = event.currentTarget.checked

        try {
            const response = await fetch(`/artist/${artistId}/toggle-monitor`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ enabled })
            })

            const result = await response.json()

            if (result.success) {
                const message = enabled ?
                    (window.translations?.['artist.js.artist_enabled'] || 'Artist enabled successfully') :
                    (window.translations?.['artist.js.artist_disabled'] || 'Artist disabled successfully')
                this.showAlert(message, 'success')
            } else {
                // Revert the toggle on error
                event.currentTarget.checked = !enabled
                throw new Error(result.error || 'Failed to toggle artist')
            }
        } catch (error) {
            console.error('Error toggling artist:', error)
            const message = (window.translations?.['artist.js.error_toggling'] || 'Error toggling artist') + ': ' + error.message
            this.showAlert(message, 'danger')
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
                const message = window.translations?.['artist.js.artist_scan_started'] || 'Artist scan started successfully'
                this.showAlert(message, 'success')
                // Refresh the artist data
                this.loadArtists()
            } else {
                throw new Error(result.error || 'Failed to start scan')
            }
        } catch (error) {
            console.error('Error scanning artist:', error)
            const message = (window.translations?.['artist.js.error_scanning'] || 'Error scanning artist') + ': ' + error.message
            this.showAlert(message, 'danger')
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
                const message = window.translations?.['artist.js.artist_deleted'] || 'Artist deleted successfully'
                this.showAlert(message, 'success')
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
            const message = (window.translations?.['artist.js.error_deleting'] || 'Error deleting artist') + ': ' + error.message
            this.showAlert(message, 'danger')
        } finally {
            // Restore button state
            button.disabled = false
            button.innerHTML = originalText
        }
    }

    async saveArtist() {
        // Get the selected artist data
        const selectedArtistElement = this.artistResultsTarget.querySelector('.list-group-item.active')
        if (!selectedArtistElement) {
            this.showAlert('No artist selected', 'warning')
            return
        }

        const artist = JSON.parse(selectedArtistElement.dataset.artist)
        console.log('Saving artist:', artist)

        // Validate required fields
        if (!artist.name || !artist.id) {
            this.showAlert('Invalid artist data: name and MBID are required', 'warning')
            return
        }

        // Show loading state
        const saveBtn = document.getElementById('saveArtistBtn')
        const originalText = saveBtn.innerHTML
        saveBtn.disabled = true
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding...'

        try {
            const response = await fetch('/artist/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    mbid: artist.id,
                    name: artist.name,
                    country: artist.country || null,
                    type: artist.type || null,
                    disambiguation: artist.disambiguation || null
                })
            })

            const result = await response.json()

            if (result.success) {
                const message = result.message || window.translations?.['artist.js.artist_added'] || 'Artist added successfully'
                this.showAlert(message, 'success')

                // Log task information if available
                if (result.task_id) {
                    console.log(`Artist sync task created: ID ${result.task_id}, Type: ${result.task_type}, Status: ${result.task_status}`)
                }

                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addArtistModal'))
                if (modal) {
                    modal.hide()
                }

                // Reset modal state
                this.resetModalState()

                // Refresh the artist list
                this.loadArtists()
            } else {
                throw new Error(result.error || 'Failed to add artist')
            }
        } catch (error) {
            console.error('Error adding artist:', error)

            let errorMessage = 'Error adding artist'
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Network error: Unable to connect to server'
            } else if (error.message) {
                errorMessage += ': ' + error.message
            }

            const message = (window.translations?.['artist.js.error_adding_artist'] || errorMessage)
            this.showAlert(message, 'danger')
        } finally {
            // Restore button state
            saveBtn.disabled = false
            saveBtn.innerHTML = originalText
        }
    }

    onModalShown() {
        // Modal is shown
        console.log('Modal shown, checking all modal targets and save button state')

        // Check if all modal targets are available
        console.log('Modal targets check:')
        console.log('- searchResults:', this.hasSearchResultsTarget)
        console.log('- artistResults:', this.hasArtistResultsTarget)
        console.log('- selectedArtistInfo:', this.hasSelectedArtistInfoTarget)
        console.log('- artistInfo:', this.hasArtistInfoTarget)

        const saveBtn = document.getElementById('saveArtistBtn')
        if (saveBtn) {
            console.log('Save button found in modal shown, current disabled state:', saveBtn.disabled)
        } else {
            console.warn('Save button not found in modal shown')
        }
    }

    onModalHidden() {
        // Reset modal state
        this.selectedArtist = null

        // Hide search results if target exists
        if (this.hasSearchResultsTarget) {
            this.searchResultsTarget.classList.add('hidden')
        }

        // Hide selected artist info if target exists
        if (this.hasSelectedArtistInfoTarget) {
            this.selectedArtistInfoTarget.classList.add('hidden')
        }

        // Disable save button
        const saveBtn = document.getElementById('saveArtistBtn')
        if (saveBtn) {
            saveBtn.disabled = true
            console.log('Save button disabled on modal hidden')
        }
    }



    showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div')
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `

        // Insert at the top of the page
        this.element.insertBefore(alertDiv, this.element.firstChild)

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 5000)
    }

    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    // Debug method to test save button state
    debugSaveButton() {
        const saveBtn = document.getElementById('saveArtistBtn')
        if (saveBtn) {
            console.log('Save button found:', saveBtn)
            console.log('Current disabled state:', saveBtn.disabled)
            console.log('Button HTML:', saveBtn.outerHTML)

            // Try to enable it
            saveBtn.disabled = false
            console.log('After setting disabled=false:', saveBtn.disabled)

            // Check if there are any CSS classes that might be affecting it
            console.log('Button classes:', saveBtn.className)
            console.log('Button attributes:', Array.from(saveBtn.attributes).map(attr => `${attr.name}="${attr.value}"`))
        } else {
            console.warn('Save button not found')
        }
    }

    // Debug method to check all modal targets
    debugModalTargets() {
        console.log('=== Modal Targets Debug ===')
        console.log('Controller element:', this.element)
        console.log('Modal element:', document.getElementById('addArtistModal'))

        // Check each target manually
        const targets = [
            'searchResults', 'artistResults', 'selectedArtistInfo', 'artistInfo'
        ]

        targets.forEach(targetName => {
            const hasTarget = this[`has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`]
            console.log(`${targetName}: ${hasTarget}`)

            if (hasTarget) {
                const targetElement = this[`${targetName}Target`]
                console.log(`  - Element:`, targetElement)
                console.log(`  - HTML:`, targetElement?.outerHTML)
            }
        })

        // Check save button
        const saveBtn = document.getElementById('saveArtistBtn')
        console.log('Save button:', saveBtn)
        if (saveBtn) {
            console.log('  - Disabled:', saveBtn.disabled)
            console.log('  - HTML:', saveBtn.outerHTML)
        }
    }

    // Reset modal state after successful add
    resetModalState() {
        // Clear search input
        const artistSearch = document.getElementById('artistSearch')
        if (artistSearch) {
            artistSearch.value = ''
        }

        // Clear search results
        if (this.hasSearchResultsTarget) {
            this.searchResultsTarget.classList.add('hidden')
        }

        // Clear selected artist info
        if (this.hasSelectedArtistInfoTarget) {
            this.selectedArtistInfoTarget.classList.add('hidden')
        }

        // Clear artist results
        if (this.hasArtistResultsTarget) {
            this.artistResultsTarget.innerHTML = ''
        }

        // Clear artist info
        if (this.hasArtistInfoTarget) {
            this.artistInfoTarget.innerHTML = ''
        }

        // Disable save button
        const saveBtn = document.getElementById('saveArtistBtn')
        if (saveBtn) {
            saveBtn.disabled = true
        }

        console.log('Modal state reset successfully')
    }
}
