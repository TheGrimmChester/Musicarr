import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'loadingReleases', 'releasesList',
        'albumActionsCollapse', 'actionsToggleText', 'actionsToggleIcon'
    ]

    static values = {
        albumId: Number,
        albumTitle: String,
        artistId: Number,
        artistName: String,
        tracks: Array
    }

    connect() {
        console.log('AlbumManagement controller connected!')
        console.log('Controller element:', this.element)
        
        // Check if we have album data (album page) or not (artist page)
        const hasAlbumData = this.hasAlbumIdValue && this.albumIdValue > 0
        
        if (hasAlbumData) {
            console.log('Album page mode - Album ID value:', this.albumIdValue)
            console.log('Album title value:', this.albumTitleValue)
            console.log('Artist ID value:', this.artistIdValue)
            console.log('Artist name value:', this.artistNameValue)
            console.log('Tracks data value:', this.tracksValue)
            console.log('Tracks data length:', this.tracksValue ? this.tracksValue.length : 'undefined')
        } else {
            console.log('Artist page mode - Album management controller ready for dynamic album loading')
        }
        
        this.patterns = []
        this.currentPreviews = []
        this.setupEventHandlers()
    }

    setupEventHandlers() {
        // Handle album actions collapse toggle
        const albumActionsCollapse = document.getElementById('albumActionsCollapse')
        const toggleText = document.querySelector('.actions-toggle-text')
        const toggleIcon = document.querySelector('.actions-toggle-icon')

        if (albumActionsCollapse && toggleText && toggleIcon) {
            albumActionsCollapse.addEventListener('show.bs.collapse', () => {
                toggleText.textContent = 'Hide Album Actions'
                toggleIcon.className = 'fas fa-chevron-up ms-2 actions-toggle-icon'
            })

            albumActionsCollapse.addEventListener('hide.bs.collapse', () => {
                toggleText.textContent = 'Show Album Actions'
                toggleIcon.className = 'fas fa-chevron-down ms-2 actions-toggle-icon'
            })
        }

        // Check for existing download statuses on page load
        this.checkExistingDownloadStatuses()
    }



    markTrackDownloaded(event) {
        const trackId = event.currentTarget.dataset.trackId
        const trackTitle = event.currentTarget.dataset.trackTitle
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...'
        button.disabled = true
        
        // Make API call to mark track as downloaded
        this.markTrackDownloadedInAPI(trackId, trackTitle)
            .then(() => {
                // Refresh the tracks data to update status
                const albumId = this.currentAlbumId || this.albumIdValue
                if (albumId) {
                    this.loadTracksWithAnalysisStatus(albumId)
                }
                this.showAlert('success', `Marked as downloaded: ${trackTitle}`)
            })
            .catch((error) => {
                console.error('Update error:', error)
                this.showAlert('danger', `Failed to update status for: ${trackTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async markTrackDownloadedInAPI(trackId, trackTitle) {
        try {
            const response = await fetch(`/track/${trackId}/mark-downloaded`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    trackId: trackId,
                    downloaded: true
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API update error:', error)
            throw error
        }
    }

    scanTrackAudio(event) {
        const trackId = event.currentTarget.dataset.trackId
        const trackTitle = event.currentTarget.dataset.trackTitle
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning...'
        button.disabled = true
        
        // Make API call to scan track audio
        this.scanTrackAudioInAPI(trackId, trackTitle)
            .then(() => {
                this.showAlert('success', `Audio scan completed for: ${trackTitle}`)
            })
            .catch((error) => {
                console.error('Scan error:', error)
                this.showAlert('danger', `Audio scan failed for: ${trackTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async scanTrackAudioInAPI(trackId, trackTitle) {
        try {
            const response = await fetch(`/audio-quality/analyze/${trackId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API scan error:', error)
            throw error
        }
    }

    scanAlbumAudio(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning...'
        button.disabled = true
        
        // Make API call to scan album audio
        this.scanAlbumAudioInAPI(albumId, albumTitle)
            .then((result) => {
                if (result.success) {
                    this.showAlert('success', `Audio analysis started for album: ${albumTitle}. ${result.tasks_created} tasks created.`)
                } else {
                    this.showAlert('warning', `Audio analysis partially completed: ${result.message}`)
                }
            })
            .catch((error) => {
                console.error('Album scan error:', error)
                this.showAlert('danger', `Audio analysis failed for album: ${albumTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async scanAlbumAudioInAPI(albumId, albumTitle) {
        try {
            // Get all track IDs from current tracks
            const trackIds = this.currentTracks 
                ? this.currentTracks.map(track => track.id)
                : []

            if (trackIds.length === 0) {
                throw new Error('No tracks found for this album')
            }

            // Use the batch analyze endpoint with track IDs
            const formData = new FormData()
            trackIds.forEach(trackId => {
                formData.append('track_ids[]', trackId)
            })

            const response = await fetch('/audio-quality/analyze-batch', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API album scan error:', error)
            throw error
        }
    }

    async getTrackAnalysisStatus(trackId) {
        try {
            const response = await fetch(`/audio-quality/status/${trackId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                return null
            }

            const result = await response.json()
            return result.success ? result : null
        } catch (error) {
            console.error('Error fetching analysis status:', error)
            return null
        }
    }

    async loadTracksWithAnalysisStatus(albumId) {
        try {
            // Show loading state
            this.showTracksLoading()
            
            // Fetch tracks from API
            const tracks = await this.fetchAlbumTracks(albumId)
            
            // Store tracks data for later use
            this.currentTracks = tracks
            
            if (tracks && tracks.length > 0) {
                // Load analysis status for each track
                const tracksWithStatus = await Promise.all(
                    tracks.map(async (track) => {
                        const analysisStatus = await this.getTrackAnalysisStatus(track.id)
                        return {
                            ...track,
                            analysisStatus: analysisStatus
                        }
                    })
                )
                
                this.currentTracks = tracksWithStatus
                this.displayTracks(tracksWithStatus)
                this.showTracksContent()
            } else {
                this.showNoTracksMessage()
            }
        } catch (error) {
            console.error('Error loading tracks with analysis status:', error)
            this.showTracksError()
        }
    }

    loadAlbumTracks(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle
        
        console.log('loadAlbumTracks called with:', { albumId, albumTitle })
        
        if (!albumId) {
            console.error('No album ID provided')
            this.showAlert('danger', 'No album ID provided')
            return
        }
        
        // Store the album data for use in the modal (temporarily for this session)
        this.currentAlbumId = albumId
        this.currentAlbumTitle = albumTitle
        
        // Show the tracks modal
        this.showTracksModal()
        
        // Load tracks data with analysis status
        this.loadTracksWithAnalysisStatus(albumId)
    }

    showTracksModal() {
        // Check if modal already exists
        let modalElement = document.getElementById('tracksModal')
        
        // Create modal HTML if it doesn't exist
        if (!modalElement) {
            this.createTracksModal()
            modalElement = document.getElementById('tracksModal')
            
            // Set up event listeners for the modal buttons
            this.setupModalEventListeners()
        }
        
        // Show the modal
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement)
            modal.show()
        }
    }

    setupModalEventListeners() {
        // Set up event listeners for tracks modal
        const tracksModal = document.getElementById('tracksModal')
        if (tracksModal) {
            this.setupTracksModalEventListeners(tracksModal)
        }

        // Set up event listeners for other releases modal
        const otherReleasesModal = document.getElementById('otherReleasesModal')
        if (otherReleasesModal) {
            this.setupOtherReleasesModalEventListeners(otherReleasesModal)
        }
    }

    setupTracksModalEventListeners(modal) {
        // Use event delegation to handle button clicks in the tracks modal
        modal.addEventListener('click', (event) => {
            const target = event.target.closest('button')
            if (!target) return

            const trackId = target.dataset.trackId
            const trackTitle = target.dataset.trackTitle
            const albumId = target.dataset.albumId

            // Handle different button types
            if (target.classList.contains('track-rename-btn')) {
                this.renameTrack({ 
                    currentTarget: { 
                        dataset: { trackId, trackTitle } 
                    } 
                })
            } else if (target.classList.contains('track-scan-btn')) {
                this.scanTrackAudio({ 
                    currentTarget: { 
                        dataset: { trackId, trackTitle } 
                    } 
                })
            } else if (target.classList.contains('track-mark-missing-btn')) {
                this.markTrackMissing({ 
                    currentTarget: { 
                        dataset: { trackId, trackTitle } 
                    } 
                })
            } else if (target.classList.contains('track-mark-downloaded-btn')) {
                this.markTrackDownloaded({ 
                    currentTarget: { 
                        dataset: { trackId, trackTitle } 
                    } 
                })
            } else if (target.classList.contains('track-download-btn')) {
                this.downloadTrack({ 
                    currentTarget: { 
                        dataset: { albumId, trackId, trackTitle } 
                    } 
                })
            } else if (target.classList.contains('scan-all-tracks-btn')) {
                this.scanAlbumAudioFromModal()
            } else if (target.classList.contains('refresh-analysis-btn')) {
                this.refreshAnalysisStatus()
            }
        })
    }

    setupOtherReleasesModalEventListeners(modal) {
        console.log('Setting up event listeners for other releases modal:', modal)
        
        // Use event delegation to handle button clicks in the other releases modal
        modal.addEventListener('click', (event) => {
            console.log('Other releases modal click event:', event)
            const target = event.target.closest('button')
            console.log('Closest button target:', target)
            
            if (!target) {
                console.log('No button target found')
                return
            }

            console.log('Button classes:', target.classList.toString())
            console.log('Button dataset:', target.dataset)

            // Handle change release button
            if (target.classList.contains('change-release-btn')) {
                console.log('Change release button clicked!')
                const releaseId = target.dataset.releaseId
                const releaseTitle = target.dataset.releaseTitle
                console.log('Calling changeRelease with:', { releaseId, releaseTitle })
                this.changeRelease(releaseId, releaseTitle, event)
            } else {
                console.log('Button clicked but not a change-release-btn')
            }
        })
        
        console.log('Event listeners set up for other releases modal')
    }

    createTracksModal() {
        const modalHtml = `
            <div class="modal fade" 
                 id="tracksModal" 
                 tabindex="-1" 
                 aria-labelledby="tracksModalLabel" 
                 aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tracksModalLabel">
                                <i class="fas fa-music me-2"></i>
                                <span id="tracksModalTitle">Album Tracks</span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center" id="tracksLoading">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading tracks...</p>
                            </div>
                            <div id="tracksContent" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50">#</th>
                                                <th>Titre</th>
                                                <th width="100">Durée</th>
                                                <th width="120">Qualité</th>
                                                <th width="100">Statut</th>
                                                <th width="140">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tracksTableBody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="tracksError" style="display: none;" class="text-center text-danger">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <p>Error loading tracks. Please try again.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="downloadAllTracks" style="display: none;">
                                <i class="fas fa-download me-1"></i>Download All Missing
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `
        
        // Append modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml)
        
        // Update modal title with album name
        const titleElement = document.getElementById('tracksModalTitle')
        const albumTitle = this.currentAlbumTitle || this.albumTitleValue
        if (titleElement && albumTitle) {
            titleElement.textContent = `Tracks: ${albumTitle}`
        }
    }

    async loadTracksData(albumId) {
        try {
            // Show loading state
            this.showTracksLoading()
            
            // Fetch tracks from API
            const tracks = await this.fetchAlbumTracks(albumId)
            
            // Store tracks data for later use
            this.currentTracks = tracks
            
            if (tracks && tracks.length > 0) {
                this.displayTracks(tracks)
                this.showTracksContent()
            } else {
                this.showNoTracksMessage()
            }
        } catch (error) {
            console.error('Error loading tracks:', error)
            this.showTracksError()
        }
    }

    async fetchAlbumTracks(albumId) {
        try {
            const response = await fetch(`/album/${albumId}/tracks`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            // Transform the API response to match our display format
            const tracks = data.tracks || [];
            return tracks.map(track => ({
                id: track.id,
                trackNumber: track.trackNumber || 0,
                title: track.title || 'Unknown Track',
                duration: track.duration || 0,
                status: this.determineTrackStatus(track),
                filePath: track.files && track.files.length > 0 ? track.files[0].filePath : null,
                mediumNumber: track.mediumNumber || 1,
                mediumTitle: track.medium ? track.medium.title : null,
                mbid: track.mbid,
                monitored: track.monitored || false,
                downloaded: track.downloaded || false,
                hasFile: track.hasFile || false,
                fileCount: track.fileCount || 0,
                totalFileSize: track.totalFileSize || 0,
                files: track.files || []
            }));
        } catch (error) {
            console.error('Error fetching album tracks:', error);
            throw error;
        }
    }

    determineTrackStatus(track) {
        if (track.hasFile && track.fileCount > 0) {
            return 'downloaded';
        } else if (track.downloaded) {
            return 'downloaded';
        } else if (track.monitored) {
            return 'missing';
        } else {
            return 'unmonitored';
        }
    }

    displayTracks(tracks) {
        const tbody = document.getElementById('tracksTableBody')
        if (!tbody) return
        
        tbody.innerHTML = tracks.map(track => {
            // Find best quality file (similar to album page logic)
            let bestQualityFile = null
            let bestQualityScore = 0
            if (track.files && track.files.length > 0) {
                track.files.forEach(file => {
                    // Use a simple quality scoring (can be improved)
                    const qualityScore = file.qualityScore || this.calculateQualityScore(file.quality)
                    if (qualityScore > bestQualityScore) {
                        bestQualityScore = qualityScore
                        bestQualityFile = file
                    }
                })
            }
            
            // Calculate duration from best quality file or use track duration
            let duration = '--'
            if (bestQualityFile && bestQualityFile.duration && bestQualityFile.duration > 0) {
                const totalSeconds = bestQualityFile.duration
                const minutes = Math.floor(totalSeconds / 60)
                const seconds = totalSeconds % 60
                duration = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
            } else if (track.duration) {
                duration = this.formatDuration(track.duration)
            }
            
            return `
                <tr>
                    <td>${track.trackNumber}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <strong>${track.title}</strong>
                            ${track.hasLyrics ? `<i class="fas fa-file-alt text-success ms-2" title="Paroles disponibles"></i>` : ''}
                        </div>
                        ${track.disambiguation ? `<br><small class="text-muted">${track.disambiguation}</small>` : ''}
                    </td>
                    <td>${duration}</td>
                    <td>
                        ${bestQualityFile && bestQualityFile.quality ? 
                            `<span class="badge bg-info">${bestQualityFile.quality}</span>` : 
                            '--'
                        }
                    </td>
                    <td>
                        ${track.downloaded ? 
                            `<span class="badge bg-success">Téléchargé</span>` : 
                            `<span class="badge bg-warning">Manquant</span>`
                        }
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="/track/${track.id}" class="btn btn-outline-primary" title="Détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            ${track.needRename ? 
                                `<button class="btn btn-outline-warning track-rename-btn" 
                                         data-track-id="${track.id}" 
                                         data-track-title="${track.title}" 
                                         title="Rename">
                                    <i class="fas fa-edit"></i>
                                 </button>` : ''
                            }
                            <button class="btn btn-outline-secondary track-scan-btn" 
                                    data-track-id="${track.id}" 
                                    data-track-title="${track.title}" 
                                    title="Scanner la qualité audio">
                                <i class="fas fa-music"></i>
                            </button>
                            ${track.downloaded ? 
                                `<button class="btn btn-outline-success track-mark-missing-btn" 
                                         data-track-id="${track.id}" 
                                         title="Marquer comme manquant">
                                    <i class="fas fa-times"></i>
                                 </button>` : 
                                `<button class="btn btn-outline-success track-mark-downloaded-btn" 
                                         data-track-id="${track.id}" 
                                         title="Marquer comme téléchargé">
                                    <i class="fas fa-check"></i>
                                 </button>
                                 <button class="btn btn-outline-info track-download-btn" 
                                         data-album-id="${this.currentAlbumId || this.albumIdValue}" 
                                         data-track-id="${track.id}" 
                                         title="Télécharger automatiquement">
                                    <i class="fas fa-download"></i>
                                 </button>`
                            }
                        </div>
                    </td>
                </tr>
            `
        }).join('')
        
        // Show download all button if there are missing tracks
        const missingTracks = tracks.filter(track => track.status === 'missing' || track.status === 'unmonitored')
        const downloadAllBtn = document.getElementById('downloadAllTracks')
        if (downloadAllBtn && missingTracks.length > 0) {
            downloadAllBtn.style.display = 'inline-block'
            downloadAllBtn.onclick = () => this.downloadAllMissingTracks(missingTracks)
        }

        // Add analysis status information below the table
        this.displayAnalysisStatus(tracks)
    }

    displayAnalysisStatus(tracks) {
        const contentDiv = document.getElementById('tracksContent')
        if (!contentDiv) return

        // Check if analysis status section already exists
        let statusSection = contentDiv.querySelector('#analysisStatusSection')
        if (!statusSection) {
            statusSection = document.createElement('div')
            statusSection.id = 'analysisStatusSection'
            statusSection.className = 'mt-4'
            contentDiv.appendChild(statusSection)
        }

        // Count tracks with different analysis statuses
        const totalTracks = tracks.length
        const tracksWithFiles = tracks.filter(track => track.fileCount > 0).length
        const analyzedTracks = tracks.filter(track => 
            track.analysisStatus && 
            track.analysisStatus.files_with_quality > 0
        ).length
        const pendingTracks = tracks.filter(track => 
            track.analysisStatus && 
            track.analysisStatus.active_tasks > 0
        ).length

        statusSection.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Audio Quality Analysis Status
                        <button class="btn btn-sm btn-outline-primary float-end refresh-analysis-btn">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-primary">${totalTracks}</h4>
                                <small class="text-muted">Total Tracks</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-success">${tracksWithFiles}</h4>
                                <small class="text-muted">Tracks with Files</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-info">${analyzedTracks}</h4>
                                <small class="text-muted">Analyzed Tracks</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-warning">${pendingTracks}</h4>
                                <small class="text-muted">Pending Analysis</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <button class="btn btn-primary scan-all-tracks-btn">
                            <i class="fas fa-search me-1"></i>Analyze All Tracks
                        </button>
                    </div>
                </div>
            </div>
        `
    }

    refreshAnalysisStatus() {
        const albumId = this.currentAlbumId || this.albumIdValue
        if (albumId) {
            this.loadTracksWithAnalysisStatus(albumId)
        }
    }

    scanAlbumAudioFromModal() {
        // Create a mock event object for the scanAlbumAudio method
        const albumId = this.currentAlbumId || this.albumIdValue
        const albumTitle = this.currentAlbumTitle || this.albumTitleValue
        
        const mockEvent = {
            currentTarget: {
                dataset: {
                    albumId: albumId,
                    albumTitle: albumTitle
                }
            }
        }
        this.scanAlbumAudio(mockEvent)
    }

    // Album actions

    markAlbumDownloaded(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle || 'this album'
        
        // Show confirmation dialog
        const confirmed = confirm(`Mark album as downloaded: ${albumTitle}?`)
        if (!confirmed) return
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...'
        button.disabled = true
        
        // Make API call to mark album as downloaded
        this.markAlbumDownloadedInAPI(albumId, albumTitle)
            .then(() => {
                this.showAlert('success', `Album marked as downloaded: ${albumTitle}`)
                // Refresh tracks if modal is open
                if (this.albumIdValue === albumId) {
                    this.loadTracksWithAnalysisStatus(albumId)
                }
            })
            .catch((error) => {
                console.error('Album update error:', error)
                this.showAlert('danger', `Failed to update album: ${albumTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async markAlbumDownloadedInAPI(albumId, albumTitle) {
        try {
            const response = await fetch(`/album/${albumId}/mark-downloaded`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    albumId: albumId,
                    downloaded: true
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API album update error:', error)
            throw error
        }
    }

    updateAlbum(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle || 'this album'
        
        // Show confirmation dialog
        const confirmed = confirm(`Update album: ${albumTitle}?`)
        if (!confirmed) return
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...'
        button.disabled = true
        
        // Make API call to update album
        this.updateAlbumFromAPI(albumId, albumTitle)
            .then(() => {
                this.showAlert('success', `Album update started: ${albumTitle}`)
            })
            .catch((error) => {
                console.error('Album update error:', error)
                this.showAlert('danger', `Failed to update album: ${albumTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async updateAlbumFromAPI(albumId, albumTitle) {
        try {
            const response = await fetch(`/album/${albumId}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    albumId: albumId
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API album update error:', error)
            throw error
        }
    }

    deleteAlbum(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle || 'this album'
        const artistName = event.currentTarget.dataset.artistName || 'Unknown Artist'
        
        // Show confirmation dialog
        const confirmed = confirm(`Are you sure you want to delete the album "${albumTitle}" by ${artistName}?\n\nThis action cannot be undone.`)
        if (!confirmed) return
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...'
        button.disabled = true
        
        // Make API call to delete album
        this.deleteAlbumFromAPI(albumId, albumTitle)
            .then(() => {
                this.showAlert('success', `Album deleted: ${albumTitle}`)
                // Close modal if open and refresh page or remove from list
                if (this.albumIdValue === albumId) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('tracksModal'))
                    if (modal) {
                        modal.hide()
                    }
                }
                // Refresh the page to update the album list
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            })
            .catch((error) => {
                console.error('Album deletion error:', error)
                this.showAlert('danger', `Failed to delete album: ${albumTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async deleteAlbumFromAPI(albumId, albumTitle) {
        try {
            const response = await fetch(`/album/${albumId}/delete`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API album deletion error:', error)
            throw error
        }
    }

    clearAlbumCache(event) {
        const mbid = event.currentTarget.dataset.mbid
        const title = event.currentTarget.dataset.title || 'this album'
        
        if (!mbid) {
            this.showAlert('warning', 'No MusicBrainz ID available for this album')
            return
        }
        
        // Show confirmation dialog
        const confirmed = confirm(`Clear cache for album: ${title}?\n\nThis will remove cached metadata and force a fresh fetch from MusicBrainz.`)
        if (!confirmed) return
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Clearing...'
        button.disabled = true
        
        // Make API call to clear album cache
        this.clearAlbumCacheFromAPI(mbid, title)
            .then(() => {
                this.showAlert('success', `Cache cleared for album: ${title}`)
            })
            .catch((error) => {
                console.error('Cache clear error:', error)
                this.showAlert('danger', `Failed to clear cache for album: ${title}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async clearAlbumCacheFromAPI(mbid, title) {
        try {
            const response = await fetch(`/album/clear-cache/${mbid}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API cache clear error:', error)
            throw error
        }
    }

    toggleAlbumMonitor(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle || 'this album'
        const currentMonitored = event.currentTarget.checked
        
        // Show loading state
        const checkbox = event.currentTarget
        checkbox.disabled = true
        
        // Make API call to toggle album monitoring
        this.toggleAlbumMonitorInAPI(albumId, currentMonitored, albumTitle)
            .then(() => {
                this.showAlert('success', `Monitoring ${currentMonitored ? 'enabled' : 'disabled'} for album: ${albumTitle}`)
            })
            .catch((error) => {
                console.error('Album monitor toggle error:', error)
                this.showAlert('danger', `Failed to update monitoring for album: ${albumTitle}`)
                // Revert checkbox state on error
                checkbox.checked = !currentMonitored
            })
            .finally(() => {
                // Re-enable checkbox
                checkbox.disabled = false
            })
    }

    async toggleAlbumMonitorInAPI(albumId, monitored, albumTitle) {
        try {
            const response = await fetch(`/album/${albumId}/toggle-monitor`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    albumId: albumId,
                    monitored: monitored
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API album monitor toggle error:', error)
            throw error
        }
    }





    // Modal is now defined in the template, no need to create it dynamically



























    // Other releases functionality
    showOtherReleases(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle || 'this album'
        
        // Show the other releases modal
        this.showOtherReleasesModal(albumId, albumTitle)
    }

    showOtherReleasesModal(albumId, albumTitle) {
        // Use the existing template modal
        const modalElement = document.getElementById('otherReleasesModal')
        if (!modalElement) {
            console.error('Other releases modal not found')
            return
        }
        
        // Set up event listeners for this modal if not already done
        this.setupOtherReleasesModalEventListeners(modalElement)
        
        const modal = new bootstrap.Modal(modalElement)
        modal.show()
        
        // Load other releases data
        this.loadOtherReleases(albumId, albumTitle)
    }



    async loadOtherReleases(albumId, albumTitle) {
        try {
            // Show loading state
            this.showOtherReleasesLoading()
            
            // Fetch other releases from API
            const releases = await this.fetchOtherReleases(albumId)
            
            // Debug: Log the releases data structure
            console.log('Other releases data:', releases)
            if (releases && releases.length > 0) {
                console.log('First release structure:', releases[0])
            }
            
            if (releases && releases.length > 0) {
                this.displayOtherReleases(releases, albumTitle)
                this.showOtherReleasesContent()
            } else {
                this.showNoOtherReleasesMessage()
            }
        } catch (error) {
            console.error('Error loading other releases:', error)
            this.showOtherReleasesError()
        }
    }

    async fetchOtherReleases(albumId) {
        try {
            const response = await fetch(`/album/${albumId}/other-releases`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result.releases || []
        } catch (error) {
            console.error('API other releases error:', error)
            throw error
        }
    }

    displayOtherReleases(releases, albumTitle) {
        const listDiv = document.getElementById('releasesList')
        if (!listDiv) return
        
        // Helper function to extract year from date
        const extractYear = (dateString) => {
            if (!dateString) return 'Unknown'
            const year = dateString.split('-')[0]
            return year || 'Unknown'
        }
        
        // Helper function to get primary type
        const getPrimaryType = (release) => {
            if (release['primary-type']) return release['primary-type']
            if (release.type) return release.type
            return 'Unknown'
        }
        
        // Helper function to get medium type
        const getMediumType = (release) => {
            if (release.media && release.media.length > 0) {
                const types = [...new Set(release.media.map(medium => medium.format || 'Unknown'))]
                return types.join(', ')
            }
            return 'Unknown'
        }
        
        listDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Found ${releases.length} other releases for "${albumTitle}"
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Year</th>
                            <th>Country</th>
                            <th>Mediums</th>
                            <th>Tracks</th>
                            <th>Medium Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${releases.map(release => `
                            <tr>
                                <td>${release.title || 'Unknown'}</td>
                                <td>${getPrimaryType(release)}</td>
                                <td>${extractYear(release.date)}</td>
                                <td>${release.country || 'Unknown'}</td>
                                <td>${release.mediaCount || 'Unknown'}</td>
                                <td>${release.totalTracks || 'Unknown'}</td>
                                <td>${getMediumType(release)}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary change-release-btn" 
                                            data-release-id="${release.id}" 
                                            data-release-title="${release.title}">
                                        <i class="fas fa-exchange-alt me-1"></i>Change
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `
    }

    showOtherReleasesLoading() {
        const loading = document.getElementById('loadingReleases')
        const content = document.getElementById('releasesList')
        
        if (loading) loading.style.display = 'block'
        if (content) content.style.display = 'none'
    }

    showOtherReleasesContent() {
        const loading = document.getElementById('loadingReleases')
        const content = document.getElementById('releasesList')
        
        if (loading) loading.style.display = 'none'
        if (content) content.style.display = 'block'
    }

    showOtherReleasesError() {
        const loading = document.getElementById('loadingReleases')
        const content = document.getElementById('releasesList')
        
        if (loading) loading.style.display = 'none'
        if (content) content.style.display = 'block'
        
        // Show error message in the releases list
        if (content) {
            content.innerHTML = `
                <div class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <p>Error loading other releases. Please try again.</p>
                </div>
            `
        }
    }

    showNoOtherReleasesMessage() {
        const listDiv = document.getElementById('releasesList')
        if (listDiv) {
            listDiv.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-compact-disc fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Other Releases Found</h5>
                    <p class="text-muted">No additional releases were found for this album.</p>
                </div>
            `
        }
        this.showOtherReleasesContent()
    }

    toggleReleaseTracks(event) {
        const releaseId = event.currentTarget.dataset.releaseId
        const releaseTitle = event.currentTarget.dataset.releaseTitle || 'this release'
        
        this.showAlert('info', `Toggle release tracks functionality for: ${releaseTitle}`)
    }

    async changeRelease(releaseId, releaseTitle, event) {
        console.log('changeRelease called with:', { releaseId, releaseTitle, event })
        
        // Show confirmation dialog
        const confirmed = confirm(`Change to release: ${releaseTitle}?\n\nThis will update the album with the selected release information and trigger a resync.`)
        if (!confirmed) {
            console.log('User cancelled the change')
            return
        }
        
        try {
            // Get current album ID
            const albumId = this.albumIdValue
            console.log('Album ID:', albumId)
            
            if (!albumId) {
                this.showAlert('danger', 'Album ID not available')
                return
            }
            
            // Show loading state on the button
            const button = event?.currentTarget || document.querySelector(`[data-release-id="${releaseId}"]`)
            console.log('Button element:', button)
            
            if (button) {
                const originalContent = button.innerHTML
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Changing...'
                button.disabled = true
                
                console.log('Making API call to change release...')
                
                // Make API call to change release
                const response = await fetch(`/album/${albumId}/change-release`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `mbid=${encodeURIComponent(releaseId)}`
                })
                
                console.log('API response status:', response.status)
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`)
                }
                
                const result = await response.json()
                console.log('API response result:', result)
                
                if (result.success) {
                    this.showAlert('success', result.message || 'Release changed successfully!')
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('otherReleasesModal'))
                    if (modal) {
                        modal.hide()
                    }
                    // Reload the page to show updated album information
                    setTimeout(() => {
                        window.location.reload()
                    }, 2000)
                } else {
                    throw new Error(result.error || 'Unknown error occurred')
                }
            } else {
                console.error('Button element not found')
                this.showAlert('danger', 'Button element not found')
            }
        } catch (error) {
            console.error('Change release error:', error)
            this.showAlert('danger', `Failed to change release: ${error.message}`)
        } finally {
            // Restore button state if button was found
            const button = event?.currentTarget || document.querySelector(`[data-release-id="${releaseId}"]`)
            if (button) {
                button.innerHTML = '<i class="fas fa-exchange-alt me-1"></i>Change'
                button.disabled = false
            }
        }
    }

    // Utility methods for patterns and status checking
    // Patterns are loaded when the rename modal is opened





    checkExistingDownloadStatuses() {
        // Check and update download statuses for tracks
        if (this.currentTracks) {
            this.currentTracks.forEach(track => {
                // Update visual indicators based on download status
                this.updateTrackDownloadStatus(track)
            })
        }
    }

    updateTrackDownloadStatus(track) {
        // Update visual indicators for track download status
        // This would be called when tracks are loaded or status changes
        console.log(`Track ${track.id} download status: ${track.status}`)
    }

    goToArtist(event) {
        const artistId = event.currentTarget.dataset.artistId
        if (artistId) {
            window.location.href = `/artist/${artistId}`
        } else {
            this.showAlert('warning', 'Artist ID not available')
        }
    }

    markTrackMissing(event) {
        const trackId = event.currentTarget.dataset.trackId
        const trackTitle = event.currentTarget.dataset.trackTitle || 'this track'
        
        // Show confirmation dialog
        const confirmed = confirm(`Mark track as missing: ${trackTitle}?\n\nThis will update the track status to indicate it's missing from the library.`)
        if (!confirmed) return
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...'
        button.disabled = true
        
        // Make API call to mark track as missing
        this.markTrackMissingInAPI(trackId, trackTitle)
            .then(() => {
                this.showAlert('success', `Track marked as missing: ${trackTitle}`)
                // Refresh tracks if modal is open
                const albumId = this.currentAlbumId || this.albumIdValue
                if (albumId) {
                    this.loadTracksWithAnalysisStatus(albumId)
                }
            })
            .catch((error) => {
                console.error('Track update error:', error)
                this.showAlert('danger', `Failed to update track: ${trackTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async markTrackMissingInAPI(trackId, trackTitle) {
        try {
            const response = await fetch(`/track/${trackId}/mark-missing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    trackId: trackId,
                    missing: true
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API track update error:', error)
            throw error
        }
    }

    showTracksLoading() {
        const loading = document.getElementById('tracksLoading')
        const content = document.getElementById('tracksContent')
        const error = document.getElementById('tracksError')
        
        if (loading) loading.style.display = 'block'
        if (content) content.style.display = 'none'
        if (error) error.style.display = 'none'
    }

    showTracksContent() {
        const loading = document.getElementById('tracksLoading')
        const content = document.getElementById('tracksContent')
        const error = document.getElementById('tracksError')
        
        if (loading) loading.style.display = 'none'
        if (content) content.style.display = 'block'
        if (error) error.style.display = 'none'
    }

    showTracksError() {
        const loading = document.getElementById('tracksLoading')
        const content = document.getElementById('tracksContent')
        const error = document.getElementById('tracksError')
        
        if (loading) loading.style.display = 'none'
        if (content) content.style.display = 'none'
        if (error) error.style.display = 'block'
    }

    showNoTracksMessage() {
        const tbody = document.getElementById('tracksTableBody')
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-music fa-2x mb-2"></i>
                        <p>No tracks found for this album.</p>
                    </td>
                </tr>
            `
        }
        this.showTracksContent()
    }

    downloadAllMissingTracks(missingTracks) {
        if (!missingTracks || missingTracks.length === 0) {
            this.showAlert('info', 'No missing tracks to download')
            return
        }

        // Show confirmation dialog
        const trackNames = missingTracks.map(track => track.title).join(', ')
        const confirmed = confirm(`Download ${missingTracks.length} missing tracks?\n\n${trackNames}`)
        
        if (!confirmed) {
            return
        }

        // Show loading state on the download all button
        const downloadAllBtn = document.getElementById('downloadAllTracks')
        if (downloadAllBtn) {
            const originalContent = downloadAllBtn.innerHTML
            downloadAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Downloading...'
            downloadAllBtn.disabled = true
            
            // Download all missing tracks
            this.downloadAllMissingTracksFromAPI(missingTracks)
                .then(() => {
                    this.showAlert('success', `Download started for ${missingTracks.length} tracks`)
                    // Refresh the tracks data
                    const albumId = this.currentAlbumId || this.albumIdValue
                    if (albumId) {
                        this.loadTracksWithAnalysisStatus(albumId)
                    }
                })
                .catch((error) => {
                    console.error('Bulk download error:', error)
                    this.showAlert('danger', `Bulk download failed`)
                })
                .finally(() => {
                    // Restore button state
                    downloadAllBtn.innerHTML = originalContent
                    downloadAllBtn.disabled = false
                })
        }
    }

    async downloadAllMissingTracksFromAPI(missingTracks) {
        try {
            const albumId = this.currentAlbumId || this.albumIdValue
            const response = await fetch(`/album/${albumId}/download-missing-tracks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    trackIds: missingTracks.map(track => track.id),
                    albumId: albumId
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API bulk download error:', error)
            throw error
        }
    }

    toggleTrackMonitor(event) {
        const trackId = event.currentTarget.dataset.trackId
        const trackTitle = event.currentTarget.dataset.trackTitle
        const currentMonitored = event.currentTarget.dataset.monitored === 'true'
        
        // Show loading state on the button
        const button = event.currentTarget
        const originalContent = button.innerHTML
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...'
        button.disabled = true
        
        // Make API call to toggle monitoring
        this.toggleTrackMonitorInAPI(trackId, !currentMonitored, trackTitle)
            .then(() => {
                // Refresh the tracks data to update status
                const albumId = this.currentAlbumId || this.albumIdValue
                if (albumId) {
                    this.loadTracksWithAnalysisStatus(albumId)
                }
                this.showAlert('success', `Monitoring ${!currentMonitored ? 'enabled' : 'disabled'} for: ${trackTitle}`)
            })
            .catch((error) => {
                console.error('Monitor toggle error:', error)
                this.showAlert('danger', `Failed to update monitoring for: ${trackTitle}`)
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalContent
                button.disabled = false
            })
    }

    async toggleTrackMonitorInAPI(trackId, monitored, trackTitle) {
        try {
            const response = await fetch(`/track/${trackId}/toggle-monitor`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    trackId: trackId,
                    monitored: monitored
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const result = await response.json()
            return result
        } catch (error) {
            console.error('API monitor toggle error:', error)
            throw error
        }
    }

    showTrackFileInfo(event) {
        const trackId = event.currentTarget.dataset.trackId
        const trackTitle = event.currentTarget.dataset.trackTitle
        const track = this.findTrackById(trackId)
        
        if (!track || !track.files || track.files.length === 0) {
            this.showAlert('info', 'No file information available for this track')
            return
        }

        this.showFileInfoModal(track)
    }

    findTrackById(trackId) {
        if (!this.currentTracks) return null
        return this.currentTracks.find(track => track.id == trackId) || null
    }

    showFileInfoModal(track) {
        const modalHtml = `
            <div class="modal fade" 
                 id="fileInfoModal" 
                 tabindex="-1" 
                 aria-labelledby="fileInfoModalLabel" 
                 aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="fileInfoModalLabel">
                                <i class="fas fa-file-audio me-2"></i>
                                File Information: ${track.title}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>File</th>
                                            <th>Size</th>
                                            <th>Quality</th>
                                            <th>Format</th>
                                            <th>Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${track.files.map(file => `
                                            <tr>
                                                <td>
                                                    <code class="text-break">${file.filePath}</code>
                                                </td>
                                                <td>${this.formatFileSize(file.fileSize)}</td>
                                                <td>${file.quality || 'Unknown'}</td>
                                                <td>${file.format || 'Unknown'}</td>
                                                <td>${this.formatDuration(file.duration)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <strong>Summary:</strong>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-file me-2"></i>Total Files: ${track.fileCount}</li>
                                    <li><i class="fas fa-hdd me-2"></i>Total Size: ${this.formatFileSize(track.totalFileSize)}</li>
                                    <li><i class="fas fa-clock me-2"></i>Total Duration: ${this.formatDuration(track.duration)}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `
        
        // Remove existing modal if it exists
        const existingModal = document.getElementById('fileInfoModal')
        if (existingModal) {
            existingModal.remove()
        }
        
        // Append new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml)
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('fileInfoModal'))
        modal.show()
    }

    formatDuration(seconds) {
        if (!seconds || seconds < 0) return '0:00'
        
        const minutes = Math.floor(seconds / 60)
        const remainingSeconds = seconds % 60
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
    }

    formatFileSize(bytes) {
        if (!bytes || bytes < 0) return '0 B'
        
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
        const i = Math.floor(Math.log(bytes) / Math.log(1024))
        const size = bytes / Math.pow(1024, i)
        
        return `${size.toFixed(1)} ${sizes[i]}`
    }

    getStatusBadgeClass(status) {
        switch (status) {
            case 'downloaded': return 'success'
            case 'missing': return 'warning'
            case 'unmonitored': return 'secondary'
            case 'downloading': return 'info'
            case 'error': return 'danger'
            default: return 'secondary'
        }
    }

    calculateQualityScore(quality) {
        if (!quality) return 0
        
        // Simple quality scoring based on common audio formats and bitrates
        const qualityLower = quality.toLowerCase()
        
        if (qualityLower.includes('flac')) return 1000
        if (qualityLower.includes('alac')) return 900
        if (qualityLower.includes('320')) return 800
        if (qualityLower.includes('256')) return 700
        if (qualityLower.includes('192')) return 600
        if (qualityLower.includes('160')) return 500
        if (qualityLower.includes('128')) return 400
        if (qualityLower.includes('mp3')) return 300
        if (qualityLower.includes('aac')) return 250
        
        return 100 // Default score for unknown formats
    }





    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div')
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `

        document.body.appendChild(alertDiv)

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 5000)
    }

    // Reload tracks data from the backend
    async reloadTracksData() {
        try {
            const albumId = this.currentAlbumId || this.albumIdValue
            console.log('Reloading tracks data for album:', albumId)
            
            // Fetch fresh tracks data from the backend
            const response = await fetch(`/album/${albumId}/tracks`)
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }
            
            const tracksData = await response.json()
            console.log('Fresh tracks data received:', tracksData)
            
            // Update the tracksValue - extract tracks array from response
            if (tracksData.tracks && Array.isArray(tracksData.tracks)) {
                this.tracksValue = tracksData.tracks
            } else if (Array.isArray(tracksData)) {
                this.tracksValue = tracksData
            } else {
                console.error('Unexpected tracks data format:', tracksData)
                this.tracksValue = []
            }
            
            // Also update the currentTracks for compatibility
            this.currentTracks = this.tracksValue
            
            console.log('Tracks data updated, count:', this.tracksValue.length)
            
        } catch (error) {
            console.error('Error reloading tracks data:', error)
            this.showAlert('danger', 'Erreur lors du rechargement des pistes')
        }
    }
}
