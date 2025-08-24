import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'minimumScoreThreshold', 'currentThresholdDisplay', 'thresholdQuality',
        'requireExactArtistMatch', 'requireExactAlbumMatch', 'requireExactDurationMatch', 
        'requireExactYearMatch', 'requireExactTitleMatch', 'autoAssociationEnabled',
        'totalAssociations', 'successfulAssociations', 'rejectedLowScore', 'unmatchedTracks',
        'lastUpdated', 'progressBar'
    ]

    connect() {
        // Controller is ready
    }

    updateThresholdDisplay() {
        const value = parseFloat(this.minimumScoreThresholdTarget.value)
        this.currentThresholdDisplayTarget.textContent = value.toFixed(1)
        
        // Update quality badge
        let quality, className
        if (value >= 90) {
            quality = 'Excellent'
            className = 'bg-success'
        } else if (value >= 70) {
            quality = 'Good'
            className = 'bg-warning'
        } else if (value >= 50) {
            quality = 'Fair'
            className = 'bg-orange'
        } else {
            quality = 'Poor'
            className = 'bg-danger'
        }
        
        this.thresholdQualityTarget.textContent = quality
        this.thresholdQualityTarget.className = `badge ${className}`
    }

    saveConfig(event) {
        // Prevent default form submission
        event.preventDefault()
        
        const config = {
            min_score: parseFloat(this.minimumScoreThresholdTarget.value),
            exact_artist_match: this.requireExactArtistMatchTarget.checked,
            exact_album_match: this.requireExactAlbumMatchTarget.checked,
            exact_duration_match: this.requireExactDurationMatchTarget.checked,
            exact_year_match: this.requireExactYearMatchTarget.checked,
            exact_title_match: this.requireExactTitleMatchTarget.checked,
            auto_association: this.autoAssociationEnabledTarget.checked
        }

        fetch('/association-config/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ association_config: config })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Configuration saved successfully', 'success')
            } else {
                this.showAlert(data.error || 'Error saving configuration', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error: ' + error.message, 'danger')
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

    resetConfig() {
        if (confirm('Are you sure you want to reset the configuration to default values?')) {
            fetch('/association-config/reset', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showAlert('Configuration reset successfully', 'success')
                    // Reload the page after a short delay to show the reset values
                    setTimeout(() => {
                        location.reload()
                    }, 1500)
                } else {
                    this.showAlert('Error: ' + data.error, 'danger')
                }
            })
            .catch(error => {
                console.error('Error:', error)
                this.showAlert('Error resetting configuration', 'danger')
            })
        }
    }

    testThreshold() {
        const threshold = parseFloat(this.minimumScoreThresholdTarget.value)
        
        fetch('/association-config/test-threshold', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ min_score: threshold })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(data.message, 'success')
            } else {
                this.showAlert('Error: ' + data.error, 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error testing threshold', 'danger')
        })
    }

    refreshStatistics() {
        const btn = event.currentTarget
        const originalText = btn.innerHTML
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...'
        btn.disabled = true

        fetch('/association-config/statistics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update statistics display
                this.totalAssociationsTarget.textContent = data.data.total_associations
                this.successfulAssociationsTarget.textContent = data.data.successful_associations
                this.rejectedLowScoreTarget.textContent = data.data.rejected_low_score
                this.unmatchedTracksTarget.textContent = data.data.unmatched_tracks_count

                // Update association rate if available
                if (data.data.association_rate !== undefined) {
                    const progressBar = this.progressBarTarget
                    if (progressBar) {
                        progressBar.style.width = data.data.association_rate + '%'
                        progressBar.textContent = data.data.association_rate.toFixed(1) + '%'
                        progressBar.setAttribute('aria-valuenow', data.data.association_rate)
                    }
                }

                // Update last updated time
                this.lastUpdatedTarget.textContent = new Date().toLocaleString()
                
                this.showAlert('Statistics refreshed successfully', 'success')
            } else {
                this.showAlert('Error: ' + data.error, 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error refreshing statistics', 'danger')
        })
        .finally(() => {
            btn.innerHTML = originalText
            btn.disabled = false
        })
    }
}
