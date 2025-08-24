import { Controller } from "@hotwired/stimulus"

/**
 * Artist modal Stimulus controller
 * Handles artist search and addition functionality
 */
export default class extends Controller {
    static targets = [
        "artistSearch",
        "searchResults",
        "artistResults",
        "artistLibrary",
        "selectedArtistInfo",
        "artistInfo",
        "saveArtistBtn"
    ]

    connect() {
        console.log("Artist modal controller connected")
        this.selectedArtist = null
        this.loadLibraries()
        this.setupEventHandlers()
    }

    // Setup event handlers
    setupEventHandlers() {
        // Search functionality
        if (this.hasArtistSearchTarget) {
            this.artistSearchTarget.addEventListener('keypress', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault()
                    this.searchMusicBrainz()
                }
            })
        }
    }

    // Search MusicBrainz for artists
    async searchMusicBrainz() {
        const query = this.artistSearchTarget.value.trim()

        if (!query) {
            this.showAlert('Please enter an artist name', 'warning')
            return
        }

        // Show loading indicator
        this.artistResultsTarget.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>'
        this.searchResultsTarget.style.display = 'block'

        try {
            const response = await fetch('/artist/search-musicbrainz', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ q: query })
            })

            const data = await response.json()

            if (data.success) {
                this.displaySearchResults(data.artists)
            } else {
                this.showAlert(data.error || 'Search error', 'danger')
                this.searchResultsTarget.style.display = 'none'
            }
        } catch (error) {
            console.error('Search failed:', error)
            this.showAlert('Search error', 'danger')
            this.searchResultsTarget.style.display = 'none'
        }
    }

    // Display search results
    displaySearchResults(artists) {
        if (artists.length === 0) {
            this.artistResultsTarget.innerHTML = '<div class="text-center text-muted">No artists found</div>'
            return
        }

        let html = ''
        artists.forEach((artist, index) => {
            const country = artist.country ? `<span class="badge bg-secondary me-2">${artist.country}</span>` : ''
            const type = artist.type ? `<span class="badge bg-info me-2">${artist.type}</span>` : ''
            const disambiguation = artist.disambiguation ? `<small class="text-muted d-block">${artist.disambiguation}</small>` : ''

            html += `
                <div class="list-group-item list-group-item-action artist-item" data-artist='${JSON.stringify(artist)}' data-artist-index="${index}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${artist.name}</h6>
                            ${disambiguation}
                            <div class="mt-1">
                                ${country}
                                ${type}
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </div>
            `
        })

        this.artistResultsTarget.innerHTML = html

        // Set up click handlers for artist items
        this.artistResultsTarget.querySelectorAll('.artist-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.selectArtist(index)
            })
        })
    }

    // Select artist
    selectArtist(index) {
        const artistElement = this.artistResultsTarget.querySelectorAll('.artist-item')[index]
        const artist = JSON.parse(artistElement.dataset.artist)

        if (!artist) return

        // Update selection appearance
        this.artistResultsTarget.querySelectorAll('.artist-item').forEach(item => item.classList.remove('active'))
        artistElement.classList.add('active')

        // Store selected artist
        this.selectedArtist = artist

        // Display artist information
        this.displaySelectedArtist(artist)

        // Enable add button
        this.enableAddButton()
    }

    // Display selected artist information
    displaySelectedArtist(artist) {
        const country = artist.country ? `<strong>Country:</strong> ${artist.country}<br>` : ''
        const type = artist.type ? `<strong>Type:</strong> ${artist.type}<br>` : ''
        const disambiguation = artist.disambiguation ? `<strong>Description:</strong> ${artist.disambiguation}<br>` : ''
        const lifeSpan = artist.life_span ? `<strong>Period:</strong> ${artist.life_span.begin || '?'} - ${artist.life_span.end || '?'}<br>` : ''

        const info = `
            <strong>Name:</strong> ${artist.name}<br>
            <strong>MBID:</strong> ${artist.id}<br>
            ${country}
            ${type}
            ${disambiguation}
            ${lifeSpan}
        `

        this.artistInfoTarget.innerHTML = info
        this.selectedArtistInfoTarget.style.display = 'block'
    }

    // Enable add button
    enableAddButton() {
        if (this.hasSaveArtistBtnTarget) {
            this.saveArtistBtnTarget.disabled = false
        }
    }

    // Add artist
    async addArtist() {
        if (!this.selectedArtist) {
            this.showAlert('Please select an artist', 'warning')
            return
        }

        const libraryId = this.artistLibraryTarget.value
        if (!libraryId) {
            this.showAlert('Please select a library', 'warning')
            return
        }

        const formData = {
            name: this.selectedArtist.name,
            mbid: this.selectedArtist.id,
            libraryId: libraryId
        }

        try {
            const response = await fetch('/artist/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })

            const data = await response.json()

            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addArtistModal'))
                if (modal) {
                    modal.hide()
                }

                // Reset form
                this.resetForm()

                // Show success message
                if (data.task_type === 'artist_add') {
                    this.showAlert('Artist add task created', 'info')
                } else {
                    this.showAlert('Artist added successfully', 'success')
                }

                // Reload page after a delay
                setTimeout(() => {
                    location.reload()
                }, 2000)
            } else {
                this.showAlert(data.error || 'Error occurred', 'danger')
            }
        } catch (error) {
            console.error('Add artist failed:', error)
            this.showAlert('Error adding artist', 'danger')
        }
    }

    // Submit form handler
    submitForm(event) {
        event.preventDefault()
        this.addArtist()
    }

    // Reset form
    resetForm() {
        this.artistSearchTarget.value = ''
        this.searchResultsTarget.style.display = 'none'
        this.selectedArtistInfoTarget.style.display = 'none'
        this.selectedArtist = null
        this.disableAddButton()
    }

    // Disable add button
    disableAddButton() {
        if (this.hasSaveArtistBtnTarget) {
            this.saveArtistBtnTarget.disabled = true
        }
    }

    // Load libraries
    async loadLibraries() {
        try {
            const response = await fetch('/library/list')
            const data = await response.json()

            if (this.hasArtistLibraryTarget) {
                const select = this.artistLibraryTarget
                // Keep the first option (placeholder)
                const firstOption = select.querySelector('option:first-child')
                select.innerHTML = ''
                if (firstOption) select.appendChild(firstOption)

                data.forEach(library => {
                    const option = document.createElement('option')
                    option.value = library.id
                    option.textContent = library.name
                    select.appendChild(option)
                })
            }
        } catch (error) {
            console.error('Error loading libraries:', error)
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
