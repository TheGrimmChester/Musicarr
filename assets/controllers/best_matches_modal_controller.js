import { Controller } from "@hotwired/stimulus"

/**
 * Best matches modal Stimulus controller
 * Handles displaying and managing best track matches
 */
export default class extends Controller {
    static targets = [
        "matchesContainer"
    ]

    connect() {
        console.log("Best matches modal controller connected")
    }

    // Display best matches
    displayBestMatches(matches, unmatchedTrack) {
        if (!this.hasMatchesContainerTarget) return

        if (matches.length === 0) {
            this.matchesContainerTarget.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-search fa-2x mb-2"></i>
                    <p>No matches found</p>
                </div>
            `
            return
        }

        let html = `
            <div class="mb-3">
                <h6>Unmatched Track Information</h6>
                <div class="alert alert-info">
                    <strong>Title:</strong> ${unmatchedTrack.title}<br>
                    <strong>Artist:</strong> ${unmatchedTrack.artist}<br>
                    ${unmatchedTrack.album ? `<strong>Album:</strong> ${unmatchedTrack.album}<br>` : ''}
                </div>
            </div>
            <h6>Potential Matches</h6>
            <div class="list-group">
        `

        matches.forEach(match => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${match.title}</h6>
                        <small class="text-muted">${match.album} - Track ${match.track_number}</small>
                        <br><small class="text-info">Score: ${Math.round(match.score)}% - ${match.reason}</small>
                    </div>
                    <button class="btn btn-sm btn-primary associate-track-btn"
                            data-track-id="${match.id}"
                            data-track-title="${match.title}"
                            data-album-title="${match.album}">
                        <i class="fas fa-link"></i> Associate
                    </button>
                </div>
            `
        })

        html += '</div>'
        this.matchesContainerTarget.innerHTML = html

        // Add event listeners to associate buttons
        this.matchesContainerTarget.querySelectorAll('.associate-track-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const matchTrackId = event.currentTarget.dataset.trackId
                const trackTitle = event.currentTarget.dataset.trackTitle
                const albumTitle = event.currentTarget.dataset.albumTitle

                if (confirm(`Are you sure you want to associate this track?\n\n${unmatchedTrack.title} → ${trackTitle} (${albumTitle})`)) {
                    this.associateToTrack(unmatchedTrack.id, matchTrackId)
                }
            })
        })
    }

    // Associate unmatched track to existing track
    async associateToTrack(unmatchedTrackId, trackId) {
        try {
            const response = await fetch(`/unmatched-tracks/${unmatchedTrackId}/associate-to-track/${trackId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })

            const data = await response.json()

            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('bestMatchesModal'))
                if (modal) {
                    modal.hide()
                }

                // Show success message
                this.showAlert(data.message, 'success')

                // Remove the row from the table
                const row = document.querySelector(`tr[data-track-id="${unmatchedTrackId}"]`)
                if (row) {
                    row.remove()
                }

                // Reload page after a delay to update stats
                setTimeout(() => {
                    location.reload()
                }, 2000)
            } else {
                this.showAlert('Error: ' + data.error, 'danger')
            }
        } catch (error) {
            console.error('Error associating track:', error)
            this.showAlert('Error associating track', 'danger')
        }
    }

    // Show alert
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
            const container = this.element.querySelector('.modal-body') || this.element
            container.insertBefore(document.createRange().createContextualFragment(alertHtml), container.firstChild)
        }
    }
}
