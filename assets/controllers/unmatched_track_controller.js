import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'searchResults', 'artistSuggestions', 'loadingSpinner',
        'albumSearchResults', 'albumSuggestions', 'albumLoadingSpinner',
        'albumSelectionSection', 'manualMatchingSection', 'trackSuggestions',
        'trackLoadingSpinner', 'selectedArtistInfo', 'selectedAlbumInfo',
        'selectedTrackInfo', 'artistSearch', 'albumSearch', 'trackSearch',
        // Missing targets that the template expects
        'loadedCount', 'emptyState', 'selectAllCheckbox', 'tableBody',
        'loadingIndicator', 'endOfDataIndicator', 'infiniteScrollTrigger'
    ]

    static values = {
        currentTrackId: Number,
        selectedArtist: Object,
        currentPage: { type: Number, default: 1 },
        isLoading: { type: Boolean, default: false },
        hasMoreData: { type: Boolean, default: true },
        limit: { type: Number, default: 50 }
    }

    connect() {
        console.log('Unmatched Track controller connected')
        this.selectedArtist = null
        this.selectedAlbum = null
        this.selectedTrack = null
        this.trackId = this.element.dataset.trackId
        
        // Initialize filters
        this.filters = {
            library: '',
            artist: '',
            title: '',
            album: ''
        }
        console.log('Initialized filters:', this.filters)
        
        // Load filters from local storage or data attributes
        this.loadFiltersFromStorage()
        
        // Initialize infinite scroll
        this.setupInfiniteScroll()
        
        // Load initial data if we're on the index page
        if (this.hasTableBodyTarget) {
            this.loadUnmatchedTracks()
        }
    }

    disconnect() {
        if (this.infiniteScrollObserver) {
            this.infiniteScrollObserver.disconnect()
        }
        
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }
    }

    // Load filters from local storage or data attributes
    loadFiltersFromStorage() {
        const storageKey = 'unmatched_tracks_filters'
        
        // Try to load from local storage first
        const storedFilters = localStorage.getItem(storageKey)
        if (storedFilters) {
            try {
                const filters = JSON.parse(storedFilters)
                this.filters = { ...this.filters, ...filters }
                console.log('Loaded filters from storage:', this.filters)
            } catch (e) {
                console.warn('Failed to parse stored filters:', e)
            }
        }
        
        // Override with data attributes if they exist (server-side values)
        if (this.element.dataset.unmatchedTrackFiltersLibraryValue) {
            this.filters.library = this.element.dataset.unmatchedTrackFiltersLibraryValue
        }
        if (this.element.dataset.unmatchedTrackFiltersArtistValue) {
            this.filters.artist = this.element.dataset.unmatchedTrackFiltersArtistValue
        }
        if (this.element.dataset.unmatchedTrackFiltersTitleValue) {
            this.filters.title = this.element.dataset.unmatchedTrackFiltersTitleValue
        }
        if (this.element.dataset.unmatchedTrackFiltersAlbumValue) {
            this.filters.album = this.element.dataset.unmatchedTrackFiltersAlbumValue
        }
        
        console.log('Final filters after loading:', this.filters)
        
        // Apply filters to form inputs
        this.applyFiltersToForm()
    }

    // Apply filters to form inputs
    applyFiltersToForm() {
        console.log('Applying filters to form:', this.filters)
        
        const libraryFilter = document.getElementById('library')
        const artistFilter = document.getElementById('artist')
        const titleFilter = document.getElementById('title')
        const albumFilter = document.getElementById('album')

        if (libraryFilter) {
            libraryFilter.value = this.filters.library
            console.log('Set library filter to:', this.filters.library)
        }
        if (artistFilter) {
            artistFilter.value = this.filters.artist
            console.log('Set artist filter to:', this.filters.artist)
        }
        if (titleFilter) {
            titleFilter.value = this.filters.title
            console.log('Set title filter to:', this.filters.title)
        }
        if (albumFilter) {
            albumFilter.value = this.filters.album
            console.log('Set album filter to:', this.filters.album)
        }
    }

    // Save filters to local storage
    saveFiltersToStorage() {
        const storageKey = 'unmatched_tracks_filters'
        console.log('Saving filters to storage:', this.filters)
        localStorage.setItem(storageKey, JSON.stringify(this.filters))
    }

    // Handle individual filter changes
    handleFilterChange(event) {
        const target = event.target
        const filterName = target.name
        
        if (filterName in this.filters) {
            this.filters[filterName] = target.value
            this.saveFiltersToStorage()
            
            // Debounced search for text inputs (not library select)
            if (filterName !== 'library') {
                this.debouncedSearch()
            }
        }
    }

    // Debounced search to avoid too many API calls
    debouncedSearch() {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }
        
        this.searchTimeout = setTimeout(() => {
            this.currentPageValue = 1
            this.hasMoreDataValue = true
            this.loadUnmatchedTracks()
        }, 500) // 500ms delay
    }

    // Handle library change (immediate search)
    handleLibraryChange(event) {
        const target = event.target
        this.filters.library = target.value
        this.saveFiltersToStorage()
        
        // Immediate search for library changes
        this.currentPageValue = 1
        this.hasMoreDataValue = true
        this.loadUnmatchedTracks()
    }

    // Setup infinite scroll
    setupInfiniteScroll() {
        if (!this.hasInfiniteScrollTriggerTarget) return

        this.infiniteScrollObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.isLoadingValue && this.hasMoreDataValue) {
                        this.loadMoreTracks()
                    }
                })
            },
            { threshold: 0.1 }
        )

        this.infiniteScrollObserver.observe(this.infiniteScrollTriggerTarget)
    }

    // Load unmatched tracks
    async loadUnmatchedTracks() {
        if (this.isLoadingValue) return

        this.isLoadingValue = true
        this.currentPageValue = 1
        this.hasMoreDataValue = true

        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.classList.remove('hidden')
        }

        try {
            await this.fetchUnmatchedTracks()
        } catch (error) {
            console.error('Error loading unmatched tracks:', error)
            this.showAlert('Error loading unmatched tracks', 'danger')
        } finally {
            this.isLoadingValue = false
            if (this.hasLoadingIndicatorTarget) {
                this.loadingIndicatorTarget.classList.add('hidden')
            }
        }
    }

    // Load more tracks for infinite scroll
    async loadMoreTracks() {
        if (this.isLoadingValue || !this.hasMoreDataValue) return

        this.isLoadingValue = true
        this.currentPageValue++

        try {
            await this.fetchUnmatchedTracks(true)
        } catch (error) {
            console.error('Error loading more tracks:', error)
            this.currentPageValue-- // Revert page increment on error
        } finally {
            this.isLoadingValue = false
        }
    }

    // Fetch tracks from API
    async fetchUnmatchedTracks(append = false) {
        const params = new URLSearchParams({
            page: this.currentPageValue,
            limit: this.limitValue
        })

        // Add filters from stored values
        if (this.filters.library) {
            params.append('library', this.filters.library)
        }
        if (this.filters.artist) {
            params.append('artist', this.filters.artist)
        }
        if (this.filters.title) {
            params.append('title', this.filters.title)
        }
        if (this.filters.album) {
            params.append('album', this.filters.album)
        }

        const response = await fetch(`/unmatched-tracks/paginated?${params}`)
        const data = await response.json()

        if (data.success) {
            const tracks = data.data.items || []
            const pagination = data.data.pagination || {}
            
            if (append) {
                this.appendTracks(tracks)
            } else {
                this.displayTracks(tracks)
            }

            this.hasMoreDataValue = pagination.hasNext || false
            this.updateLoadedCount(pagination.total || 0, tracks.length)
        } else {
            throw new Error(data.message || 'Failed to load tracks')
        }
    }

    // Display tracks
    displayTracks(tracks) {
        if (!this.hasTableBodyTarget) return

        if (tracks.length === 0) {
            this.showEmptyState()
            return
        }

        this.hideEmptyState()
        const tracksHtml = tracks.map(track => this.createTrackRow(track)).join('')
        this.tableBodyTarget.innerHTML = tracksHtml
    }

    // Append tracks for infinite scroll
    appendTracks(tracks) {
        if (!this.hasTableBodyTarget) return

        const tracksHtml = tracks.map(track => this.createTrackRow(track)).join('')
        this.tableBodyTarget.insertAdjacentHTML('beforeend', tracksHtml)
    }

    // Create track table row
    createTrackRow(track) {
        const fileSize = track.fileSize ? this.formatFileSize(track.fileSize) : 'Unknown'
        const duration = track.duration ? this.formatDuration(track.duration) : 'Unknown'
        const year = track.year || 'Unknown'
        const trackNumber = track.trackNumber || '?'
        const libraryName = track.library && track.library.name ? track.library.name : 'Unknown'

        return `
            <tr data-track-id="${track.id}">
                <td>
                    <input type="checkbox" class="track-checkbox" data-action="change->unmatched-track#toggleTrackSelection" data-track-id="${track.id}">
                </td>
                <td>
                    <small class="text-muted">${this.escapeHtml(track.relativePath || '')}</small>
                </td>
                <td>${trackNumber}</td>
                <td>${this.escapeHtml(track.artist || 'Unknown Artist')}</td>
                <td>${this.escapeHtml(track.album || 'Unknown Album')}</td>
                <td>${this.escapeHtml(track.title || 'Unknown Title')}</td>
                <td>${year}</td>
                <td>${duration}</td>
                <td>${this.escapeHtml(libraryName)}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" 
                                data-action="click->unmatched-track#associateTrack"
                                data-track-id="${track.id}"
                                data-artist-name="${this.escapeHtml(track.artist || '')}"
                                data-track-name="${this.escapeHtml(track.title || '')}">
                            <i class="fas fa-link me-1"></i>Associate
                        </button>
                        <button class="btn btn-outline-danger" 
                                data-action="click->unmatched-track#deleteTrack"
                                data-track-id="${track.id}"
                                data-track-name="${this.escapeHtml(track.title || '')}">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </div>
                </td>
            </tr>
        `
    }

    // Show empty state
    showEmptyState() {
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.remove('hidden')
        }
        if (this.hasTableBodyTarget) {
            this.tableBodyTarget.innerHTML = ''
        }
    }

    // Hide empty state
    hideEmptyState() {
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.add('hidden')
        }
    }

    // Update loaded count
    updateLoadedCount(total, current) {
        if (this.hasLoadedCountTarget) {
            this.loadedCountTarget.textContent = current
        }
    }

    // Handle filter form submission
    handleFilterSubmit(event) {
        event.preventDefault()
        console.log('Handling filter submit, current filters:', this.filters)
        
        // Update filters from form inputs
        const libraryFilter = document.getElementById('library')
        const artistFilter = document.getElementById('artist')
        const titleFilter = document.getElementById('title')
        const albumFilter = document.getElementById('album')

        this.filters.library = libraryFilter ? libraryFilter.value : ''
        this.filters.artist = artistFilter ? artistFilter.value : ''
        this.filters.title = titleFilter ? titleFilter.value : ''
        this.filters.album = albumFilter ? albumFilter.value : ''

        console.log('Updated filters:', this.filters)

        // Save filters to local storage
        this.saveFiltersToStorage()
        
        // Reset pagination and load tracks
        this.currentPageValue = 1
        this.hasMoreDataValue = true
        this.loadUnmatchedTracks()
    }

    // Clear filters
    clearFilters() {
        // Clear stored filters
        this.filters = {
            library: '',
            artist: '',
            title: '',
            album: ''
        }
        
        // Save to storage
        this.saveFiltersToStorage()
        
        // Clear form inputs
        const libraryFilter = document.getElementById('library')
        const artistFilter = document.getElementById('artist')
        const titleFilter = document.getElementById('title')
        const albumFilter = document.getElementById('album')

        if (libraryFilter) libraryFilter.value = ''
        if (artistFilter) artistFilter.value = ''
        if (titleFilter) titleFilter.value = ''
        if (albumFilter) albumFilter.value = ''

        this.currentPageValue = 1
        this.hasMoreDataValue = true
        this.loadUnmatchedTracks()
    }

    // Select all tracks
    selectAll() {
        const checkboxes = this.element.querySelectorAll('.track-checkbox')
        const selectAllCheckbox = this.selectAllCheckboxTarget
        
        if (selectAllCheckbox.checked) {
            checkboxes.forEach(checkbox => checkbox.checked = true)
            this.showBulkDeleteButton()
        } else {
            checkboxes.forEach(checkbox => checkbox.checked = false)
            this.hideBulkDeleteButton()
        }
    }

    // Toggle all tasks (select all checkbox)
    toggleAllTasks() {
        const checkboxes = this.element.querySelectorAll('.track-checkbox')
        const selectAllCheckbox = this.selectAllCheckboxTarget
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked
        })

        if (selectAllCheckbox.checked) {
            this.showBulkDeleteButton()
        } else {
            this.hideBulkDeleteButton()
        }
    }

    // Toggle individual track selection
    toggleTrackSelection() {
        const checkboxes = this.element.querySelectorAll('.track-checkbox')
        const selectAllCheckbox = this.selectAllCheckboxTarget
        const bulkDeleteBtn = document.getElementById('bulkDelete')

        const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked)
        const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked)

        selectAllCheckbox.checked = allChecked
        selectAllCheckbox.indeterminate = anyChecked && !allChecked

        if (anyChecked) {
            this.showBulkDeleteButton()
        } else {
            this.hideBulkDeleteButton()
        }
    }

    // Show bulk action buttons
    showBulkActionButtons() {
        const bulkDeleteBtn = document.getElementById('bulkDelete')
        const bulkAutoAssociateBtn = document.getElementById('bulkAutoAssociate')
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.classList.remove('hidden')
        }
        if (bulkAutoAssociateBtn) {
            bulkAutoAssociateBtn.classList.remove('hidden')
        }
    }

    // Hide bulk action buttons
    hideBulkActionButtons() {
        const bulkDeleteBtn = document.getElementById('bulkDelete')
        const bulkAutoAssociateBtn = document.getElementById('bulkAutoAssociate')
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.classList.add('hidden')
        }
        if (bulkAutoAssociateBtn) {
            bulkAutoAssociateBtn.classList.add('hidden')
        }
    }

    // Show bulk delete button (for backward compatibility)
    showBulkDeleteButton() {
        this.showBulkActionButtons()
    }

    // Hide bulk delete button (for backward compatibility)
    hideBulkDeleteButton() {
        this.hideBulkActionButtons()
    }

    // Bulk delete selected tracks
    async bulkDelete() {
        const selectedCheckboxes = this.element.querySelectorAll('.track-checkbox:checked')
        
        if (selectedCheckboxes.length === 0) {
            this.showAlert('Please select tracks to delete', 'warning')
            return
        }

        if (!confirm(`Are you sure you want to delete ${selectedCheckboxes.length} selected tracks? This action cannot be undone.`)) {
            return
        }

        const trackIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.dataset.trackId)
        
        try {
            const response = await fetch('/unmatched-tracks/bulk-delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ trackIds })
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert(`${trackIds.length} tracks deleted successfully`, 'success')
                this.loadUnmatchedTracks()
            } else {
                this.showAlert(result.error || 'Error deleting tracks', 'danger')
            }
        } catch (error) {
            console.error('Error bulk deleting tracks:', error)
            this.showAlert('Error deleting tracks', 'danger')
        }
    }

    // Bulk auto-associate selected tracks
    async bulkAutoAssociate() {
        const selectedCheckboxes = this.element.querySelectorAll('.track-checkbox:checked')
        
        if (selectedCheckboxes.length === 0) {
            this.showAlert('Please select tracks to auto-associate', 'warning')
            return
        }

        if (!confirm(`Are you sure you want to auto-associate ${selectedCheckboxes.length} selected tracks? This will create background tasks for each track.`)) {
            return
        }

        const trackIds = Array.from(selectedCheckboxes).map(checkbox => parseInt(checkbox.dataset.trackId))
        const button = document.getElementById('bulkAutoAssociate')
        const originalText = button.innerHTML

        button.disabled = true
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating tasks...'

        try {
            const response = await fetch('/unmatched-tracks/auto-associate-selected', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ trackIds })
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert(`${result.created_tasks} auto-association tasks created successfully`, 'success')
                // Clear selection and reload tracks after a delay
                this.clearSelection()
                setTimeout(() => this.loadUnmatchedTracks(), 2000)
            } else {
                this.showAlert(result.error || 'Error creating auto-association tasks', 'danger')
            }
        } catch (error) {
            console.error('Error creating bulk auto-association tasks:', error)
            this.showAlert('Error creating auto-association tasks', 'danger')
        } finally {
            button.disabled = false
            button.innerHTML = originalText
        }
    }

    // Clear selection
    clearSelection() {
        const checkboxes = this.element.querySelectorAll('.track-checkbox:checked')
        const selectAllCheckbox = this.selectAllCheckboxTarget
        
        checkboxes.forEach(checkbox => checkbox.checked = false)
        selectAllCheckbox.checked = false
        selectAllCheckbox.indeterminate = false
        this.hideBulkActionButtons()
    }

    // Start auto association
    async startAutoAssociation() {
        const button = document.getElementById('autoAssociateBtn')
        const originalText = button.innerHTML

        button.disabled = true
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Auto-associating...'

        try {
            const response = await fetch('/unmatched-tracks/auto-associate', {
                method: 'POST'
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert('Auto-association started successfully', 'success')
                // Reload tracks after a delay
                setTimeout(() => this.loadUnmatchedTracks(), 2000)
            } else {
                this.showAlert(result.error || 'Error starting auto-association', 'danger')
            }
        } catch (error) {
            console.error('Error starting auto-association:', error)
            this.showAlert('Error starting auto-association', 'danger')
        } finally {
            button.disabled = false
            button.innerHTML = originalText
        }
    }

    // Track actions
    associateTrack(event) {
        const trackId = event.currentTarget.dataset.trackId
        const artistName = event.currentTarget.dataset.artistName
        const trackName = event.currentTarget.dataset.trackName
        
        this.currentTrackIdValue = parseInt(trackId)
        this.showAssociateModal(trackId, artistName, trackName)
    }

    showAssociateModal(trackId, artistName, trackName) {
        // Create and show association modal
        const modalHtml = `
            <div class="modal fade" id="associateModal" tabindex="-1">
                <div class="modal-dialog modal-full">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Associate Track</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Track:</strong> ${this.escapeHtml(trackName)}</p>
                            <p><strong>Artist:</strong> ${this.escapeHtml(artistName)}</p>
                            
                            <div class="mb-3">
                                <label for="artistSearch" class="form-label">Search for Artist</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="artistSearch" 
                                           placeholder="Enter artist name" value="${this.escapeHtml(artistName)}">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            data-action="click->unmatched-track#searchMusicBrainz">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            
                            <div id="searchResults" class="mb-3" style="display: none;">
                                <h6>Search Results:</h6>
                                <div id="artistResults" class="list-group">
                                    <!-- Results will be populated here -->
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmAssociation" disabled
                                    data-action="click->unmatched-track#confirmAssociation">
                                Associate Track
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `

        // Remove old modal if exists
        const oldModal = document.getElementById('associateModal')
        if (oldModal) {
            oldModal.remove()
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHtml)

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('associateModal'))
        modal.show()
    }

    async searchMusicBrainz() {
        const searchInput = document.getElementById('artistSearch')
        const query = searchInput.value.trim()
        
        if (!query) {
            this.showAlert('Please enter an artist name to search', 'warning')
            return
        }

        const resultsContainer = document.getElementById('searchResults')
        const artistResults = document.getElementById('artistResults')
        
        resultsContainer.style.display = 'block'
        artistResults.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>'

        try {
            const response = await fetch(`/musicbrainz/search-artist?q=${encodeURIComponent(query)}`)
            const data = await response.json()

            if (data.success && data.artists.length > 0) {
                this.displayArtistResults(data.artists)
            } else {
                artistResults.innerHTML = '<div class="text-muted">No artists found</div>'
            }
        } catch (error) {
            console.error('Error searching MusicBrainz:', error)
            artistResults.innerHTML = '<div class="text-danger">Error searching for artists</div>'
        }
    }

    displayArtistResults(artists) {
        const artistResults = document.getElementById('artistResults')
        
        const resultsHtml = artists.map(artist => `
            <button type="button" class="list-group-item list-group-item-action" 
                    data-action="click->unmatched-track#selectArtist"
                    data-artist-id="${artist.id}"
                    data-artist-name="${this.escapeHtml(artist.name)}"
                    data-artist-country="${this.escapeHtml(artist.country || '')}"
                    data-artist-type="${this.escapeHtml(artist.type || '')}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(artist.name)}</strong>
                        <br>
                        <small class="text-muted">
                            ${this.escapeHtml(artist.type || 'Unknown type')} • 
                            ${this.escapeHtml(artist.country || 'Unknown country')}
                        </small>
                    </div>
                    <span class="badge bg-primary">Select</span>
                </div>
            </button>
        `).join('')

        artistResults.innerHTML = resultsHtml
    }

    selectArtist(event) {
        const button = event.currentTarget
        
        // Remove previous selections
        document.querySelectorAll('#artistResults .list-group-item').forEach(item => {
            item.classList.remove('active')
        })
        
        // Mark current selection
        button.classList.add('active')
        
        // Store selected artist
        this.selectedArtistValue = {
            id: button.dataset.artistId,
            name: button.dataset.artistName,
            country: button.dataset.artistCountry,
            type: button.dataset.artistType
        }
        
        // Enable confirmation button
        const confirmButton = document.getElementById('confirmAssociation')
        if (confirmButton) {
            confirmButton.disabled = false
        }
    }

    async confirmAssociation() {
        if (!this.selectedArtistValue || !this.currentTrackIdValue) {
            this.showAlert('Please select an artist first', 'warning')
            return
        }

        try {
            const response = await fetch('/unmatched-tracks/associate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    trackId: this.currentTrackIdValue,
                    artistName: this.selectedArtistValue.name,
                    mbid: this.selectedArtistValue.id
                })
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert('Track associated successfully', 'success')
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('associateModal'))
                if (modal) {
                    modal.hide()
                }

                // Reload tracks
                await this.loadUnmatchedTracks()
            } else {
                this.showAlert(result.error || 'Error associating track', 'danger')
            }
        } catch (error) {
            console.error('Error associating track:', error)
            this.showAlert('Error associating track', 'danger')
        }
    }

    async deleteTrack(event) {
        const trackId = event.currentTarget.dataset.trackId
        const trackName = event.currentTarget.dataset.trackName

        if (!confirm(`Are you sure you want to delete "${trackName}"? This action cannot be undone.`)) {
            return
        }

        try {
            const response = await fetch(`/unmatched-tracks/${trackId}/delete`, {
                method: 'DELETE'
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert('Track deleted successfully', 'success')
                await this.loadUnmatchedTracks()
            } else {
                this.showAlert(result.error || 'Error deleting track', 'danger')
            }
        } catch (error) {
            console.error('Error deleting track:', error)
            this.showAlert('Error deleting track', 'danger')
        }
    }

    // Original methods for individual track view
    deleteTrack() {
        if (confirm('Are you sure you want to delete this unmatched track?')) {
            fetch(`/unmatched-track/${this.trackId}/delete`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showAlert('Track deleted successfully', 'success')
                    // Redirect to unmatched tracks list
                    setTimeout(() => {
                        window.location.href = '/unmatched-track'
                    }, 1000)
                } else {
                    this.showAlert('Error deleting track: ' + data.error, 'danger')
                }
            })
            .catch(error => {
                console.error('Error:', error)
                this.showAlert('Error deleting track', 'danger')
            })
        }
    }

    searchArtists() {
        if (!this.hasArtistSearchTarget) return

        const query = this.artistSearchTarget.value.trim()
        if (!query) {
            this.showAlert('Please enter an artist name', 'warning')
            return
        }

        this.showLoadingSpinner()
        this.hideSearchResults()

        fetch(`/artist/search?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                this.hideLoadingSpinner()
                if (data.success && data.artists) {
                    this.displayArtistResults(data.artists)
                } else {
                    this.showAlert(data.error || 'No artists found', 'warning')
                }
            })
            .catch(error => {
                console.error('Search error:', error)
                this.hideLoadingSpinner()
                this.showAlert('Error during search', 'danger')
            })
    }

    searchAlbums() {
        if (!this.hasAlbumSearchTarget) return

        const query = this.albumSearchTarget.value.trim()
        if (!query) {
            this.showAlert('Please enter an album name', 'warning')
            return
        }

        if (!this.selectedArtist) {
            this.showAlert('Please select an artist first', 'warning')
            return
        }

        this.showAlbumLoadingSpinner()
        this.hideAlbumSearchResults()

        fetch(`/album/search?query=${encodeURIComponent(query)}&artist=${encodeURIComponent(this.selectedArtist.name)}`)
            .then(response => response.json())
            .then(data => {
                this.hideAlbumLoadingSpinner()
                if (data.success && data.albums) {
                    this.displayAlbumResults(data.albums)
                } else {
                    this.showAlert(data.error || 'No albums found', 'warning')
                }
            })
            .catch(error => {
                console.error('Search error:', error)
                this.hideAlbumLoadingSpinner()
                this.showAlert('Error during search', 'danger')
            })
    }

    searchManualArtists() {
        if (!this.hasArtistSearchTarget) return

        const query = this.artistSearchTarget.value.trim()
        if (!query) {
            this.showAlert('Please enter an artist name', 'warning')
            return
        }

        this.showTrackLoadingSpinner()
        this.hideTrackSuggestions()

        fetch(`/artist/search?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                this.hideTrackLoadingSpinner()
                if (data.success && data.artists) {
                    this.displayManualArtistResults(data.artists)
                } else {
                    this.showAlert(data.error || 'No artists found', 'warning')
                }
            })
            .catch(error => {
                console.error('Search error:', error)
                this.hideTrackLoadingSpinner()
                this.showAlert('Error during search', 'danger')
            })
    }

    searchManualAlbums() {
        if (!this.hasAlbumSearchTarget) return

        const query = this.albumSearchTarget.value.trim()
        if (!query) {
            this.showAlert('Please enter an album name', 'warning')
            return
        }

        if (!this.selectedArtist) {
            this.showAlert('Please select an artist first', 'warning')
            return
        }

        this.showTrackLoadingSpinner()
        this.hideTrackSuggestions()

        fetch(`/album/search?query=${encodeURIComponent(query)}&artist=${encodeURIComponent(this.selectedArtist.name)}`)
            .then(response => response.json())
            .then(data => {
                this.hideTrackLoadingSpinner()
                if (data.success && data.albums) {
                    this.displayManualAlbumResults(data.albums)
                } else {
                    this.showAlert(data.error || 'No albums found', 'warning')
                }
            })
            .catch(error => {
                console.error('Search error:', error)
                this.hideTrackLoadingSpinner()
                this.showAlert('Error during search', 'danger')
            })
    }

    searchManualTracks() {
        if (!this.hasTrackSearchTarget) return

        const query = this.trackSearchTarget.value.trim()
        if (!query) {
            this.showAlert('Please enter a track name', 'warning')
            return
        }

        if (!this.selectedArtist || !this.selectedAlbum) {
            this.showAlert('Please select an artist and album first', 'warning')
            return
        }

        this.showTrackLoadingSpinner()
        this.hideTrackSuggestions()

        fetch(`/track/search?query=${encodeURIComponent(query)}&artist=${encodeURIComponent(this.selectedArtist.name)}&album=${encodeURIComponent(this.selectedAlbum.title)}`)
            .then(response => response.json())
            .then(data => {
                this.hideTrackLoadingSpinner()
                if (data.success && data.tracks) {
                    this.displayManualTrackResults(data.tracks)
                } else {
                    this.showAlert(data.error || 'No tracks found', 'warning')
                }
            })
            .catch(error => {
                console.error('Search error:', error)
                this.hideTrackLoadingSpinner()
                this.showAlert('Error during search', 'danger')
            })
    }

    // Display methods
    displayArtistResults(artists) {
        if (!this.hasArtistSuggestionsTarget) return

        const resultsHtml = artists.map(artist => `
            <div class="list-group-item list-group-item-action" 
                 data-action="click->unmatched-track#selectArtistFromResults"
                 data-artist-id="${artist.id}"
                 data-artist-name="${this.escapeHtml(artist.name)}"
                 data-artist-mbid="${artist.mbid || ''}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(artist.name)}</strong>
                        ${artist.mbid ? `<br><small class="text-muted">MBID: ${artist.mbid}</small>` : ''}
                    </div>
                    <span class="badge bg-primary">Select</span>
                </div>
            </div>
        `).join('')

        this.artistSuggestionsTarget.innerHTML = resultsHtml
        this.showSearchResults()
    }

    displayAlbumResults(albums) {
        if (!this.hasAlbumSuggestionsTarget) return

        const resultsHtml = albums.map(album => `
            <div class="list-group-item list-group-item-action" 
                 data-action="click->unmatched-track#selectAlbumFromResults"
                 data-album-id="${album.id}"
                 data-album-title="${this.escapeHtml(album.title)}"
                 data-album-year="${album.year || ''}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(album.title)}</strong>
                        ${album.year ? `<br><small class="text-muted">Year: ${album.year}</small>` : ''}
                    </div>
                    <span class="badge bg-primary">Select</span>
                </div>
            </div>
        `).join('')

        this.albumSuggestionsTarget.innerHTML = resultsHtml
        this.showAlbumSearchResults()
    }

    displayManualArtistResults(artists) {
        if (!this.hasTrackSuggestionsTarget) return

        const resultsHtml = artists.map(artist => `
            <div class="list-group-item list-group-item-action" 
                 data-action="click->unmatched-track#selectManualArtist"
                 data-artist-id="${artist.id}"
                 data-artist-name="${this.escapeHtml(artist.name)}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(artist.name)}</strong>
                    </div>
                    <span class="badge bg-primary">Select</span>
                </div>
            </div>
        `).join('')

        this.trackSuggestionsTarget.innerHTML = resultsHtml
        this.showTrackSuggestions()
    }

    displayManualAlbumResults(albums) {
        if (!this.hasTrackSuggestionsTarget) return

        const resultsHtml = albums.map(album => `
            <div class="list-group-item list-group-item-action" 
                 data-action="click->unmatched-track#selectManualAlbum"
                 data-album-id="${album.id}"
                 data-album-title="${this.escapeHtml(album.title)}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(album.title)}</strong>
                    </div>
                    <span class="badge bg-primary">Select</span>
                </div>
            </div>
        `).join('')

        this.trackSuggestionsTarget.innerHTML = resultsHtml
        this.showTrackSuggestions()
    }

    displayManualTrackResults(tracks) {
        if (!this.hasTrackSuggestionsTarget) return

        const resultsHtml = tracks.map(track => `
            <div class="list-group-item list-group-item-action" 
                 data-action="click->unmatched-track#selectManualTrack"
                 data-track-id="${track.id}"
                 data-track-title="${this.escapeHtml(track.title)}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(track.title)}</strong>
                    </div>
                    <span class="badge bg-primary">Select</span>
                </div>
            </div>
        `).join('')

        this.trackSuggestionsTarget.innerHTML = resultsHtml
        this.showTrackSuggestions()
    }

    // Selection methods
    selectArtistFromResults(event) {
        const artistId = event.currentTarget.dataset.artistId
        const artistName = event.currentTarget.dataset.artistName
        const artistMbid = event.currentTarget.dataset.artistMbid

        this.selectedArtist = {
            id: artistId,
            name: artistName,
            mbid: artistMbid
        }

        this.updateSelectedArtistInfo()
        this.hideSearchResults()
        this.showAlbumSelectionSection()
    }

    selectAlbumFromResults(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle
        const albumYear = event.currentTarget.dataset.albumYear

        this.selectedAlbum = {
            id: albumId,
            title: albumTitle,
            year: albumYear
        }

        this.updateSelectedAlbumInfo()
        this.hideAlbumSearchResults()
        this.showManualMatchingSection()
    }

    selectManualArtist(event) {
        const artistId = event.currentTarget.dataset.artistId
        const artistName = event.currentTarget.dataset.artistName

        this.selectedArtist = {
            id: artistId,
            name: artistName
        }

        this.updateSelectedArtistInfo()
        this.hideTrackSuggestions()
        this.showAlbumSelectionSection()
    }

    selectManualAlbum(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle

        this.selectedAlbum = {
            id: albumId,
            title: albumTitle
        }

        this.updateSelectedAlbumInfo()
        this.hideTrackSuggestions()
        this.showManualMatchingSection()
    }

    selectManualTrack(event) {
        const trackId = event.currentTarget.dataset.artistId
        const trackTitle = event.currentTarget.dataset.artistName

        this.selectedTrack = {
            id: trackId,
            title: trackTitle
        }

        this.updateSelectedTrackInfo()
        this.hideTrackSuggestions()
        this.showManualMatchingSection()
    }

    // Update info displays
    updateSelectedArtistInfo() {
        if (this.hasSelectedArtistInfoTarget) {
            this.selectedArtistInfoTarget.innerHTML = `
                <div class="alert alert-info">
                    <strong>Selected Artist:</strong> ${this.escapeHtml(this.selectedArtist.name)}
                </div>
            `
        }
    }

    updateSelectedAlbumInfo() {
        if (this.hasSelectedAlbumInfoTarget) {
            this.selectedAlbumInfoTarget.innerHTML = `
                <div class="alert alert-info">
                    <strong>Selected Album:</strong> ${this.escapeHtml(this.selectedAlbum.title)}
                </div>
            `
        }
    }

    updateSelectedTrackInfo() {
        if (this.hasSelectedTrackInfoTarget) {
            this.selectedTrackInfoTarget.innerHTML = `
                <div class="alert alert-info">
                    <strong>Selected Track:</strong> ${this.escapeHtml(this.selectedTrack.title)}
                </div>
            `
        }
    }

    // Show/hide sections
    showAlbumSelectionSection() {
        if (this.hasAlbumSelectionSectionTarget) {
            this.albumSelectionSectionTarget.style.display = 'block'
        }
    }

    showManualMatchingSection() {
        if (this.hasManualMatchingSectionTarget) {
            this.manualMatchingSectionTarget.style.display = 'block'
        }
    }

    showSearchResults() {
        if (this.hasSearchResultsTarget) {
            this.searchResultsTarget.style.display = 'block'
        }
    }

    hideSearchResults() {
        if (this.hasSearchResultsTarget) {
            this.searchResultsTarget.style.display = 'none'
        }
    }

    showAlbumSearchResults() {
        if (this.hasAlbumSearchResultsTarget) {
            this.albumSearchResultsTarget.style.display = 'block'
        }
    }

    hideAlbumSearchResults() {
        if (this.hasAlbumSearchResultsTarget) {
            this.albumSearchResultsTarget.style.display = 'none'
        }
    }

    showTrackSuggestions() {
        if (this.hasTrackSuggestionsTarget) {
            this.trackSuggestionsTarget.style.display = 'block'
        }
    }

    hideTrackSuggestions() {
        if (this.hasTrackSuggestionsTarget) {
            this.trackSuggestionsTarget.style.display = 'none'
        }
    }

    // Loading spinners
    showLoadingSpinner() {
        if (this.hasLoadingSpinnerTarget) {
            this.loadingSpinnerTarget.style.display = 'block'
        }
    }

    hideLoadingSpinner() {
        if (this.hasLoadingSpinnerTarget) {
            this.loadingSpinnerTarget.style.display = 'none'
        }
    }

    showAlbumLoadingSpinner() {
        if (this.hasAlbumLoadingSpinnerTarget) {
            this.albumLoadingSpinnerTarget.style.display = 'block'
        }
    }

    hideAlbumLoadingSpinner() {
        if (this.hasAlbumLoadingSpinnerTarget) {
            this.albumLoadingSpinnerTarget.style.display = 'none'
        }
    }

    showTrackLoadingSpinner() {
        if (this.hasTrackLoadingSpinnerTarget) {
            this.trackLoadingSpinnerTarget.style.display = 'block'
        }
    }

    hideTrackLoadingSpinner() {
        if (this.hasTrackLoadingSpinnerTarget) {
            this.trackLoadingSpinnerTarget.style.display = 'none'
        }
    }

    // Utility methods
    formatFileSize(bytes) {
        if (!bytes || bytes < 0) return '0 B'

        const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB']
        const i = Math.floor(Math.log(bytes) / Math.log(1024))
        const size = bytes / Math.pow(1024, i)
        
        return `${size.toFixed(2)} ${sizes[i]}`
    }

    formatDuration(seconds) {
        if (!seconds || seconds < 0) return '0:00'

        const hours = Math.floor(seconds / 3600)
        const minutes = Math.floor((seconds % 3600) / 60)
        const remainingSeconds = seconds % 60

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`
        } else {
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
        }
    }

    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
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
}
