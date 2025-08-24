import { Controller } from "@hotwired/stimulus"

/**
 * Artist show management Stimulus controller
 * Replaces all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = ['editFolderModal', 'artistIdInput', 'folderPathInput', 'moveMetadataCheckbox', 'updateFolderBtn']
    static values = {
        artistId: Number,
        artistMbid: String,
        artistName: String,
        statusFilter: String
    }

    connect() {
        console.log("Artist show controller connected")
        console.log("Artist ID:", this.artistIdValue)
        console.log("Artist MBID:", this.artistMbidValue)
        console.log("Artist Name:", this.artistNameValue)
    }

    // Artist management methods
    syncArtist(event) {
        const artistId = event.currentTarget.dataset.artistId || this.artistIdValue
        if (!artistId) {
            this.showAlert('No artist ID provided', 'error')
            return
        }

        const button = event.currentTarget
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...'
        button.disabled = true

        fetch(`/artist/${artistId}/sync`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Artist sync started successfully', 'success')
                // Refresh page after a delay to show updated data
                setTimeout(() => window.location.reload(), 2000)
            } else {
                this.showAlert(data.error || 'Error starting artist sync', 'error')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error starting artist sync', 'error')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    refreshArtistImage(event) {
        const artistId = event.currentTarget.dataset.artistId || this.artistIdValue
        if (!artistId) {
            this.showAlert('No artist ID provided', 'error')
            return
        }

        const button = event.currentTarget
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...'
        button.disabled = true

        fetch(`/artist/${artistId}/refresh-image`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Artist image refreshed successfully', 'success')
                // Refresh page after a delay to show updated image
                setTimeout(() => window.location.reload(), 2000)
            } else {
                this.showAlert(data.error || 'Error refreshing artist image', 'error')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error refreshing artist image', 'error')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    deleteArtist(event) {
        const artistId = event.currentTarget.dataset.artistId || this.artistIdValue
        const artistName = event.currentTarget.dataset.artistName || this.artistNameValue
        
        if (!artistId) {
            this.showAlert('No artist ID provided', 'error')
            return
        }

        if (!confirm(`Are you sure you want to delete the artist "${artistName}"?\n\nThis action will also delete all associated albums and tracks.`)) {
            return
        }

        const button = event.currentTarget
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...'
        button.disabled = true

        fetch(`/artist/${artistId}/delete`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Artist deleted successfully', 'success')
                // Redirect to artists list after a delay
                setTimeout(() => window.location.href = '/artist', 2000)
            } else {
                this.showAlert(data.error || 'Error deleting artist', 'error')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error deleting artist', 'error')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    clearArtistCache(event) {
        const artistMbid = event.currentTarget.dataset.artistMbid || this.artistMbidValue
        const artistName = event.currentTarget.dataset.artistName || this.artistNameValue
        
        if (!artistMbid) {
            this.showAlert('No artist MBID provided', 'error')
            return
        }

        if (!confirm(`Are you sure you want to clear the cache for "${artistName}"?`)) {
            return
        }

        const button = event.currentTarget
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Clearing...'
        button.disabled = true

        fetch(`/artist/${this.artistIdValue}/clear-cache`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Artist cache cleared successfully', 'success')
                // Refresh page after a delay to show updated data
                setTimeout(() => window.location.reload(), 2000)
            } else {
                this.showAlert(data.error || 'Error clearing artist cache', 'error')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error clearing artist cache', 'error')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    goBack() {
        window.history.back()
    }

    // Album management methods
    deleteAlbum(event) {
        const albumId = event.currentTarget.dataset.albumId
        const albumTitle = event.currentTarget.dataset.albumTitle
        
        if (!albumId) {
            this.showAlert('No album ID provided', 'error')
            return
        }

        if (!confirm(`Are you sure you want to delete the album "${albumTitle}"?\n\nThis action will also delete all associated tracks.`)) {
            return
        }

        const button = event.currentTarget
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...'
        button.disabled = true

        fetch(`/album/${albumId}/delete`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Album deleted successfully', 'success')
                // Refresh page after a delay to show updated data
                setTimeout(() => window.location.reload(), 2000)
            } else {
                this.showAlert(data.error || 'Error deleting album', 'error')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error deleting album', 'error')
        })
        .finally(() => {
            button.innerHTML = originalText
            button.disabled = false
        })
    }

    // Filter management methods
    clearStatusFilters() {
        // Reset status filter to show all albums
        window.location.href = window.location.pathname
    }

    // Folder management methods
    editArtistFolder() {
        if (this.hasEditFolderModalTarget) {
            // Populate the modal with current values
            if (this.hasArtistIdInputTarget) {
                this.artistIdInputTarget.value = this.artistIdValue
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(this.editFolderModalTarget)
            modal.show()
        }
    }

    browseFolderInput() {
        // Create a file input for folder selection
        const input = document.createElement('input')
        input.type = 'file'
        input.webkitdirectory = true
        input.directory = true
        
        input.addEventListener('change', (event) => {
            if (event.target.files.length > 0) {
                const folderPath = event.target.files[0].path.replace(/\/[^\/]*$/, '')
                if (this.hasFolderPathInputTarget) {
                    this.folderPathInputTarget.value = folderPath
                }
            }
        })
        
        input.click()
    }

    updateArtistFolder() {
        if (!this.hasFolderPathInputTarget || !this.hasArtistIdInputTarget) {
            this.showAlert('Required form elements not found', 'error')
            return
        }

        const folderPath = this.folderPathInputTarget.value
        const artistId = this.artistIdInputTarget.value
        const moveMetadata = this.hasMoveMetadataCheckboxTarget ? this.moveMetadataCheckboxTarget.checked : false

        if (!folderPath) {
            this.showAlert('Please select a folder path', 'error')
            return
        }

        if (this.hasUpdateFolderBtnTarget) {
            this.updateFolderBtnTarget.disabled = true
            this.updateFolderBtnTarget.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...'
        }

        fetch(`/artist/${artistId}/update-folder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                folder_path: folderPath,
                move_metadata: moveMetadata
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Artist folder updated successfully', 'success')
                // Close modal and refresh page
                const modal = bootstrap.Modal.getInstance(this.editFolderModalTarget)
                if (modal) {
                    modal.hide()
                }
                setTimeout(() => window.location.reload(), 2000)
            } else {
                this.showAlert(data.error || 'Error updating artist folder', 'error')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error updating artist folder', 'error')
        })
        .finally(() => {
            if (this.hasUpdateFolderBtnTarget) {
                this.updateFolderBtnTarget.disabled = false
                this.updateFolderBtnTarget.innerHTML = 'Update Folder'
            }
        })
    }

    // Utility methods
    showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div')
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`
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
}

