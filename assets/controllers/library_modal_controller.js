import { Controller } from "@hotwired/stimulus"

/**
 * Shared Library Modal Stimulus controller
 * Handles the library creation modal functionality for both home and library pages
 */
export default class extends Controller {
    static targets = ['form', 'nameInput', 'pathInput', 'enabledToggle']

    connect() {
        console.log("Library modal controller connected")
        this.initFolderTree()
    }

    // Form submission
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

        this.createLibrary(data)
    }

    // Create library
    async createLibrary(data) {
        try {
            const response = await fetch('/library/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })

            const result = await response.json()

            if (result.success) {
                this.showAlert('Library created successfully', 'success')
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addLibraryModal'))
                if (modal) {
                    modal.hide()
                }
                // Dispatch event for parent controllers to handle refresh
                this.dispatch('libraryCreated', { detail: { library: result.library } })
            } else {
                this.showAlert(result.error || 'Error creating library', 'danger')
            }
        } catch (error) {
            console.error('Error creating library:', error)
            this.showAlert('Error creating library', 'danger')
        }
    }

    // Folder tree integration
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
                'folder-tree')
            if (folderTreeController) {
                // Reset the folder tree to initial state
                folderTreeController.selectedPathValue = ''
                if (folderTreeController.hasSelectedPathTarget) {
                    folderTreeController.selectedPathTarget.textContent = 'No path selected'
                }
            }
        }
    }

    // Utility functions
    showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `
        this.element.insertAdjacentHTML('afterbegin', alertHtml)
    }
}
