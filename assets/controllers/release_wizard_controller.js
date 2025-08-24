import { Controller } from "@hotwired/stimulus"

/**
 * Release wizard Stimulus controller
 * Handles the release addition wizard functionality
 */
export default class extends Controller {
    static targets = ['searchInput', 'searchResults', 'selectedRelease', 'releaseInfo', 'confirmBtn']
    static values = {
        artistId: Number,
        artistMbid: String,
        artistName: String,
        artistType: String,
        artistCountry: String
    }

    connect() {
        console.log("Release wizard controller connected")
        console.log("Artist ID:", this.artistIdValue)
        console.log("Artist MBID:", this.artistMbidValue)
        console.log("Artist Name:", this.artistNameValue)
    }

    // Release management methods
    openAddReleaseModal() {
        // Check if we have the required artist information
        if (!this.artistIdValue) {
            this.showAlert('No artist ID provided', 'error')
            return
        }

        if (!this.artistMbidValue) {
            this.showAlert('Artist must have a MusicBrainz ID to search for releases', 'error')
            return
        }

        // Create and show the add release modal
        this.createAddReleaseModal()
        
        // Get the modal element by ID since it was dynamically created
        const modalElement = document.getElementById('addReleaseModal')
        if (!modalElement) {
            this.showAlert('Failed to create modal', 'error')
            return
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(modalElement)
        modal.show()
        
        // Load release groups
        this.loadAllReleaseGroups()
    }

    createAddReleaseModal() {
        // Remove existing modal if it exists
        const existingModal = document.getElementById('addReleaseModal')
        if (existingModal) {
            existingModal.remove()
        }

        // Create the modal HTML with the full wizard structure
        const modalHTML = `
            <div class="modal fade" id="addReleaseModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-plus me-2"></i>Add Release Wizard for ${this.artistNameValue}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Step 1: Select Release Group -->
                            <div id="step1" class="wizard-step">
                                <div class="text-center mb-4">
                                    <div class="step-indicator">
                                        <span class="badge bg-primary">1</span>
                                        <span class="ms-2">Select Release Group</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="releaseGroupSearch" class="form-label">Search Release Groups</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="releaseGroupSearch" 
                                               placeholder="Search by title, year, or type..."
                                               data-release-wizard-target="searchInput"
                                               data-action="input->release-wizard#searchReleaseGroups">
                                        <button class="btn btn-outline-secondary" type="button"
                                                data-action="click->release-wizard#searchReleaseGroups">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>

                                <div id="releaseGroupsList" class="release-groups-container">
                                    <!-- Release groups will be loaded here -->
                                </div>

                                <div class="text-center mt-3">
                                    <button class="btn btn-primary" data-action="click->release-wizard#loadAllReleaseGroups">
                                        <i class="fas fa-list me-1"></i>Load All Release Groups
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Select Release -->
                            <div id="step2" class="wizard-step" style="display: none;">
                                <div class="text-center mb-4">
                                    <div class="step-indicator">
                                        <span class="badge bg-secondary">1</span>
                                        <span class="ms-2">Select Release Group</span>
                                        <i class="fas fa-arrow-right mx-2"></i>
                                        <span class="badge bg-primary">2</span>
                                        <span class="ms-2">Select Release</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6 id="selectedReleaseGroupTitle" class="text-primary"></h6>
                                    <button class="btn btn-outline-secondary btn-sm" data-action="click->release-wizard#backToStep1">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Release Groups
                                    </button>
                                </div>

                                <div id="releasesList" class="releases-container">
                                    <!-- Releases will be loaded here -->
                                </div>
                            </div>

                            <!-- Step 3: Confirm and Add -->
                            <div id="step3" class="wizard-step" style="display: none;">
                                <div class="text-center mb-4">
                                    <div class="step-indicator">
                                        <span class="badge bg-secondary">1</span>
                                        <span class="ms-2">Select Release Group</span>
                                        <i class="fas fa-arrow-right mx-2"></i>
                                        <span class="badge bg-secondary">2</span>
                                        <span class="ms-2">Select Release</span>
                                        <i class="fas fa-arrow-right mx-2"></i>
                                        <span class="badge bg-primary">3</span>
                                        <span class="ms-2">Confirm Addition</span>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Release Details</h6>
                                        <div id="releaseDetails" class="release-details">
                                            <!-- Release details will be displayed here -->
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-3">
                                    <button class="btn btn-outline-secondary me-2" data-action="click->release-wizard#backToStep2">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Releases
                                    </button>
                                    <button class="btn btn-success" data-action="click->release-wizard#confirmAddRelease">
                                        <i class="fas fa-plus me-1"></i>Add Release
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `

        // Insert the modal into the DOM
        document.body.insertAdjacentHTML('beforeend', modalHTML)
        
        // Add CSS styles for the wizard
        this.addWizardStyles()
    }

    addWizardStyles() {
        // Check if styles already exist
        if (document.getElementById('release-wizard-styles')) {
            return
        }

        const styles = document.createElement('style')
        styles.id = 'release-wizard-styles'
        styles.textContent = `
            .wizard-step {
                min-height: 400px;
            }
            
            .step-indicator {
                font-size: 1.1em;
                font-weight: 500;
            }
            
            .release-groups-container, .releases-container {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .release-group-item, .release-item {
                border: 1px solid var(--border-color);
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .release-group-item:hover, .release-item:hover {
                border-color: var(--accent-color);
                box-shadow: 0 2px 8px rgba(23, 162, 184, 0.15);
                transform: translateY(-2px);
            }
            
            .release-group-item.selected, .release-item.selected {
                border-color: var(--accent-secondary);
                background-color: rgba(32, 201, 151, 0.05);
            }
            
            .release-group-header, .release-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
            }
            
            .release-group-title, .release-title {
                font-weight: 600;
                color: var(--secondary-color);
                flex: 1;
            }
            
            .release-group-score, .release-id {
                font-size: 0.8em;
                color: var(--text-muted);
                text-align: right;
            }
            
            .release-group-meta, .release-meta {
                font-size: 0.9em;
                color: var(--text-muted);
            }
            
            .release-group-disambiguation {
                font-style: italic;
                color: var(--text-muted);
                border-left: 3px solid var(--border-color);
                padding-left: 10px;
            }
            
            .release-group-details, .release-details {
                font-size: 0.85em;
                color: var(--text-muted);
            }
            
            .release-group-details .row, .release-details .row {
                margin: 0;
            }
            
            .release-group-details .col-6, .release-details .col-6 {
                padding: 2px 8px;
            }
            
            .loading-spinner {
                text-align: center;
                padding: 40px;
                color: var(--text-muted);
            }
            
            .error-message {
                color: var(--danger-color);
                text-align: center;
                padding: 20px;
            }
        `
        document.head.appendChild(styles)
    }

    // Wizard step management
    showStep(stepNumber) {
        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(step => {
            step.style.display = 'none'
        })
        
        // Show the specified step
        const stepElement = document.getElementById(`step${stepNumber}`)
        if (stepElement) {
            stepElement.style.display = 'block'
        }
    }

    backToStep1() {
        this.showStep(1)
    }

    backToStep2() {
        this.showStep(2)
    }

    // Release group management
    async loadAllReleaseGroups() {
        if (!this.artistMbidValue) {
            this.showAlert('No artist MBID available', 'error')
            return
        }

        const container = document.getElementById('releaseGroupsList')
        if (!container) return

        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading release groups...</div>'

        try {
            const response = await fetch(`/artist/${this.artistIdValue}/release-groups`)
            const data = await response.json()

            if (data.success) {
                this.displayReleaseGroups(data.releaseGroups)
                // Store release groups for statistics
                window.currentReleaseGroups = data.releaseGroups
            } else {
                this.showAlert(data.error || 'Failed to load release groups', 'error')
            }
        } catch (error) {
            console.error('Error loading release groups:', error)
            this.showAlert('Error loading release groups', 'error')
        }
    }

    async searchReleaseGroups() {
        const searchTerm = this.searchInputTarget.value.trim()
        if (!searchTerm) {
            this.loadAllReleaseGroups()
            return
        }

        if (!this.artistMbidValue) {
            this.showAlert('No artist MBID available', 'error')
            return
        }

        const container = document.getElementById('releaseGroupsList')
        if (!container) return

        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Searching release groups...</div>'

        try {
            const response = await fetch(`/artist/${this.artistIdValue}/release-groups?search=${encodeURIComponent(searchTerm)}`)
            const data = await response.json()

            if (data.success) {
                if (data.releaseGroups && data.releaseGroups.length > 0) {
                    this.displayReleaseGroups(data.releaseGroups)
                } else {
                    container.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-search fa-2x mb-3"></i>
                            <h6>No release groups found</h6>
                            <p>No release groups match your search term: "${searchTerm}"</p>
                            <button class="btn btn-outline-secondary btn-sm" data-action="click->release-wizard#loadAllReleaseGroups">
                                <i class="fas fa-list me-1"></i>Show All Release Groups
                            </button>
                        </div>
                    `
                }
            } else {
                this.showAlert(data.error || 'Failed to search release groups', 'error')
            }
        } catch (error) {
            console.error('Error searching release groups:', error)
            this.showAlert('Error searching release groups', 'error')
        }
    }

    displayReleaseGroups(releaseGroups) {
        const container = document.getElementById('releaseGroupsList')
        if (!container) return
        
        if (!releaseGroups || releaseGroups.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4">No release groups found</div>'
            return
        }

        let html = ''
        releaseGroups.forEach(group => {
            const year = group['first-release-date'] ? group['first-release-date'].substring(0, 4) : 'Unknown'
            const type = group['primary-type'] || 'Unknown'
            const secondaryTypes = group['secondary-types'] || []
            const disambiguation = group['disambiguation'] || ''
            const score = group['score'] || ''
            
            html += `
                <div class="release-group-item" data-action="click->release-wizard#selectReleaseGroup" data-release-group-id="${group.id}" data-release-group-title="${group.title.replace(/"/g, '&quot;')}">
                    <div class="release-group-header">
                        <div class="release-group-title">${group.title}</div>
                        ${score ? `<div class="release-group-score">Score: ${score}</div>` : ''}
                    </div>
                    <div class="release-group-meta">
                        <span class="badge bg-secondary me-2">${year}</span>
                        <span class="badge bg-info me-2">${type}</span>
                        ${secondaryTypes.map(t => `<span class="badge bg-light text-dark me-1">${t}</span>`).join('')}
                    </div>
                    ${disambiguation ? `<div class="release-group-disambiguation text-muted small mt-2"><i class="fas fa-info-circle me-1"></i>${disambiguation}</div>` : ''}
                    <div class="release-group-details mt-2">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-fingerprint me-1"></i>ID: ${group.id}
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>First Release: ${group['first-release-date'] || 'Unknown'}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `
        })

        container.innerHTML = html
    }

    selectReleaseGroup(event) {
        const releaseGroupId = event.currentTarget.dataset.releaseGroupId
        const releaseGroupTitle = event.currentTarget.dataset.releaseGroupTitle
        
        // Store selected release group
        this.selectedReleaseGroup = { id: releaseGroupId, title: releaseGroupTitle }
        
        // Update UI to show selection
        document.querySelectorAll('.release-group-item').forEach(item => {
            item.classList.remove('selected')
        })
        event.currentTarget.classList.add('selected')
        
        // Move to step 2
        this.showStep(2)
        
        // Update the release group title
        const titleElement = document.getElementById('selectedReleaseGroupTitle')
        if (titleElement) {
            titleElement.textContent = releaseGroupTitle
        }
        
        // Load releases for this release group
        this.loadReleases(releaseGroupId)
    }

    // Release management
    async loadReleases(releaseGroupId) {
        const container = document.getElementById('releasesList')
        if (!container) return
        
        // Show loading state
        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading releases...</div>'

        try {
            const response = await fetch(`/artist/${this.artistIdValue}/releases?releaseGroupId=${releaseGroupId}`)
            const data = await response.json()

            if (data.success) {
                if (data.releases && Array.isArray(data.releases) && data.releases.length > 0) {
                    this.displayReleases(data.releases)
                } else {
                    container.innerHTML = '<div class="text-center text-muted py-4">No releases found for this release group</div>'
                }
            } else {
                container.innerHTML = '<div class="alert alert-danger">Failed to load releases: ' + (data.error || 'Unknown error') + '</div>'
            }
        } catch (error) {
            console.error('Error loading releases:', error)
            container.innerHTML = '<div class="alert alert-danger">Error loading releases</div>'
        }
    }

    displayReleases(releases) {
        const container = document.getElementById('releasesList')
        if (!container) return
        
        if (!releases || releases.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4">No releases found for this release group</div>'
            return
        }

        let html = ''
        releases.forEach(release => {
            const date = release.date ? release.date.substring(0, 4) : 'Unknown'
            const country = release.country || 'Unknown'
            const status = release.status || 'Unknown'
            const packaging = release.packaging || ''
            
            html += `
                <div class="release-item" data-action="click->release-wizard#selectRelease" data-release-id="${release.id}" data-release-title="${release.title.replace(/"/g, '&quot;')}">
                    <div class="release-header">
                        <div class="release-title">${release.title}</div>
                        <div class="release-id small text-muted">ID: ${release.id}</div>
                    </div>
                    <div class="release-meta">
                        <span class="badge bg-secondary me-2">${date}</span>
                        <span class="badge bg-info me-2">${country}</span>
                        <span class="badge bg-warning me-2">${status}</span>
                        ${packaging ? `<span class="badge bg-dark me-2">${packaging}</span>` : ''}
                    </div>
                </div>
            `
        })

        container.innerHTML = html
    }

    selectRelease(event) {
        const releaseId = event.currentTarget.dataset.releaseId
        const releaseTitle = event.currentTarget.dataset.releaseTitle
        
        // Store selected release
        this.selectedRelease = { id: releaseId, title: releaseTitle }
        
        // Update UI to show selection
        document.querySelectorAll('.release-item').forEach(item => {
            item.classList.remove('selected')
        })
        event.currentTarget.classList.add('selected')
        
        // Display release details
        this.displayReleaseDetails()
        
        // Move to step 3
        this.showStep(3)
    }

    displayReleaseDetails() {
        const container = document.getElementById('releaseDetails')
        if (!container || !this.selectedRelease || !this.selectedReleaseGroup) return
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-layer-group me-2"></i>Release Group Information
                    </h6>
                    <div class="mb-2">
                        <strong>Title:</strong> ${this.selectedReleaseGroup.title}<br>
                        <strong>ID:</strong> <code>${this.selectedReleaseGroup.id}</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="text-success mb-3">
                        <i class="fas fa-compact-disc me-2"></i>Release Information
                    </h6>
                    <div class="mb-2">
                        <strong>Title:</strong> ${this.selectedRelease.title}<br>
                        <strong>ID:</strong> <code>${this.selectedRelease.id}</code>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-12">
                    <h6 class="text-info mb-3">
                        <i class="fas fa-user me-2"></i>Artist Information
                    </h6>
                    <div class="mb-2">
                        <strong>Name:</strong> ${this.artistNameValue}<br>
                        <strong>Artist ID:</strong> <code>${this.artistIdValue}</code><br>
                        <strong>MBID:</strong> <code>${this.artistMbidValue}</code>
                    </div>
                </div>
            </div>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> This will create a task to add the selected release as a new album. 
                The album will be processed in the background and will appear in your library once the task is completed.
            </div>
        `
    }

    async confirmAddRelease() {
        if (!this.selectedRelease || !this.selectedReleaseGroup) {
            this.showAlert('No release selected', 'error')
            return
        }

        try {
            const response = await fetch(`/artist/${this.artistIdValue}/add-release`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    releaseId: this.selectedRelease.id,
                    releaseTitle: this.selectedRelease.title,
                    releaseGroupId: this.selectedReleaseGroup.id,
                    releaseGroupTitle: this.selectedReleaseGroup.title
                })
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert('Release addition started successfully', 'success')
                
                // Close modal
                const modalElement = document.getElementById('addReleaseModal')
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement)
                    if (modal) {
                        modal.hide()
                    }
                }
                
                // Reload page to show the new album
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            } else {
                this.showAlert(result.error || 'Failed to start release addition', 'error')
            }
        } catch (error) {
            console.error('Error adding release:', error)
            this.showAlert('Error adding release', 'error')
        }
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
