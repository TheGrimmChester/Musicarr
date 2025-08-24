import { Controller } from "@hotwired/stimulus"

/**
 * Library index management Stimulus controller
 * Handles the library index page functionality
 */
export default class extends Controller {
    static targets = ['container', 'loadingIndicator', 'endOfDataIndicator', 'form', 'pathInput']

    connect() {
        console.log("Library index controller connected")
        this.loadLibraries()
        this.initFolderTree()
        this.listenForLibraryEvents()
    }

    loadLibraries() {
        console.log('Loading libraries...')
        
        // Show loading indicator if available
        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = 'block'
        }

        // Clear container
        if (this.hasContainerTarget) {
            this.containerTarget.innerHTML = ''
        }

        fetch('/library/list')
            .then(response => response.json())
            .then(libraries => {
                console.log('Libraries loaded:', libraries)
                this.displayLibraries(libraries)
            })
            .catch(error => {
                console.error('Error loading libraries:', error)
                this.showAlert('Error loading libraries', 'danger')
            })
            .finally(() => {
                if (this.hasLoadingIndicatorTarget) {
                    this.loadingIndicatorTarget.style.display = 'none'
                }
            })
    }

    displayLibraries(libraries) {
        if (!this.hasContainerTarget) return

        if (libraries.length === 0) {
            this.containerTarget.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        No libraries found. Create your first library to get started.
                    </div>
                </div>
            `
            return
        }

        const librariesHtml = libraries.map(library => this.createLibraryCard(library)).join('')
        this.containerTarget.innerHTML = librariesHtml
    }

    createLibraryCard(library) {
        const stats = library.stats || {}
        const lastScan = library.lastScan ? new Date(library.lastScan).toLocaleDateString() : 'Never'
        
        return `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-database me-2"></i>${library.name}
                        </h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   ${library.enabled ? 'checked' : ''} 
                                   data-action="change->library-index#toggleLibrary"
                                   data-library-id="${library.id}">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Path:</strong><br>
                            <code class="small">${library.path}</code>
                        </div>
                        
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="h6 mb-0">${stats.artists || 0}</div>
                                <small class="text-muted">Artists</small>
                            </div>
                            <div class="col-4">
                                <div class="h6 mb-0">${stats.albums || 0}</div>
                                <small class="text-muted">Albums</small>
                            </div>
                            <div class="col-4">
                                <div class="h6 mb-0">${stats.tracks || 0}</div>
                                <small class="text-muted">Tracks</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>Last scan: ${lastScan}
                            </small>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="/library/${library.id}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>View Details
                            </a>
                            <button class="btn btn-outline-success btn-sm" 
                                    data-action="click->library-index#scanLibrary"
                                    data-library-id="${library.id}">
                                <i class="fas fa-sync me-1"></i>Scan Library
                            </button>
                            <button class="btn btn-outline-danger btn-sm" 
                                    data-action="click->library-index#deleteLibrary"
                                    data-library-id="${library.id}"
                                    data-library-name="${library.name}">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `
    }

    toggleLibrary(event) {
        const libraryId = event.target.dataset.libraryId
        const enabled = event.target.checked

        fetch(`/library/${libraryId}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ enabled: enabled })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(`Library ${enabled ? 'enabled' : 'disabled'} successfully`, 'success')
                // Refresh libraries to show updated state
                setTimeout(() => this.loadLibraries(), 1000)
            } else {
                this.showAlert(data.error || 'Error updating library', 'danger')
                // Revert checkbox state
                event.target.checked = !enabled
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error updating library', 'danger')
            // Revert checkbox state
            event.target.checked = !enabled
        })
    }

    scanLibrary(event) {
        const libraryId = event.target.dataset.libraryId
        const button = event.target
        const originalText = button.innerHTML
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Scanning...'
        button.disabled = true

        fetch(`/library/${libraryId}/scan`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Library scan started successfully', 'success')
                // Refresh libraries after scan
                setTimeout(() => this.loadLibraries(), 2000)
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

    deleteLibrary(event) {
        const libraryId = event.target.dataset.libraryId
        const libraryName = event.target.dataset.libraryName
        
        if (!confirm(`Are you sure you want to delete the library "${libraryName}"? This action cannot be undone.`)) {
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
                this.showAlert(`Library "${libraryName}" deleted successfully`, 'success')
                // Refresh libraries after deletion
                setTimeout(() => this.loadLibraries(), 1000)
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

    submitForm(event) {
        event.preventDefault()
        
        // Get the selected path from the folder tree controller
        let selectedPath = ''
        const folderTreeContainer = this.element.querySelector('[data-controller="folder-tree"]')
        if (folderTreeContainer) {
            const folderTreeController = this.application.getControllerForElementAndIdentifier(
                folderTreeContainer,
                'folder-tree'
            )
            if (folderTreeController) {
                selectedPath = folderTreeController.selectedPathValue
            }
        }

        if (!selectedPath) {
            this.showAlert('Please select a folder path first', 'warning')
            return
        }
        
        const formData = new FormData(event.target)
        const data = {
            name: formData.get('name'),
            path: selectedPath,
            enabled: formData.get('enabled') === 'on',
            scanAutomatically: true,
            scanInterval: 60
        }

        fetch('/library/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Library created successfully', 'success')
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addLibraryModal'))
                if (modal) {
                    modal.hide()
                }
                // Refresh libraries
                setTimeout(() => this.loadLibraries(), 1000)
            } else {
                this.showAlert(data.error || 'Error creating library', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error creating library', 'danger')
        })
    }

    showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `
        this.element.insertAdjacentHTML('afterbegin', alertHtml)
    }

    initFolderTree() {
        // Set up modal event handlers for folder tree
        const addLibraryModal = document.getElementById('addLibraryModal')
        if (addLibraryModal) {
            // Initialize folder tree when modal is shown
            addLibraryModal.addEventListener('shown.bs.modal', () => {
                this.initializeAddFolderTree()
            })

            // Clean up when modal is hidden
            addLibraryModal.addEventListener('hidden.bs.modal', () => {
                this.cleanupAddFolderTree()
            })
        }

        // Listen for folder tree selection events
        this.element.addEventListener('folder-tree:select', (event) => {
            console.log('Folder tree select event received:', event.detail)
            // Update the hidden path input using the target
            if (this.hasPathInputTarget) {
                this.pathInputTarget.value = event.detail.path
                console.log('Path input updated to:', event.detail.path)
            }
        })

        // Listen for folder tree confirm events (for compatibility)
        this.element.addEventListener('folder-tree:confirm', (event) => {
            console.log('Folder tree confirm event received:', event.detail)
            // Update the hidden path input using the target
            if (this.hasPathInputTarget) {
                this.pathInputTarget.value = event.detail.path
                console.log('Path input updated to:', event.detail.path)
            }
        })
    }

    initializeAddFolderTree() {
        const folderTreeContainer = this.element.querySelector('[data-folder-tree-target="folderList"]')
        if (folderTreeContainer) {
            // The folder tree controller should already be connected via data-controller="folder-tree"
            // Just ensure it starts at the root
            const folderTreeController = this.application.getControllerForElementAndIdentifier(
                folderTreeContainer.closest('[data-controller="folder-tree"]'),
                'folder-tree'
            )
            if (folderTreeController) {
                folderTreeController.loadRoots()
            }
        }
    }

    cleanupAddFolderTree() {
        // Cleanup if needed
        const folderTreeContainer = this.element.querySelector('[data-folder-tree-target="folderList"]')
        if (folderTreeContainer) {
            const folderTreeController = this.application.getControllerForElementAndIdentifier(
                folderTreeContainer.closest('[data-controller="folder-tree"]'),
                'folder-tree'
            )
            if (folderTreeController) {
                // Reset the folder tree to initial state
                folderTreeController.selectedPathValue = ''
                if (folderTreeController.hasSelectedPathTarget) {
                    folderTreeController.selectedPathTarget.textContent = 'No path selected'
                }
            }
        }
    }

    // Listen for library creation events from shared modal
    listenForLibraryEvents() {
        this.element.addEventListener('libraryCreated', (event) => {
            console.log('Library created event received:', event.detail)
            // Refresh libraries after creation
            setTimeout(() => this.loadLibraries(), 1000)
        })
    }
}
