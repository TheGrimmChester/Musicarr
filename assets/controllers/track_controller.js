import { Controller } from "@hotwired/stimulus"

/**
 * Track management Stimulus controller
 * Replaces all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = [
        "renameModal",
        "patternSelect",
        "preview",
        "renameBtn"
    ]

    static values = {
        trackId: Number
    }

    connect() {
        console.log("Track controller connected")
        this.trackPatterns = []
        this.loadTrackPatterns()
    }

    // Load track patterns
    async loadTrackPatterns() {
        try {
            const response = await fetch('/file-naming-patterns/list')
            const patterns = await response.json()
            
            this.trackPatterns = patterns
            this.populatePatternSelect()
        } catch (error) {
            console.error('Error loading track patterns:', error)
        }
    }

    // Populate pattern select
    populatePatternSelect() {
        if (!this.hasPatternSelectTarget) return

        this.patternSelectTarget.innerHTML = '<option value="">Select pattern</option>'

        this.trackPatterns.forEach(pattern => {
            const option = document.createElement('option')
            option.value = pattern.id
            option.textContent = `${pattern.name} - ${pattern.pattern}`
            this.patternSelectTarget.appendChild(option)
        })

        // Select first pattern automatically
        if (this.trackPatterns.length > 0) {
            this.patternSelectTarget.selectedIndex = 1
            this.generateTrackPreview()
        }
    }

    // Open rename modal
    openRenameModal() {
        const modal = new bootstrap.Modal(this.renameModalTarget)
        modal.show()

        // Generate preview automatically
        setTimeout(() => {
            if (this.trackPatterns.length > 0) {
                this.generateTrackPreview()
            }
        }, 100)
    }

    // Generate track preview
    async generateTrackPreview() {
        if (!this.hasPatternSelectTarget || !this.hasPreviewTarget || !this.hasRenameBtnTarget) return

        const patternId = this.patternSelectTarget.value

        if (!patternId) {
            this.previewTarget.innerHTML = '<span class="text-muted"><i class="fas fa-eye"></i> Preview</span>'
            this.renameBtnTarget.disabled = true
            return
        }

        const formData = new FormData()
        formData.append('pattern_id', patternId)
        formData.append('track_ids[]', this.trackIdValue)

        try {
            const response = await fetch('/file-renaming/preview', {
                method: 'POST',
                body: formData
            })

            const data = await response.json()

            if (data.success && data.previews.length > 0) {
                const preview = data.previews[0]
                this.previewTarget.innerHTML = `<code>${preview.new_full_path}</code>`
                this.renameBtnTarget.disabled = false
            } else {
                this.previewTarget.innerHTML = '<span class="text-muted">No change needed</span>'
                this.renameBtnTarget.disabled = true
            }
        } catch (error) {
            console.error('Error generating preview:', error)
            this.previewTarget.innerHTML = '<span class="text-danger">Error</span>'
        }
    }

    // Rename file
    async renameFile() {
        if (!this.hasPatternSelectTarget) return

        const patternId = this.patternSelectTarget.value

        if (!patternId) {
            this.showAlert('Please select a pattern', 'warning')
            return
        }

        if (!confirm('Are you sure you want to rename this file?')) return

        const formData = new FormData()
        formData.append('pattern_id', patternId)
        formData.append('track_ids[]', this.trackIdValue)

        try {
            const response = await fetch('/file-renaming/rename', {
                method: 'POST',
                body: formData
            })

            const data = await response.json()

            if (data.success) {
                this.showAlert(`Success: ${data.message}`, 'success')
                if (data.errors && data.errors.length > 0) {
                    this.showAlert('Errors:\n' + data.errors.join('\n'), 'warning')
                }
                
                const modal = bootstrap.Modal.getInstance(this.renameModalTarget)
                if (modal) {
                    modal.hide()
                }
                
                location.reload()
            } else {
                this.showAlert('Error: ' + data.error, 'danger')
            }
        } catch (error) {
            console.error('Error renaming file:', error)
            this.showAlert('Error renaming file', 'danger')
        }
    }

    // Analyze quality
    async analyzeQuality(event) {
        const button = event.currentTarget
        const trackId = button.dataset.trackId
        const originalText = button.innerHTML

        // Disable button and show loading indicator
        button.disabled = true
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Analysis in progress...'

        try {
            const response = await fetch(`/audio-quality/analyze/${trackId}`, {
                method: 'POST'
            })

            const data = await response.json()

            if (data.success) {
                this.showAlert(`Quality analyzed successfully: ${data.quality.quality_string}`, 'success')
                // Reload page to show new quality
                location.reload()
            } else {
                this.showAlert('Error during analysis: ' + data.error, 'danger')
            }
        } catch (error) {
            console.error('Error analyzing quality:', error)
            this.showAlert('Error during quality analysis', 'danger')
        } finally {
            // Restore button
            button.disabled = false
            button.innerHTML = originalText
        }
    }

    // Delete file
    async deleteFile(event) {
        const fileId = event.currentTarget.dataset.fileId

        if (!confirm('Are you sure you want to delete this file?')) return

        try {
            const response = await fetch(`/track-file/file/${fileId}/delete`, {
                method: 'DELETE'
            })

            const data = await response.json()

            if (data.success) {
                event.currentTarget.closest('.border').remove()
            } else {
                this.showAlert('Error deleting file', 'danger')
            }
        } catch (error) {
            console.error('Error deleting file:', error)
            this.showAlert('Error deleting file', 'danger')
        }
    }

    // Show alert
    showAlert(message, type = 'info') {
        if (window.MusicarrApp && window.MusicarrApp.showAlert) {
            window.MusicarrApp.showAlert(message, type)
        } else {
            const alertDiv = document.createElement('div')
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `

            document.body.insertBefore(alertDiv, document.body.firstChild)

            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove()
                }
            }, 5000)
        }
    }
}
