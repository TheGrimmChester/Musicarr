import { Controller } from "@hotwired/stimulus"

/**
 * Album management Stimulus controller
 * Replaces the legacy AlbumManager class and all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = ["container", "tracksContainer"]

    static values = {
        albumId: Number
    }

    connect() {
        console.log("Album controller connected")
    }

    // Album monitoring toggle
    async toggleMonitor(event) {
        const albumId = event.currentTarget.dataset.albumId
        const monitored = event.currentTarget.checked
        const originalState = !monitored

        try {
            const response = await fetch(`/album/${albumId}/toggle-monitor`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ monitored })
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess(`Album ${monitored ? 'monitoring enabled' : 'monitoring disabled'}`)
                
                // Update UI to reflect the change
                this.updateMonitoringUI(albumId, monitored)
            } else {
                this.showError('Error updating album monitoring status')
                // Revert the toggle
                event.currentTarget.checked = originalState
            }
        } catch (error) {
            console.error('Error toggling album monitor:', error)
            this.showError('Error updating album monitoring status')
            // Revert the toggle
            event.currentTarget.checked = originalState
        }
    }

    updateMonitoringUI(albumId, monitored) {
        // Update any monitoring indicators in the UI
        const monitoringBadges = document.querySelectorAll(`[data-album-id="${albumId}"] .monitoring-badge`)
        monitoringBadges.forEach(badge => {
            if (monitored) {
                badge.className = 'badge bg-success monitoring-badge'
                badge.textContent = 'Monitored'
            } else {
                badge.className = 'badge bg-secondary monitoring-badge'
                badge.textContent = 'Not Monitored'
            }
        })
    }

    // Album search functionality
    async searchAlbum(event) {
        const albumId = event.currentTarget.dataset.albumId
        const button = event.currentTarget
        const originalText = this.showLoading(button)

        try {
            const response = await fetch(`/album/${albumId}/search`)
            const result = await response.json()

            if (result.success) {
                this.showSuccess('Album search completed')
                // Optionally update the album display with new information
                if (result.album) {
                    this.updateAlbumDisplay(result.album)
                }
            } else {
                this.showError(result.error || 'Error searching for album')
            }
        } catch (error) {
            console.error('Error searching album:', error)
            this.showError('Error searching for album')
        } finally {
            this.hideLoading(button, originalText)
        }
    }

    // Load and display album tracks
    async loadTracks(event) {
        const albumId = event.currentTarget.dataset.albumId
        const button = event.currentTarget
        const originalText = this.showLoading(button)

        try {
            const response = await fetch(`/album/${albumId}/tracks`)
            const result = await response.json()

            if (result.success && result.tracks) {
                this.displayTracks(result.tracks)
                this.showSuccess('Tracks loaded successfully')
            } else {
                this.showError(result.error || 'Error loading tracks')
            }
        } catch (error) {
            console.error('Error loading tracks:', error)
            this.showError('Error loading tracks')
        } finally {
            this.hideLoading(button, originalText)
        }
    }

    displayTracks(tracks) {
        if (!this.hasTracksContainerTarget) return

        if (tracks.length === 0) {
            this.tracksContainerTarget.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No tracks found for this album.
                </div>
            `
            return
        }

        const tracksHtml = tracks.map(track => `
            <div class="track-item d-flex justify-content-between align-items-center p-2 border-bottom">
                <div class="d-flex align-items-center">
                    <span class="track-number me-3">${track.trackNumber || '?'}</span>
                    <div>
                        <div class="track-title">${this.escapeHtml(track.title)}</div>
                        <small class="text-muted">${this.formatDuration(track.duration)}</small>
                    </div>
                </div>
                <div class="track-actions">
                    <button class="btn btn-sm btn-outline-primary me-1" 
                            data-action="click->album#downloadTrack"
                            data-track-id="${track.id}">
                        <i class="fas fa-download me-1"></i>Download
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" 
                            data-action="click->album#playTrack"
                            data-track-id="${track.id}">
                        <i class="fas fa-play me-1"></i>Play
                    </button>
                </div>
            </div>
        `).join('')

        this.tracksContainerTarget.innerHTML = tracksHtml
    }

    // Track actions
    async downloadTrack(event) {
        const trackId = event.currentTarget.dataset.trackId
        const button = event.currentTarget
        const originalText = this.showLoading(button)

        try {
            const response = await fetch(`/track/${trackId}/download`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess('Track download started')
                // Update download status in UI if needed
                this.updateTrackDownloadStatus(trackId, 'downloading')
            } else {
                this.showError(result.error || 'Error starting download')
            }
        } catch (error) {
            console.error('Error downloading track:', error)
            this.showError('Error downloading track')
        } finally {
            this.hideLoading(button, originalText)
        }
    }

    async playTrack(event) {
        const trackId = event.currentTarget.dataset.trackId
        const button = event.currentTarget
        const originalText = this.showLoading(button)

        try {
            const response = await fetch(`/track/${trackId}/play`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess('Track playback started')
                // Update play status in UI if needed
                this.updateTrackPlayStatus(trackId, 'playing')
            } else {
                this.showError(result.error || 'Error playing track')
            }
        } catch (error) {
            console.error('Error playing track:', error)
            this.showError('Error playing track')
        } finally {
            this.hideLoading(button, originalText)
        }
    }

    updateTrackDownloadStatus(trackId, status) {
        const trackItem = document.querySelector(`[data-track-id="${trackId}"]`)
        if (trackItem) {
            const downloadBtn = trackItem.querySelector('.btn-outline-primary')
            if (downloadBtn) {
                if (status === 'downloading') {
                    downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Downloading...'
                    downloadBtn.disabled = true
                } else if (status === 'downloaded') {
                    downloadBtn.innerHTML = '<i class="fas fa-check me-1"></i>Downloaded'
                    downloadBtn.className = 'btn btn-sm btn-success me-1'
                    downloadBtn.disabled = true
                }
            }
        }
    }

    updateTrackPlayStatus(trackId, status) {
        const trackItem = document.querySelector(`[data-track-id="${trackId}"]`)
        if (trackItem) {
            const playBtn = trackItem.querySelector('.btn-outline-secondary')
            if (playBtn) {
                if (status === 'playing') {
                    playBtn.innerHTML = '<i class="fas fa-pause me-1"></i>Playing'
                    playBtn.className = 'btn btn-sm btn-warning'
                } else if (status === 'stopped') {
                    playBtn.innerHTML = '<i class="fas fa-play me-1"></i>Play'
                    playBtn.className = 'btn btn-sm btn-outline-secondary'
                }
            }
        }
    }

    // Album actions
    async refreshAlbum(event) {
        const albumId = event.currentTarget.dataset.albumId
        const button = event.currentTarget
        const originalText = this.showLoading(button)

        try {
            const response = await fetch(`/album/${albumId}/refresh`, {
                method: 'POST'
            })

            const result = await response.json()

            if (result.success) {
                this.showSuccess('Album refreshed successfully')
                // Reload the page or update the album display
                if (result.album) {
                    this.updateAlbumDisplay(result.album)
                }
            } else {
                this.showError(result.error || 'Error refreshing album')
            }
        } catch (error) {
            console.error('Error refreshing album:', error)
            this.showError('Error refreshing album')
        } finally {
            this.hideLoading(button, originalText)
        }
    }

    updateAlbumDisplay(album) {
        // Update album information in the UI
        console.log('Updating album display:', album)
        // Implementation depends on the specific UI structure
    }

    // Utility methods
    showLoading(button) {
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'
        button.disabled = true
        return originalText
    }

    hideLoading(button, originalText) {
        button.innerHTML = originalText
        button.disabled = false
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

    showError(message) {
        this.showAlert(message, 'danger')
    }

    showSuccess(message) {
        this.showAlert(message, 'success')
    }

    showInfo(message) {
        this.showAlert(message, 'info')
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
