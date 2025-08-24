import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'matchesContainer', 'targetTrackInfo', 'confirmMoveBtn', 'findMatchesBtn'
    ]

    static values = {
        fileId: Number
    }

    connect() {
        console.log('Track File Reassociate controller connected')
        this.targetTrackId = null
        this.targetTrackInfo = null
    }

    findMatches() {
        const container = this.matchesContainerTarget
        container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching for matches...</div>'
        
        fetch(`/track-file/file/${this.fileIdValue}/find-matches`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayMatches(data.matches)
                } else {
                    container.innerHTML = '<div class="alert alert-danger">Error finding matches</div>'
                }
            })
            .catch(error => {
                console.error('Error:', error)
                container.innerHTML = '<div class="alert alert-danger">Error finding matches</div>'
            })
    }

    displayMatches(matches) {
        const container = this.matchesContainerTarget
        
        if (matches.length === 0) {
            container.innerHTML = '<div class="text-center text-muted">No matches found</div>'
            return
        }

        let html = '<div class="list-group">'
        matches.forEach(match => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${match.title}</h6>
                        <small class="text-muted">${match.album} - Track ${match.track_number}</small>
                        <br><small class="text-info">Score: ${Math.round(match.score)}% - ${match.reason}</small>
                    </div>
                    <button class="btn btn-sm btn-primary move-file-btn" 
                            data-track-id="${match.id}"
                            data-track-title="${match.title}"
                            data-album-title="${match.album}"
                            data-action="click->track-file-reassociate#showMoveConfirmation">
                        <i class="fas fa-arrow-right"></i> Move Here
                    </button>
                </div>
            `
        })
        html += '</div>'
        
        container.innerHTML = html
    }

    showMoveConfirmation(event) {
        const button = event.currentTarget
        this.targetTrackId = button.dataset.trackId
        this.targetTrackInfo = `${button.dataset.trackTitle} (${button.dataset.albumTitle})`
        
        if (this.hasTargetTrackInfoTarget) {
            this.targetTrackInfoTarget.textContent = this.targetTrackInfo
        }
        
        // Show the confirmation modal
        const modal = new bootstrap.Modal(document.getElementById('confirmMoveModal'))
        modal.show()
    }

    confirmMove() {
        if (!this.targetTrackId) return
        
        fetch(`/track-file/file/${this.fileIdValue}/move-to-track/${this.targetTrackId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('File moved successfully', 'success')
                setTimeout(() => {
                    window.location.href = data.redirect_url || '/track-file'
                }, 1000)
            } else {
                this.showAlert('Error: ' + (data.error || 'Unknown error'), 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error moving file', 'danger')
        })
    }

    showAlert(message, type = 'info') {
        // Create a temporary alert
        const alertDiv = document.createElement('div')
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `
        document.body.appendChild(alertDiv)
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 5000)
    }
}
