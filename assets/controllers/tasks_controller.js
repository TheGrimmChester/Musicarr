import { Controller } from "@hotwired/stimulus"

/**
 * Tasks management Stimulus controller
 * Replaces all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = [
        "autoRefreshIndicator",
        "statPending",
        "statRunning", 
        "statCompleted",
        "statFailed",
        "statCancelled",
        "toggleAutoRefreshBtn",
        "refreshCountdown",
        "selectAllCheckbox",
        "taskCheckbox"
    ]

    static values = {
        statisticsUrl: String,
        refreshInterval: { type: Number, default: 5000 }
    }

    connect() {
        console.log("Tasks controller connected")
        this.autoRefreshInterval = null
        this.refreshCountdown = null
        this.countdownValue = 5
        this.initializeAutoRefresh()
        this.startDefaultStatisticsRefresh()
    }

    disconnect() {
        this.stopAutoRefresh()
        this.stopDefaultStatisticsRefresh()
    }

    // Statistics refresh
    async refreshStatistics() {
        try {
            const response = await fetch(this.statisticsUrlValue || '/tasks/ajax-statistics')
            const data = await response.json()
            
            this.statPendingTarget.textContent = data.pending
            this.statRunningTarget.textContent = data.running
            this.statCompletedTarget.textContent = data.completed
            this.statFailedTarget.textContent = data.failed
            this.statCancelledTarget.textContent = data.cancelled
        } catch (error) {
            console.error('Error refreshing statistics:', error)
        }
    }

    // Auto-refresh functionality
    startAutoRefresh() {
        this.countdownValue = 5
        this.autoRefreshInterval = setInterval(() => {
            this.autoRefreshIndicatorTarget.style.display = 'block'
            this.refreshStatistics()
            // Optionally refresh the task list too
            window.location.reload()
        }, this.refreshIntervalValue)

        this.refreshCountdown = setInterval(() => {
            this.countdownValue--
            this.refreshCountdownTarget.textContent = this.countdownValue + 's'
            if (this.countdownValue <= 0) {
                this.countdownValue = 5
            }
        }, 1000)

        this.toggleAutoRefreshBtnTarget.innerHTML = '<i class="fas fa-stop me-1"></i>Stop'
        this.toggleAutoRefreshBtnTarget.className = 'btn btn-outline-danger'
        this.refreshCountdownTarget.textContent = this.countdownValue + 's'
        this.saveAutoRefreshState(true)
    }

    stopAutoRefresh() {
        clearInterval(this.autoRefreshInterval)
        clearInterval(this.refreshCountdown)
        this.autoRefreshInterval = null
        this.refreshCountdown = null

        this.toggleAutoRefreshBtnTarget.innerHTML = '<i class="fas fa-play me-1"></i>Start'
        this.toggleAutoRefreshBtnTarget.className = 'btn btn-outline-success'
        this.autoRefreshIndicatorTarget.style.display = 'none'
        this.refreshCountdownTarget.textContent = ''
        this.saveAutoRefreshState(false)
    }

    toggleAutoRefresh() {
        if (this.autoRefreshInterval) {
            this.stopAutoRefresh()
        } else {
            this.startAutoRefresh()
        }
    }

    // Checkbox management
    selectAll() {
        this.taskCheckboxTargets.forEach(checkbox => {
            checkbox.checked = true
        })
        this.selectAllCheckboxTarget.checked = true
    }

    selectNone() {
        this.taskCheckboxTargets.forEach(checkbox => {
            checkbox.checked = false
        })
        this.selectAllCheckboxTarget.checked = false
    }

    toggleAllTasks() {
        this.taskCheckboxTargets.forEach(checkbox => {
            checkbox.checked = this.selectAllCheckboxTarget.checked
        })
    }

    // Bulk actions
    confirmBulkAction(event) {
        const selectedTasks = this.taskCheckboxTargets.filter(checkbox => checkbox.checked).length
        if (selectedTasks === 0) {
            event.preventDefault()
            alert('Please select at least one task.')
            return false
        }
        return confirm(`Are you sure you want to perform this action on ${selectedTasks} task(s)?`)
    }

    // Task operations
    cancelTask(event) {
        const taskId = event.currentTarget.dataset.taskId
        if (confirm('Are you sure you want to cancel this task?')) {
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = `/tasks/${taskId}/cancel`

            const reasonInput = document.createElement('input')
            reasonInput.type = 'hidden'
            reasonInput.name = 'reason'
            reasonInput.value = 'Cancelled by user'
            form.appendChild(reasonInput)

            document.body.appendChild(form)
            form.submit()
        }
    }

    retryTask(event) {
        const taskId = event.currentTarget.dataset.taskId
        if (confirm('Are you sure you want to retry this task?')) {
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = `/tasks/${taskId}/retry`

            document.body.appendChild(form)
            form.submit()
        }
    }

    // Cleanup confirmation
    confirmCleanup(event) {
        if (!confirm('Are you sure you want to delete old tasks?')) {
            event.preventDefault()
            return false
        }
    }

    // Utility functions
    loadAutoRefreshState() {
        const savedState = localStorage.getItem('taskAutoRefresh')
        return savedState === 'true'
    }

    saveAutoRefreshState(isEnabled) {
        localStorage.setItem('taskAutoRefresh', isEnabled.toString())
    }

    initializeAutoRefresh() {
        if (this.loadAutoRefreshState()) {
            this.startAutoRefresh()
        }
    }

    startDefaultStatisticsRefresh() {
        // Auto-refresh statistics every 5 seconds by default (only if auto-refresh is not running)
        this.defaultStatisticsInterval = setInterval(() => {
            if (!this.autoRefreshInterval) {
                this.refreshStatistics()
            }
        }, 5000)
    }

    stopDefaultStatisticsRefresh() {
        if (this.defaultStatisticsInterval) {
            clearInterval(this.defaultStatisticsInterval)
        }
    }
}
