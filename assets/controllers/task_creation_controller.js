import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'entityCard', 'optionsCard', 'previewCard', 'infoCard',
        'selectedType', 'typeError', 'entityMbid', 'entityId', 'entityName',
        'priority', 'previewType', 'previewEntity', 'previewPriority', 'previewUniqueKey',
        'taskTypeInfo', 'submitBtn'
    ]

    static values = {
        selectedTaskType: String
    }

    connect() {
        console.log('Task Creation controller connected')
        this.selectedTaskType = null
        
        // Initialize tooltips
        this.initializeTooltips()
        
        // Set up event listeners
        this.setupEventListeners()
    }

    setupEventListeners() {
        // Add input event listeners for form validation
        if (this.hasEntityMbidTarget) {
            this.entityMbidTarget.addEventListener('input', () => {
                this.updatePreview()
                this.validateForm()
            })
        }

        if (this.hasEntityIdTarget) {
            this.entityIdTarget.addEventListener('input', () => {
                this.updatePreview()
                this.validateForm()
            })
        }

        if (this.hasEntityNameTarget) {
            this.entityNameTarget.addEventListener('input', () => {
                this.updatePreview()
                this.validateForm()
            })
        }

        if (this.hasPriorityTarget) {
            this.priorityTarget.addEventListener('change', () => {
                this.updatePreview()
            })
        }
    }

    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }

    selectTaskType(event) {
        const taskType = event.currentTarget.dataset.taskType
        this.selectedTaskType = taskType

        // Update visual selection
        document.querySelectorAll('.task-type-card').forEach(card => {
            card.classList.remove('selected')
        })
        event.currentTarget.classList.add('selected')

        // Update hidden input
        if (this.hasSelectedTypeTarget) {
            this.selectedTypeTarget.value = taskType
        }

        // Show entity and options cards
        this.showCards()

        // Update task info
        this.updateTaskInfo(taskType)

        // Update preview
        this.updatePreview()

        // Enable submit button
        this.validateForm()
    }

    showCards() {
        if (this.hasEntityCardTarget) {
            this.entityCardTarget.style.display = 'block'
        }
        if (this.hasOptionsCardTarget) {
            this.optionsCardTarget.style.display = 'block'
        }
        if (this.hasPreviewCardTarget) {
            this.previewCardTarget.style.display = 'block'
        }
        if (this.hasInfoCardTarget) {
            this.infoCardTarget.style.display = 'block'
        }
    }

    updateTaskInfo(taskType) {
        if (!this.hasTaskTypeInfoTarget) return

        const taskTypeInfo = {
            'add_artist': {
                title: 'Add Artist',
                description: 'Creates a new artist in the database using MusicBrainz data.',
                requiredFields: ['entity_mbid or entity_name'],
                examples: 'MBID: f54ba100-0562-4368-9728-a2c9c46cc6b7<br>Name: Radiohead'
            },
            'add_album': {
                title: 'Add Album',
                description: 'Creates a new album/release in the database using MusicBrainz data.',
                requiredFields: ['entity_mbid or entity_name'],
                examples: 'MBID: 1f038a3c-43a4-4f96-9f98-9e2eb9e2a96d<br>Name: OK Computer'
            },
            'update_artist': {
                title: 'Update Artist',
                description: 'Updates existing artist information with fresh data from MusicBrainz.',
                requiredFields: ['entity_mbid or entity_id'],
                examples: 'MBID: f54ba100-0562-4368-9728-a2c9c46cc6b7<br>ID: 123'
            },
            'update_album': {
                title: 'Update Album',
                description: 'Updates existing album information with fresh data from MusicBrainz.',
                requiredFields: ['entity_mbid or entity_id'],
                examples: 'MBID: 1f038a3c-43a4-4f96-9f98-9e2eb9e2a96d<br>ID: 456'
            },
            'sync_artist': {
                title: 'Sync Artist',
                description: 'Synchronizes artist data with external sources and updates related information.',
                requiredFields: ['entity_mbid or entity_id'],
                examples: 'MBID: f54ba100-0562-4368-9728-a2c9c46cc6b7<br>ID: 123'
            },
            'sync_album': {
                title: 'Sync Album',
                description: 'Synchronizes album data with external sources and updates related information.',
                requiredFields: ['entity_mbid or entity_id'],
                examples: 'MBID: 1f038a3c-43a4-4f96-9f98-9e2eb9e2a96d<br>ID: 456'
            },
            'download_album': {
                title: 'Download Album',
                description: 'Downloads all tracks for an album from configured sources.',
                requiredFields: ['entity_mbid or entity_id'],
                examples: 'MBID: 1f038a3c-43a4-4f96-9f98-9e2eb9e2a96d<br>ID: 456'
            },
            'download_song': {
                title: 'Download Song',
                description: 'Downloads a specific song/track from configured sources.',
                requiredFields: ['entity_mbid or entity_id'],
                examples: 'MBID: track-mbid-here<br>ID: 789'
            }
        }

        const info = taskTypeInfo[taskType]
        if (info) {
            this.taskTypeInfoTarget.innerHTML = `
                <h6>${info.title}</h6>
                <p class="small">${info.description}</p>
                <h6 class="mt-3">Required Fields:</h6>
                <ul class="small">
                    <li>${info.requiredFields.join('</li><li>')}</li>
                </ul>
                <h6 class="mt-3">Examples:</h6>
                <div class="small">${info.examples}</div>
            `
        }
    }

    updatePreview() {
        if (!this.selectedTaskType) return

        const entityMbid = this.hasEntityMbidTarget ? this.entityMbidTarget.value : ''
        const entityId = this.hasEntityIdTarget ? this.entityIdTarget.value : ''
        const entityName = this.hasEntityNameTarget ? this.entityNameTarget.value : ''
        const priority = this.hasPriorityTarget ? this.priorityTarget.value : ''

        // Update preview fields
        if (this.hasPreviewTypeTarget) {
            this.previewTypeTarget.textContent = this.selectedTaskType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
        }

        let entityPreview = ''
        if (entityName) entityPreview += entityName
        if (entityMbid) entityPreview += (entityPreview ? ' (' : '') + 'MBID: ' + entityMbid.substring(0, 8) + '...' + (entityPreview ? ')' : '')
        if (entityId) entityPreview += (entityPreview ? ' (' : '') + 'ID: ' + entityId + (entityPreview ? ')' : '')
        
        if (this.hasPreviewEntityTarget) {
            this.previewEntityTarget.textContent = entityPreview || 'Not specified'
        }

        if (this.hasPreviewPriorityTarget) {
            this.previewPriorityTarget.textContent = priority || '0'
        }

        // Generate unique key preview
        let uniqueKey = this.selectedTaskType
        if (entityMbid) {
            uniqueKey += ':' + entityMbid
        } else if (entityId) {
            uniqueKey += ':id:' + entityId
        } else if (entityName) {
            uniqueKey += ':name:' + entityName
        }
        
        if (this.hasPreviewUniqueKeyTarget) {
            this.previewUniqueKeyTarget.textContent = uniqueKey
        }
    }

    validateForm() {
        const hasTaskType = this.selectedTaskType !== null
        const hasEntity = (this.hasEntityMbidTarget && this.entityMbidTarget.value) ||
                         (this.hasEntityIdTarget && this.entityIdTarget.value) ||
                         (this.hasEntityNameTarget && this.entityNameTarget.value)

        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = !(hasTaskType && hasEntity)
        }

        // Show/hide error messages
        if (this.hasTypeErrorTarget) {
            this.typeErrorTarget.style.display = hasTaskType ? 'none' : 'block'
        }
    }

    submitForm(event) {
        if (!this.selectedTaskType) {
            event.preventDefault()
            alert('Please select a task type.')
            return false
        }

        const hasEntity = (this.hasEntityMbidTarget && this.entityMbidTarget.value) ||
                         (this.hasEntityIdTarget && this.entityIdTarget.value) ||
                         (this.hasEntityNameTarget && this.entityNameTarget.value)

        if (!hasEntity) {
            event.preventDefault()
            alert('Please provide at least one entity identifier (MBID, ID, or Name).')
            return false
        }

        return true
    }
}
