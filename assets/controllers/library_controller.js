import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['artistsContainer', 'loadingIndicator', 'endOfDataIndicator', 'infiniteScrollTrigger', 'artistSearch', 'artistResults', 'searchResults']

    connect() {
        // Initialize variables
        this.selectedArtist = null
        this.isInitialized = false
        this.currentPage = 1
        this.isLoading = false
        this.hasMoreData = true
        this.infiniteScrollObserver = null
        this.limit = 50
        this.libraryId = this.element.dataset.libraryId

        // Set up functionality
        this.setupEventListeners()
        this.initLibraryShow()
        this.initEditLibrary()
    }

    disconnect() {
        // Clean up infinite scroll observer
        if (this.infiniteScrollObserver) {
            this.infiniteScrollObserver.disconnect()
        }
    }

    setupEventListeners() {
        // Setup infinite scroll observer
        this.setupInfiniteScrollObserver()
    }

    setupInfiniteScrollObserver() {
        if (!this.hasInfiniteScrollTriggerTarget) {
            console.log('Infinite scroll trigger not found')
            return
        }

        this.infiniteScrollObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && this.hasMoreData && !this.isLoading) {
                    console.log('Loading next page:', this.currentPage + 1)
                    this.loadArtistsPage(this.currentPage + 1)
                }
            })
        }, {
            rootMargin: '100px'
        })

        this.infiniteScrollObserver.observe(this.infiniteScrollTriggerTarget)
        console.log('Infinite scroll observer setup complete')
    }

    initLibraryShow() {
        // Load initial artists
        this.loadArtists(true)
    }

    loadArtists(resetPagination = true) {
        console.log('loadArtists called with resetPagination:', resetPagination)

        if (resetPagination) {
            this.currentPage = 1
            this.hasMoreData = true
            if (this.hasArtistsContainerTarget) {
                this.artistsContainerTarget.innerHTML = ''
            }
            if (this.hasEndOfDataIndicatorTarget) {
                this.endOfDataIndicatorTarget.style.display = 'none'
            }

            // Disconnect existing observer
            if (this.infiniteScrollObserver) {
                this.infiniteScrollObserver.disconnect()
            }
        }

        this.loadArtistsPage(this.currentPage, resetPagination)
    }

    loadArtistsPage(page, isInitial = false) {
        if (this.isLoading || !this.hasMoreData) return

        console.log('Loading artists page:', page)
        this.isLoading = true

        if (!isInitial && this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = 'block'
        }

        console.log('Making AJAX request to /library/' + this.libraryId + '/artists with params:', {
            page: page,
            limit: this.limit
        })

        fetch(`/library/${this.libraryId}/artists?page=${page}&limit=${this.limit}`)
            .then(response => response.json())
            .then(response => {
                try {
                    console.log('Artists data loaded:', response)

                    // Handle response format
                    let data = []
                    let pagination = null

                    if (Array.isArray(response)) {
                        // Simple array format
                        data = response
                        pagination = {
                            hasNext: data.length >= this.limit,
                            page: page
                        }
                        console.log('Using array format, items:', data.length)
                    } else if (response && response.success && response.data) {
                        // Paginated format
                        data = response.data.items || []
                        pagination = response.data.pagination || {
                            hasNext: data.length >= this.limit,
                            page: page
                        }
                        console.log('Using paginated format, items:', data.length)
                    } else {
                        console.error('Unexpected response format:', response)
                        return
                    }

                    // Process artists data
                    if (data.length > 0) {
                        data.forEach(artist => {
                            const artistCard = this.createArtistCard(artist)
                            if (this.hasArtistsContainerTarget) {
                                this.artistsContainerTarget.insertAdjacentHTML('beforeend', artistCard)
                            }
                        })

                        // Update pagination state
                        this.currentPage = pagination.page
                        this.hasMoreData = pagination.hasNext

                        // Setup infinite scroll for next page
                        if (this.hasMoreData && this.infiniteScrollObserver) {
                            this.setupInfiniteScrollObserver()
                        }
                    } else {
                        console.log('No artists data received')
                        this.hasMoreData = false
                    }

                    // Show end of data indicator if no more data
                    if (!this.hasMoreData && this.hasEndOfDataIndicatorTarget) {
                        this.endOfDataIndicatorTarget.style.display = 'block'
                    }

                } catch (error) {
                    console.error('Error processing artists data:', error)
                    this.showAlert('Error processing artists data', 'danger')
                }
            })
            .catch(error => {
                console.error('Error loading artists:', error)
                this.showAlert('Error loading artists', 'danger')
            })
            .finally(() => {
                this.isLoading = false
                if (this.hasLoadingIndicatorTarget) {
                    this.loadingIndicatorTarget.style.display = 'none'
                }
            })
    }

    createArtistCard(artist) {
        try {
            // Safely get artist properties with defaults
            const artistId = artist.id || 0
            const artistName = (artist.name || 'Unknown Artist').replace(/'/g, '&#39;').replace(/"/g, '&quot;')
            const artistCountry = artist.country || ''
            const artistType = artist.type || ''
            const artistMonitored = artist.monitored === true
            const artistAlbumCount = artist.albumCount || 0
            const artistStatus = artist.status || 'Unknown'

            // Build country/type info
            const countryHtml = artistCountry ? '<i class="fas fa-flag me-1"></i>' + artistCountry : ''
            const typeHtml = artistType ? '<i class="fas fa-tag me-1"></i>' + artistType : ''
            const countryTypeHtml = [countryHtml, typeHtml].filter(Boolean).join(' ')

            // Build checkbox HTML
            const checkboxHtml = `<input class="form-check-input" type="checkbox" ${artistMonitored ? 'checked' : ''} data-action="change->library#toggleArtistMonitor" data-artist-id="${artistId}">`

            return `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 artist-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="card-title mb-0">${artistName}</h6>
                                <div class="form-check form-switch">${checkboxHtml}</div>
                            </div>
                            <p class="card-text text-muted small">${countryTypeHtml}</p>
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="h6 mb-0">${artistAlbumCount}</div>
                                    <small class="text-muted">Albums</small>
                                </div>
                                <div class="col-6">
                                    <div class="h6 mb-0">${artistStatus}</div>
                                    <small class="text-muted">Statut</small>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="/artist/${artistId}" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Voir les détails
                                </a>
                                <button class="btn btn-outline-secondary btn-sm" data-action="click->library#syncArtist" data-artist-id="${artistId}">
                                    <i class="fas fa-sync me-1"></i>Synchroniser
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `
        } catch (error) {
            console.log('Error in createArtistCard:', error)
            return '<div class="col-12"><div class="alert alert-warning">Error creating artist card for: ' + (artist.name || 'Unknown') + '</div></div>'
        }
    }

    scanLibrary() {
        const button = event.target.closest('button')
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Scanning...'
        button.disabled = true

        fetch(`/library/${this.libraryId}/scan`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Library scan started successfully', 'success')
                // Refresh data after scan
                setTimeout(() => {
                    this.loadArtists(true)
                    this.refreshLibraryStats()
                }, 2000)
            } else {
                this.showAlert(data.error || 'Error starting library scan', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error starting library scan', 'danger')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    toggleArtistMonitor(event) {
        const artistId = event.target.dataset.artistId
        const monitored = event.target.checked

        fetch(`/artist/${artistId}/toggle-monitor`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ monitored: monitored })
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                this.showAlert('Artist updated', 'success')
            } else {
                this.showAlert('Error updating artist', 'danger')
                // Revert checkbox state
                event.target.checked = !monitored
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error updating artist', 'danger')
            // Revert checkbox state
            event.target.checked = !monitored
        })
    }

    syncArtist(event) {
        const artistId = event.target.dataset.artistId
        const button = event.target
        const originalText = button.innerHTML
        button.innerHTML = '<span class="loading"></span> Synchronisation...'
        button.disabled = true

        fetch(`/artist/${artistId}/sync`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(response => {
            button.innerHTML = originalText
            button.disabled = false

            if (response.success) {
                this.showAlert('Synchronization completed', 'success')
                this.loadArtists()
                // Refresh statistics after sync
                setTimeout(() => this.refreshLibraryStats(), 1000)
            } else {
                this.showAlert('Error during synchronization', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            button.innerHTML = originalText
            button.disabled = false
            this.showAlert('Error during synchronization', 'danger')
        })
    }

    searchMusicBrainz() {
        console.log('Search MusicBrainz called')
        if (!this.hasArtistSearchTarget) return

        const query = this.artistSearchTarget.value.trim()
        console.log('Search query:', query)

        if (!query) {
            this.showAlert('Please enter an artist name', 'warning')
            return
        }

        // Show loading indicator
        if (this.hasArtistResultsTarget) {
            this.artistResultsTarget.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Search in progress...</div>'
        }
        if (this.hasSearchResultsTarget) {
            this.searchResultsTarget.style.display = 'block'
        }

        // Perform search
        fetch(`/artist/search-musicbrainz?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.artists) {
                    this.displaySearchResults(data.artists)
                } else {
                    this.showAlert(data.error || 'No artists found', 'warning')
                }
            })
            .catch(error => {
                console.error('Search error:', error)
                this.showAlert('Error during search', 'danger')
            })
    }

    displaySearchResults(artists) {
        if (!this.hasArtistResultsTarget) return

        if (artists.length === 0) {
            this.artistResultsTarget.innerHTML = '<div class="text-center text-muted">No artists found</div>'
            return
        }

        const resultsHtml = artists.map(artist => `
            <div class="artist-result-item p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${artist.name}</h6>
                        <small class="text-muted">${artist.country || ''} ${artist.type || ''}</small>
                    </div>
                    <button class="btn btn-primary btn-sm" data-action="click->library#addArtist" data-mbid="${artist.mbid}">
                        Add Artist
                    </button>
                </div>
            </div>
        `).join('')

        this.artistResultsTarget.innerHTML = resultsHtml
    }

    addArtist(event) {
        const mbid = event.target.dataset.mbid
        const button = event.target
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...'
        button.disabled = true

        fetch('/artist/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mbid: mbid, library: this.libraryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Artist added successfully', 'success')
                // Refresh artists list
                setTimeout(() => this.loadArtists(true), 1000)
            } else {
                this.showAlert(data.error || 'Error adding artist', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error adding artist', 'danger')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    refreshLibraryStats() {
        // Refresh library statistics
        fetch(`/library/${this.libraryId}/stats`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stats display if needed
                    console.log('Library stats refreshed:', data)
                }
            })
            .catch(error => {
                console.error('Error refreshing stats:', error)
            })
    }

    initEditLibrary() {
        // Set up modal event handlers
        const editModal = document.getElementById('editLibraryModal')
        if (editModal) {
            // Initialize folder tree when modal is shown
            editModal.addEventListener('shown.bs.modal', () => {
                this.initializeEditFolderTree()
            })

            // Clean up when modal is hidden
            editModal.addEventListener('hidden.bs.modal', () => {
                this.cleanupEditFolderTree()
            })
        }

        // Set up save button handler for edit modal
        const saveEditBtn = document.getElementById('saveEditLibraryBtn')
        if (saveEditBtn) {
            saveEditBtn.addEventListener('click', () => {
                this.saveLibraryEdit()
            })
        }
    }

    saveLibraryEdit() {
        // Get the selected path from the folder tree controller
        let selectedPath = this.element.dataset.libraryPath
        const editFolderTreeContainer = document.getElementById('editFolderTreeContainer')
        if (editFolderTreeContainer) {
            const folderTreeController = this.application.getControllerForElementAndIdentifier(
                editFolderTreeContainer.querySelector('[data-controller="folder-tree"]'),
                'folder-tree'
            )
            if (folderTreeController) {
                selectedPath = folderTreeController.getPath()
            }
        }

        const formData = {
            name: document.getElementById('editLibraryName').value,
            path: selectedPath,
            enabled: document.getElementById('editLibraryEnabled').checked,
            scanAutomatically: document.getElementById('editLibraryScanAuto').checked,
            scanInterval: parseInt(document.getElementById('editLibraryScanInterval').value) || 60
        }

        fetch(`/library/${this.libraryId}/edit`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('editLibraryModal'))
                if (modal) {
                    modal.hide()
                }
                this.showAlert('Library updated successfully', 'success')
                // Reload page to show updated data
                setTimeout(() => {
                    location.reload()
                }, 1000)
            } else {
                this.showAlert(data.error || 'Error updating library', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error updating library', 'danger')
        })
    }

    initializeEditFolderTree() {
        const editFolderTreeContainer = document.getElementById('editFolderTreeContainer')

        if (editFolderTreeContainer) {
            try {
                // Clear existing content
                editFolderTreeContainer.innerHTML = ''

                // Create folder tree structure
                const folderTreeHtml = `
                    <div class="border rounded p-3" data-controller="folder-tree" data-folder-tree-initial-path-value="${this.element.dataset.libraryPath}">
                        <!-- Breadcrumb -->
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-3" data-folder-tree-target="breadcrumb">
                                <!-- Breadcrumb will be populated here -->
                            </ol>
                        </nav>

                        <!-- Folder list -->
                        <div data-folder-tree-target="folderList" class="folder-tree-list">
                            <!-- Folders will be loaded here -->
                        </div>

                        <!-- Selected path display -->
                        <div class="mt-3">
                            <strong>Selected path:</strong>
                            <code data-folder-tree-target="selectedPath">No path selected</code>
                        </div>

                        <!-- Select current path button -->
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary btn-sm" data-action="click->folder-tree#selectCurrentPath">
                                <i class="fas fa-check me-1"></i>Select Current Path
                            </button>
                        </div>
                    </div>
                `

                editFolderTreeContainer.innerHTML = folderTreeHtml

                // Wait for Stimulus to connect the controller
                setTimeout(() => {
                    // Set the initial path
                    const folderTreeElement = editFolderTreeContainer.querySelector('[data-controller="folder-tree"]')
                    
                    const folderTreeController = this.application.getControllerForElementAndIdentifier(
                        folderTreeElement,
                        'folder-tree'
                    )
                    
                    if (folderTreeController && typeof folderTreeController.setPath === 'function') {
                        folderTreeController.setPath(this.element.dataset.libraryPath)
                    } else {
                        console.warn('Library: Folder tree controller not ready or missing setPath method')
                    }
                }, 200)
            } catch (error) {
                console.error('Error initializing edit folder tree:', error)
            }
        }
    }

    cleanupEditFolderTree() {
        const editFolderTreeContainer = document.getElementById('editFolderTreeContainer')
        if (editFolderTreeContainer) {
            const folderTreeController = this.application.getControllerForElementAndIdentifier(
                editFolderTreeContainer.querySelector('[data-controller="folder-tree"]'),
                'folder-tree'
            )
            if (folderTreeController && typeof folderTreeController.destroy === 'function') {
                folderTreeController.destroy()
            }
            editFolderTreeContainer.innerHTML = ''
        }
    }

    showAlert(message, type = 'info') {
        if (window.MusicarrApp && window.MusicarrApp.showAlert) {
            window.MusicarrApp.showAlert(message, type)
        } else {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `
            this.element.insertAdjacentHTML('afterbegin', alertHtml)
        }
    }

    deleteLibrary(event) {
        const libraryId = this.element.dataset.libraryId
        const libraryName = this.element.dataset.libraryName || 'this library'
        
        if (!confirm(`Are you sure you want to delete ${libraryName}? This action cannot be undone.`)) {
            return
        }

        const button = event.target
        const originalText = button.innerHTML
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...'
        button.disabled = true

        fetch(`/library/${libraryId}/delete`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(`${libraryName} deleted successfully`, 'success')
                // Redirect to library index after deletion
                setTimeout(() => {
                    window.location.href = '/library'
                }, 1500)
            } else {
                this.showAlert(data.error || 'Error deleting library', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error deleting library', 'danger')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }
}
