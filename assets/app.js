import './bootstrap.js';
// Import des styles
import './styles/app.css';
import './styles/twig-extracted.css';
import './styles/components.css';
import './styles/layout.css';
import './styles/utilities.css';
import './styles/music.css';

// Import Stimulus application
import { startStimulusApp } from '@symfony/stimulus-bridge';

// Start Stimulus application with automatic controller loading
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/
));

// Modern application utilities without jQuery dependency
class MusicarrApp {
    constructor() {
        this.translations = window.translations || {};
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            // Load plugin controllers after DOM is ready and Stimulus app is initialized
            import('./plugin_loader.js').then(() => {
                console.log('✅ Plugin loader imported successfully');
            }).catch(error => {
                console.error('❌ Failed to import plugin loader:', error);
            });
            
            this.initializeTooltips();
            this.initializeAlerts();
            this.initializeEventListeners();
            
            // Initialize shortcuts after everything is ready
            this.initializeShortcuts();
        });
    }

    /**
     * Initialize keyboard shortcuts
     */
    initializeShortcuts() {
        // Wait a bit to ensure MusicarrShortcuts is available
        setTimeout(() => {
            if (window.MusicarrShortcuts && window.MusicarrShortcuts.initShortcuts) {
                console.log('MusicarrApp: Initializing shortcuts...');
                window.MusicarrShortcuts.initShortcuts();
            } else {
                console.warn('MusicarrApp: MusicarrShortcuts not available');
            }
        }, 100);
    }

    /**
     * Initialize Bootstrap tooltips
     */
    initializeTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(element => {
            new bootstrap.Tooltip(element);
        });
    }

    /**
     * Initialize auto-hide alerts
     */
    initializeAlerts() {
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    }

    /**
     * Initialize event listeners
     */
    initializeEventListeners() {
        // Handle confirmation dialogs
        document.addEventListener('click', (e) => {
            const confirmElement = e.target.closest('[data-confirm]');
            if (confirmElement) {
                const message = confirmElement.dataset.confirm;
                if (!confirm(message || 'Are you sure?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Handle download links
        document.addEventListener('click', (e) => {
            const downloadLink = e.target.closest('.download-link');
            if (downloadLink) {
                const originalText = downloadLink.textContent;
                downloadLink.textContent = this.getTranslation('js.downloading', 'Downloading...');
                downloadLink.disabled = true;

                setTimeout(() => {
                    downloadLink.textContent = originalText;
                    downloadLink.disabled = false;
                }, 2000);
            }
        });
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info', duration = 5000) {
        const alertId = `alert-${Date.now()}`;
        const alertElement = document.createElement('div');
        alertElement.id = alertId;
        alertElement.className = `alert alert-${type} alert-dismissible fade show`;
        alertElement.setAttribute('role', 'alert');
        alertElement.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container-fluid');
        if (container) {
            container.insertBefore(alertElement, container.firstChild);
        }

        if (duration > 0) {
            setTimeout(() => {
                alertElement.style.transition = 'opacity 0.5s';
                alertElement.style.opacity = '0';
                setTimeout(() => alertElement.remove(), 500);
            }, duration);
        }
    }

    /**
     * Show loading indicator on element
     */
    showLoading(element) {
        const originalText = element.textContent;
        element.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${this.getTranslation('js.loading', 'Loading...')}`;
        element.disabled = true;
        return originalText;
    }

    /**
     * Hide loading indicator
     */
    hideLoading(element, originalText) {
        element.textContent = originalText;
        element.disabled = false;
    }

    /**
     * Format duration in seconds
     */
    formatDuration(seconds) {
        if (!seconds || seconds < 0) return '0:00';

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
    }

    /**
     * Format file size in bytes
     */
    formatFileSize(bytes) {
        if (!bytes || bytes < 0) return '0 B';

        const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const size = bytes / Math.pow(1024, i);

        return `${size.toFixed(2)} ${sizes[i]}`;
    }

    /**
     * Format date
     */
    formatDate(dateString, locale = 'fr-FR', options = {}) {
        if (!dateString) return this.getTranslation('js.not_available', 'N/A');

        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        const date = new Date(dateString);
        return date.toLocaleDateString(locale, { ...defaultOptions, ...options });
    }

    /**
     * Get translation
     */
    getTranslation(key, defaultValue = '') {
        return this.translations[key] || defaultValue;
    }

    /**
     * Make HTTP request with error handling
     */
    async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        };

        const requestOptions = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, requestOptions);
            const data = await response.json();

            if (response.ok) {
                return { success: true, data };
            } else {
                return { success: false, error: data.error || response.statusText };
            }
        } catch (error) {
            console.error('Request Error:', error);
            return {
                success: false,
                error: error.message || 'Network error'
            };
        }
    }

    /**
     * Search artists
     */
    async searchArtists(query) {
        const params = new URLSearchParams({ q: query });
        const result = await this.request(`/artist/search?${params}`);

        if (result.success) {
            return result.data;
        } else {
            this.showAlert(this.getTranslation('js.error_searching', 'Error searching artists'), 'danger');
            return [];
        }
    }

    /**
     * Add artist
     */
    async addArtist(artistData) {
        const result = await this.request('/artist/add', {
            method: 'POST',
            body: JSON.stringify(artistData)
        });

        if (result.success) {
            this.showAlert(this.getTranslation('js.artist_added', 'Artist added successfully'), 'success');
            return result.data;
        } else {
            this.showAlert(result.error || this.getTranslation('js.error_adding', 'Error adding artist'), 'danger');
            return null;
        }
    }

    /**
     * Sync artist
     */
    async syncArtist(artistId) {
        const button = document.querySelector(`[data-artist-id="${artistId}"]`);
        const originalText = button ? this.showLoading(button) : null;

        const result = await this.request(`/artist/${artistId}/sync`, {
            method: 'POST'
        });

        if (button && originalText) {
            this.hideLoading(button, originalText);
        }

        if (result.success) {
            this.showAlert(this.getTranslation('js.sync_completed', 'Sync completed'), 'success');
            return result.data;
        } else {
            this.showAlert(this.getTranslation('js.error_sync', 'Error during sync'), 'danger');
            return null;
        }
    }

    /**
     * Load libraries
     */
    async loadLibraries() {
        const result = await this.request('/library/list');
        return result.success ? result.data : [];
    }

    /**
     * Scan library
     */
    async scanLibrary(libraryId, options = {}) {
        const { dryRun = false, force = false } = options;
        const button = document.querySelector(`[data-library-id="${libraryId}"]`);
        const originalText = button ? this.showLoading(button) : null;

        const result = await this.request(`/library/${libraryId}/scan`, {
            method: 'POST',
            body: JSON.stringify({ dryRun, force })
        });

        if (button && originalText) {
            this.hideLoading(button, originalText);
        }

        if (result.success) {
            this.showAlert(result.data?.message || this.getTranslation('js.scan_started', 'Scan started'), 'success');
            return result.data;
        } else {
            this.showAlert(this.getTranslation('js.error_scan', 'Error during scan'), 'danger');
            return null;
        }
    }

    /**
     * Scan all libraries
     */
    async scanAllLibraries(options = {}) {
        const { dryRun = false, force = false } = options;
        const button = document.querySelector('[data-action="scan-all"]');
        const originalText = button ? this.showLoading(button) : null;

        const result = await this.request('/library/scan-all', {
            method: 'POST',
            body: JSON.stringify({ dryRun, force })
        });

        if (button && originalText) {
            this.hideLoading(button, originalText);
        }

        if (result.success) {
            this.showAlert(result.data?.message || this.getTranslation('js.scan_all_started', 'Scan of all libraries started'), 'success');
            return result.data;
        } else {
            this.showAlert(this.getTranslation('js.error_scan', 'Error during scan'), 'danger');
            return null;
        }
    }

    /**
     * Load album tracks
     */
    async loadAlbumTracks(albumId) {
        const result = await this.request(`/album/${albumId}/tracks`);
        return result.success ? result.data : [];
    }

    /**
     * Toggle album monitoring
     */
    async toggleAlbumMonitor(albumId, monitored) {
        const result = await this.request(`/album/${albumId}/toggle-monitor`, {
            method: 'POST',
            body: JSON.stringify({ monitored })
        });

        if (result.success) {
            this.showAlert(this.getTranslation('js.album_updated', 'Album updated'), 'success');
            return result.data;
        } else {
            this.showAlert(this.getTranslation('js.error_update', 'Error during update'), 'danger');
            return null;
        }
    }

    /**
     * Load track details
     */
    async loadTrackDetails(trackId) {
        const result = await this.request(`/track/${trackId}`);
        return result.success ? result.data : null;
    }

    /**
     * Show modal
     */
    showModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }

    /**
     * Hide modal
     */
    hideModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    }

    /**
     * Create chart (Chart.js)
     */
    createChart(canvasId, data, options = {}) {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js is not available');
            return null;
        }

        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false
        };

        return new Chart(ctx, {
            type: 'line',
            data,
            options: { ...defaultOptions, ...options }
        });
    }

    /**
     * Request notification permission
     */
    requestNotificationPermission() {
        if ('Notification' in window) {
            Notification.requestPermission();
        }
    }

    /**
     * Show notification
     */
    showNotification(title, options = {}) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, options);
        }
    }

    /**
     * Initialize drag & drop
     */
    initializeDragAndDrop(dropZone, onDrop) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');

            const files = e.dataTransfer.files;
            if (onDrop) onDrop(files);
        });
    }

    /**
     * Update stats in real-time
     */
    updateStats() {
        // Implementation for real-time updates
        console.log('Stats updated');
    }

    /**
     * Handle global errors
     */
    handleGlobalError(error) {
        console.error('Global error:', error);
        this.showAlert(this.getTranslation('js.unexpected_error', 'An unexpected error occurred'), 'danger');
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    /**
     * Generate unique ID
     */
    generateId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`
    }

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout)
                func(...args)
            }
            clearTimeout(timeout)
            timeout = setTimeout(later, wait)
        }
    }

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle
        return function() {
            const args = arguments
            const context = this
            if (!inThrottle) {
                func.apply(context, args)
                inThrottle = true
                setTimeout(() => inThrottle = false, limit)
            }
        }
    }
}

// Initialize application
const musicarrApp = new MusicarrApp();

// Handle global errors
window.addEventListener('error', (e) => {
    musicarrApp.handleGlobalError(e.error);
});

// Export for global use (backward compatibility)
window.MusicarrApp = musicarrApp;

// Export Stimulus app and utilities
export { app as stimulusApp };
export default musicarrApp;
