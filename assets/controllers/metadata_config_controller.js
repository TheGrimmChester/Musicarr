import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['saveBtn', 'baseDir', 'saveInLibrary']

    connect() {
        console.log('Metadata Config controller connected')
    }

    saveConfig() {
        const payload = {
            base_dir: this.baseDirTarget.value,
            save_in_library: this.saveInLibraryTarget.checked
        }

        fetch('/metadata-config/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Configuration saved successfully', 'success')
            } else {
                this.showAlert(data.error || 'Error saving configuration', 'danger')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showAlert('Error: ' + error.message, 'danger')
        })
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
