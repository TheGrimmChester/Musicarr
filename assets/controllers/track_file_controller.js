import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['deleteFile']

    connect() {
        console.log('Track File controller connected')
    }

    deleteFile(event) {
        const fileId = event.currentTarget.dataset.fileId
        
        if (confirm('Are you sure you want to delete this file?')) {
            fetch(`/track-file/file/${fileId}/delete`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    event.currentTarget.closest('tr').remove()
                    this.showAlert('File deleted successfully', 'success')
                } else {
                    this.showAlert('Error: ' + data.error, 'danger')
                }
            })
            .catch(error => {
                console.error('Error:', error)
                this.showAlert('Error deleting file', 'danger')
            })
        }
    }

    showAlert(message, type = 'info') {
        // Create a temporary alert
        const alertDiv = document.createElement('div')
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `
        document.body.appendChild(alertDiv)
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 5000)
    }
}
