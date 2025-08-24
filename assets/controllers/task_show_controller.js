import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'statusBadge', 'duration', 'durationDisplay', 'autoRefreshBadge',
        'toggleAutoRefresh', 'cancelModal'
    ]

    static values = {
        taskId: Number,
        taskStatus: String,
        isActive: Boolean
    }

    connect() {
        console.log('Task Show controller connected')
        this.autoRefreshInterval = null
        
        // Initialize auto-refresh for running tasks
        if (this.taskStatusValue === 'running') {
            this.toggleAutoRefresh()
        }
    }

    disconnect() {
        // Clean up auto-refresh interval
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval)
            this.autoRefreshInterval = null
        }
    }

    refreshTask() {
        if (this.hasAutoRefreshBadgeTarget) {
            this.autoRefreshBadgeTarget.style.display = 'block'
        }

        fetch(`/tasks/${this.taskIdValue}/ajax-status`)
            .then(response => response.json())
            .then(data => {
                this.updateStatusBadge(data.status)
                this.updateDuration(data.duration)

                // If task is completed, reload page to show all updates
                if (data.isFinalized && this.isActiveValue) {
                    setTimeout(() => {
                        window.location.reload()
                    }, 1000)
                }
            })
            .catch(error => {
                console.error('Error refreshing task:', error)
            })
            .finally(() => {
                if (this.hasAutoRefreshBadgeTarget) {
                    this.autoRefreshBadgeTarget.style.display = 'none'
                }
            })
    }

    updateStatusBadge(status) {
        if (!this.hasStatusBadgeTarget) return

        const statusClasses = {
            'pending': 'bg-warning',
            'running': 'bg-info',
            'completed': 'bg-success',
            'failed': 'bg-danger',
            'cancelled': 'bg-secondary'
        }

        this.statusBadgeTarget.className = `badge task-status-large ${statusClasses[status] || 'bg-secondary'}`

        if (status === 'running') {
            this.statusBadgeTarget.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + status.charAt(0).toUpperCase() + status.slice(1)
        } else {
            this.statusBadgeTarget.textContent = status.charAt(0).toUpperCase() + status.slice(1)
        }
    }

    updateDuration(duration) {
        if (duration && this.hasDurationTarget) {
            this.durationTarget.innerHTML = `<span class="badge bg-light text-dark">${duration}s</span>`
        }

        if (this.hasDurationDisplayTarget) {
            this.durationDisplayTarget.textContent = duration ? `${duration}s` : '-'
        }
    }

    cancelTask() {
        if (this.hasCancelModalTarget) {
            const modal = new bootstrap.Modal(this.cancelModalTarget)
            modal.show()
        }
    }

    retryTask() {
        if (confirm('Are you sure you want to retry this task?')) {
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = `/tasks/${this.taskIdValue}/retry`

            document.body.appendChild(form)
            form.submit()
        }
    }

    toggleAutoRefresh() {
        if (!this.hasToggleAutoRefreshTarget) return

        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval)
            this.autoRefreshInterval = null
            this.toggleAutoRefreshTarget.innerHTML = '<i class="fas fa-play me-1"></i>Auto Refresh'
            this.toggleAutoRefreshTarget.className = 'btn btn-outline-info'
        } else {
            this.autoRefreshInterval = setInterval(() => {
                this.refreshTask()
            }, 5000)
            this.toggleAutoRefreshTarget.innerHTML = '<i class="fas fa-stop me-1"></i>Stop Auto Refresh'
            this.toggleAutoRefreshTarget.className = 'btn btn-outline-danger'
        }
    }
}
