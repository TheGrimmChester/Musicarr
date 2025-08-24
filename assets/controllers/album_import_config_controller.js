import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'primaryTypeCheckbox', 'secondaryTypeCheckbox', 'releaseStatusCheckbox',
        'primaryTypesCount', 'secondaryTypesCount', 'releaseStatusesCount'
    ]

    connect() {
        // Controller is ready
    }

    updateSummaryCounts() {
        const primaryTypes = Array.from(this.primaryTypeCheckboxTargets).filter(cb => cb.checked).map(cb => cb.value)
        const secondaryTypes = Array.from(this.secondaryTypeCheckboxTargets).filter(cb => cb.checked).map(cb => cb.value)
        const releaseStatuses = Array.from(this.releaseStatusCheckboxTargets).filter(cb => cb.checked).map(cb => cb.value)

        this.primaryTypesCountTarget.textContent = primaryTypes.length
        this.secondaryTypesCountTarget.textContent = secondaryTypes.length
        this.releaseStatusesCountTarget.textContent = releaseStatuses.length
    }

    saveConfig() {
        const primaryTypes = Array.from(this.primaryTypeCheckboxTargets).filter(cb => cb.checked).map(cb => cb.value)
        const secondaryTypes = Array.from(this.secondaryTypeCheckboxTargets).filter(cb => cb.checked).map(cb => cb.value)
        const releaseStatuses = Array.from(this.releaseStatusCheckboxTargets).filter(cb => cb.checked).map(cb => cb.value)

        const config = {
            primary_types: primaryTypes,
            secondary_types: secondaryTypes,
            release_statuses: releaseStatuses
        }

        fetch('/album-import-config/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(config)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Try to get translation from the page, fallback to English
                const successMessage = document.querySelector('[data-translation="config.saved_successfully"]')?.textContent || 'Configuration saved successfully';
                alert(successMessage);
            } else {
                const errorMessage = document.querySelector('[data-translation="app.error"]')?.textContent || 'Error';
                alert(errorMessage + ': ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorMessage = document.querySelector('[data-translation="config.save_error"]')?.textContent || 'Error saving configuration';
            alert(errorMessage);
        })
    }

    resetConfig() {
        const confirmMessage = document.querySelector('[data-translation="config.reset_confirm"]')?.textContent || 'Are you sure you want to reset the configuration to default values?';
        if (confirm(confirmMessage)) {
            fetch('/album-import-config/reset', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload()
                } else {
                    alert('Error: ' + data.error)
                }
            })
            .catch(error => {
                console.error('Error:', error)
                alert('Error resetting configuration')
            })
        }
    }
}
