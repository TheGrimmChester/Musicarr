import { Controller } from "@hotwired/stimulus"

/**
 * Home page Stimulus controller
 * Replaces all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = [
        "artistName",
        "artistLibrary"
    ]

    static values = {
        libraries: Array
    }

    connect() {
        console.log("Home controller connected")
        this.loadLibraries()
        this.initializeModals()
        this.listenForLibraryEvents()
        this.listenForArtistEvents()
    }

    initializeModals() {
        // Modal initialization handled by Bootstrap
    }

    // Library scanning
    scanAllLibraries(event) {
        const testMode = event.currentTarget.dataset.homeTestParam === 'true'
        const forced = event.currentTarget.dataset.homeForcedParam === 'true'
        this.showAlert('Feature in development', 'info')
    }

    // Artist synchronization
    async syncAllArtists() {
        const button = this.element.querySelector('[data-action*="syncAllArtists"]')
        if (!button) return

        const originalText = button.innerHTML
        button.innerHTML = '<span class="loading"></span> Processing...'
        button.disabled = true

        try {
            const response = await fetch('/artist/sync-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({})
            })

            const data = await response.json()

            if (data.success) {
                this.showAlert('Synchronization started', 'success')
            } else {
                this.showAlert(data.error || 'Error starting synchronization', 'danger')
            }
        } catch (error) {
            console.error('Error syncing artists:', error)
            this.showAlert('Error starting synchronization', 'danger')
        } finally {
            button.innerHTML = originalText
            button.disabled = false
        }
    }

    // Load libraries for artist form
    async loadLibraries() {
        try {
            const response = await fetch('/library/list')
            const libraries = await response.json()

            // Clear existing options except the first one
            const select = this.artistLibraryTarget
            const firstOption = select.querySelector('option:first-child')
            select.innerHTML = ''
            if (firstOption) {
                select.appendChild(firstOption)
            }

            libraries.forEach(library => {
                const option = document.createElement('option')
                option.value = library.id
                option.textContent = library.name
                select.appendChild(option)
            })
        } catch (error) {
            console.error('Error loading libraries:', error)
        }
    }

    // Utility functions
    showAlert(message, type) {
        const alert = document.createElement('div')
        alert.className = `alert alert-${type} alert-dismissible fade show`
        alert.role = 'alert'
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `

        const container = this.element.querySelector('.container-fluid') || this.element
        container.insertBefore(alert, container.firstChild)
    }

    // Navigation functions
    navigateToLibrary() {
        window.location.href = '/library'
    }

    navigateToArtist() {
        window.location.href = '/artist'
    }



    // Listen for library creation events from shared modal
    listenForLibraryEvents() {
        this.element.addEventListener('libraryCreated', (event) => {
            console.log('Library created event received:', event.detail)
            // Refresh page to show new library
            setTimeout(() => location.reload(), 1000)
        })
    }

    // Listen for artist creation events from shared modal
    listenForArtistEvents() {
        this.element.addEventListener('artistCreated', (event) => {
            console.log('Artist created event received:', event.detail)
            // Refresh page to show new artist
            setTimeout(() => location.reload(), 1000)
        })
    }
}
