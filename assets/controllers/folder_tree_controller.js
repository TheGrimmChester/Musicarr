import { Controller } from "@hotwired/stimulus"

/**
 * Folder Tree Selector Stimulus controller
 * Replaces the legacy FolderTreeSelector class and inline JavaScript
 */
export default class extends Controller {
    static targets = [
        "breadcrumb",
        "folderList",
        "selectedPath"
    ]

    static values = {
        initialPath: { type: String, default: '/' },
        currentPath: { type: String, default: '/' },
        selectedPath: String
    }

    connect() {
        this.loadRoots()
    }

    // Load root directories
    async loadRoots() {
        if (!this.hasFolderListTarget) return

        this.showLoading()

        try {
            const response = await fetch('/library/browse-directories?listRoots=true')
            const data = await response.json()

            if (data.success) {
                this.updateBreadcrumbForRoots()
                this.renderRoots(data.roots)
                this.currentPathValue = ''
            } else {
                this.showError(`Error loading root directories: ${data.error}`)
            }
        } catch (error) {
            console.error('Error loading root directories:', error)
            this.showError('Error loading root directories')
        }
    }

    // Load directories for a specific path
    async loadDirectories(path) {
        if (!this.hasFolderListTarget) return

        this.showLoading()

        try {
            const params = new URLSearchParams({ path })
            const response = await fetch(`/library/browse-directories?${params}`)
            const data = await response.json()

            if (data.success) {
                this.currentPathValue = path
                this.updateBreadcrumb(path)
                this.renderDirectories(data.directories)
            } else {
                this.showError(`Error loading directories: ${data.error}`)
            }
        } catch (error) {
            console.error('Error loading directories:', error)
            this.showError('Error loading directories')
        }
    }

    // Render root directories
    renderRoots(roots) {
        const html = roots.map(root => `
            <div class="folder-item d-flex justify-content-between align-items-center p-2 border-bottom" 
                 data-path="${this.escapeHtml(root.path)}">
                <div class="d-flex align-items-center">
                    <i class="fas fa-hdd text-primary me-2"></i>
                    <span>${this.escapeHtml(root.name)} (${this.escapeHtml(root.path)})</span>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary folder-expand" 
                            data-action="click->folder-tree#expandFolder"
                            data-path="${this.escapeHtml(root.path)}">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        `).join('')

        this.folderListTarget.innerHTML = html
    }

    // Render directories
    renderDirectories(directories) {
        if (directories.length === 0) {
            this.folderListTarget.innerHTML = `
                <div class="text-center text-muted p-3">
                    <i class="fas fa-folder-open"></i>
                    <p class="mb-0">No subdirectories found</p>
                </div>
            `
            return
        }

        const html = directories.map(dir => `
            <div class="folder-item d-flex justify-content-between align-items-center p-2 border-bottom" 
                 data-path="${this.escapeHtml(dir.path)}">
                <div class="d-flex align-items-center">
                    <i class="fas fa-folder text-warning me-2"></i>
                    <span>${this.escapeHtml(dir.name)}</span>
                </div>
                <div>
                    ${dir.hasChildren ? `
                        <button class="btn btn-sm btn-outline-primary folder-expand me-1" 
                                data-action="click->folder-tree#expandFolder"
                                data-path="${this.escapeHtml(dir.path)}">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-success folder-select" 
                            data-action="click->folder-tree#selectFolder"
                            data-path="${this.escapeHtml(dir.path)}">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            </div>
        `).join('')

        this.folderListTarget.innerHTML = html
    }

    // Update breadcrumb for roots
    updateBreadcrumbForRoots() {
        if (!this.hasBreadcrumbTarget) return

        this.breadcrumbTarget.innerHTML = `
            <li class="breadcrumb-item active">
                <i class="fas fa-server me-1"></i>Root Directories
            </li>
        `
    }

    // Update breadcrumb for path
    updateBreadcrumb(path) {
        if (!this.hasBreadcrumbTarget) return

        const parts = path.split('/').filter(part => part.length > 0)
        let currentPath = ''

        let html = `
            <li class="breadcrumb-item">
                <a href="#" data-action="click->folder-tree#loadRoots">
                    <i class="fas fa-server me-1"></i>Roots
                </a>
            </li>
        `

        parts.forEach((part, index) => {
            currentPath += '/' + part
            const isLast = index === parts.length - 1

            if (isLast) {
                html += `
                    <li class="breadcrumb-item active">
                        <i class="fas fa-folder me-1"></i>${this.escapeHtml(part)}
                    </li>
                `
            } else {
                html += `
                    <li class="breadcrumb-item">
                        <a href="#" data-action="click->folder-tree#navigateToPath" 
                           data-path="${this.escapeHtml(currentPath)}">
                            <i class="fas fa-folder me-1"></i>${this.escapeHtml(part)}
                        </a>
                    </li>
                `
            }
        })

        this.breadcrumbTarget.innerHTML = html
    }

    // Event handlers
    expandFolder(event) {
        event.preventDefault()
        const path = event.currentTarget.dataset.path
        this.loadDirectories(path)
    }

    selectFolder(event) {
        event.preventDefault()
        const path = event.currentTarget.dataset.path
        
        // Update selected path
        this.selectedPathValue = path
        
        // Update UI to show selection
        this.updateSelectedPathDisplay(path)
        
        // Dispatch selection event for parent controllers to handle
        this.dispatch('select', { detail: { path } })
    }

    navigateToPath(event) {
        event.preventDefault()
        const path = event.currentTarget.dataset.path
        this.loadDirectories(path)
    }

    // Select current path (for the button in the template)
    selectCurrentPath() {
        if (this.currentPathValue) {
            this.selectFolder({ 
                preventDefault: () => {}, 
                currentTarget: { dataset: { path: this.currentPathValue } }
            })
        }
    }

    // Update selected path display
    updateSelectedPathDisplay(path) {
        if (this.hasSelectedPathTarget) {
            this.selectedPathTarget.textContent = path
        }

        // Highlight selected folder
        document.querySelectorAll('.folder-item').forEach(item => {
            item.classList.remove('bg-light')
        })

        const selectedItem = document.querySelector(`[data-path="${path}"]`)
        if (selectedItem) {
            selectedItem.classList.add('bg-light')
        }
    }

    // Go up one level
    goUp() {
        if (this.currentPathValue) {
            const parentPath = this.currentPathValue.split('/').slice(0, -1).join('/')
            if (parentPath) {
                this.loadDirectories(parentPath)
            } else {
                this.loadRoots()
            }
        }
    }

    // Set path (for compatibility with existing code)
    setPath(path) {
        if (path && path !== this.currentPathValue) {
            if (path === '/' || path === '') {
                this.loadRoots()
            } else {
                this.loadDirectories(path)
            }
        }
    }

    // Confirm selection (for compatibility with existing code)
    confirmSelection() {
        if (this.selectedPathValue) {
            this.dispatch('confirm', { detail: { path: this.selectedPathValue } })
        }
    }

    // Public methods for external use
    getPath() {
        return this.selectedPathValue || this.currentPathValue
    }

    // Cleanup method for external use
    destroy() {
        this.selectedPathValue = ''
        this.currentPathValue = this.initialPathValue
    }

    // Utility methods
    showLoading() {
        if (this.hasFolderListTarget) {
            this.folderListTarget.innerHTML = `
                <div class="text-center p-3">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0 mt-2">Loading directories...</p>
                </div>
            `
        }
    }

    showError(message) {
        if (this.hasFolderListTarget) {
            this.folderListTarget.innerHTML = `
                <div class="alert alert-danger">
                    ${this.escapeHtml(message)}
                </div>
            `
        }
    }

    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }
}
