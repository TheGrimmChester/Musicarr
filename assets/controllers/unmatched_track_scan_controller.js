import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['scanForm', 'startScanBtn', 'scanResults', 'librarySelect', 'dryRunCheck', 'forceCheck']

    connect() {
        console.log('Unmatched Track Scan controller connected')
    }

    startScan(event) {
        event.preventDefault()

        const libraryId = this.librarySelectTarget.value
        const dryRun = this.dryRunCheckTarget.checked
        const force = this.forceCheckTarget.checked

        this.startScanBtnTarget.disabled = true
        this.startScanBtnTarget.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Launching scan...'

        this.scanResultsTarget.innerHTML = `
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Launching scan...</span>
                </div>
                <p class="mt-2">Scan is being launched in the background...</p>
            </div>
        `

        // Prepare data for API
        const data = {
            libraryId: libraryId || null,
            dryRun: dryRun,
            force: force
        }

        // Call async API
        fetch('/unmatched-tracks/scan-libraries/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.scanResultsTarget.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Scan launched successfully!</strong><br>
                        <p class="mb-0">${data.message}</p>
                        ${data.libraries_count ? `<small>Libraries count: ${data.libraries_count}</small>` : ''}
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Background scan information</strong>
                    </div>
                `
            } else {
                this.scanResultsTarget.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Error launching scan</strong><br>
                        <p class="mb-0">${data.error || 'Unknown error'}</p>
                    </div>
                `
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.scanResultsTarget.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Connection error</strong><br>
                    <p class="mb-0">Cannot contact server</p>
                </div>
            `
        })
        .finally(() => {
            this.startScanBtnTarget.disabled = false
            this.startScanBtnTarget.innerHTML = '<i class="fas fa-play"></i> Start Scan'
        })
    }
}
