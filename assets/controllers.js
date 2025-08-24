// Import the Stimulus app from app.js
import { stimulusApp as app } from './app.js';

console.log("Stimulus app imported:", app);
console.log("Available methods on app:", Object.getOwnPropertyNames(app));

// Breadcrumb Controller
app.register('breadcrumb', class extends window.Stimulus.Controller {
    static targets = ['container', 'toggle', 'collapsed']
    static values = {
        animated: { type: Boolean, default: true },
        mobileOptimized: { type: Boolean, default: true }
    }

    connect() {
        this.initializeBreadcrumbs();
        this.setupMobileOptimization();
    }

    initializeBreadcrumbs() {
        if (this.animatedValue) {
            this.animateBreadcrumbItems();
        }

        // Add hover effects for better UX
        this.element.addEventListener('mouseenter', this.handleMouseEnter.bind(this));
        this.element.addEventListener('mouseleave', this.handleMouseLeave.bind(this));
    }

    animateBreadcrumbItems() {
        const items = this.element.querySelectorAll('.breadcrumb-item');

        items.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
            item.classList.add('breadcrumb-animated');
        });
    }

    handleMouseEnter() {
        this.element.classList.add('breadcrumb-hovered');
    }

    handleMouseLeave() {
        this.element.classList.remove('breadcrumb-hovered');
    }

    setupMobileOptimization() {
        if (!this.mobileOptimizedValue || window.innerWidth > 768) {
            return;
        }

        // Optimize breadcrumbs for mobile
        const container = this.containerTarget;
        if (container) {
            container.classList.add('mobile-optimized');
        }

        // Add touch-friendly interactions
        this.addTouchInteractions();
    }

    addTouchInteractions() {
        const links = this.element.querySelectorAll('.breadcrumb-link');

        links.forEach(link => {
            link.addEventListener('touchstart', this.handleTouchStart.bind(this));
            link.addEventListener('touchend', this.handleTouchEnd.bind(this));
        });
    }

    handleTouchStart(event) {
        event.target.classList.add('touch-active');
    }

    handleTouchEnd(event) {
        setTimeout(() => {
            event.target.classList.remove('touch-active');
        }, 150);
    }

    // Method to programmatically update breadcrumbs
    updateBreadcrumbs(newItems) {
        // This method can be called from other controllers to update breadcrumbs dynamically
        if (Array.isArray(newItems)) {
            this.element.dispatchEvent(new CustomEvent('breadcrumbs:update', {
                detail: { items: newItems }
            }));
        }
    }

    // Method to show/hide breadcrumbs
    toggleVisibility() {
        this.element.classList.toggle('breadcrumb-hidden');
    }

    // Method to add a new breadcrumb item
    addItem(item) {
        const breadcrumbList = this.element.querySelector('.breadcrumb-main');
        if (breadcrumbList && item) {
            const newItem = this.createBreadcrumbItem(item);
            breadcrumbList.appendChild(newItem);

            // Animate the new item
            if (this.animatedValue) {
                newItem.style.animationDelay = '0s';
                newItem.classList.add('breadcrumb-animated');
            }
        }
    }

    createBreadcrumbItem(item) {
        const li = document.createElement('li');
        li.className = 'breadcrumb-item';

        if (item.active) {
            li.classList.add('active');
            li.setAttribute('aria-current', 'page');
        }

        const content = item.icon ?
            `<i class="${item.icon} me-1"></i><span class="breadcrumb-text">${item.text}</span>` :
            `<span class="breadcrumb-text">${item.text}</span>`;

        if (item.url) {
            li.innerHTML = `<a href="${item.url}" class="breadcrumb-link">${content}</a>`;
        } else {
            li.innerHTML = content;
        }

        return li;
    }

    disconnect() {
        // Clean up event listeners
        this.element.removeEventListener('mouseenter', this.handleMouseEnter.bind(this));
        this.element.removeEventListener('mouseleave', this.handleMouseLeave.bind(this));
    }
});

// Legacy inline controllers (to be converted to separate controller files)
// Contrôleur pour la recherche d'artistes
app.register('artist-search', class extends window.Stimulus.Controller {
    static targets = ['input', 'results', 'loading']
    static values = { url: String }

    connect() {
        this.debouncedSearch = this.debounce(this.search.bind(this), 300);
    }

    search() {
        const query = this.inputTarget.value.trim();
        if (query.length < 2) {
            this.clearResults();
            return;
        }

        this.showLoading();

        fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                this.displayResults(data);
            })
            .catch(error => {
                console.error('Search error:', error);
                this.showError();
            })
            .finally(() => {
                this.hideLoading();
            });
    }

    displayResults(data) {
        if (!data.length) {
            this.resultsTarget.innerHTML = '<div class="p-3 text-muted">Aucun résultat trouvé</div>';
            return;
        }

        const html = data.map(artist => `
            <div class="list-group-item list-group-item-action" data-action="click->artist-search#selectArtist" data-artist-id="${artist.id}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${artist.name}</h6>
                        ${artist.mbid ? `<small class="text-muted">MBID: ${artist.mbid}</small>` : ''}
                    </div>
                    <button class="btn btn-sm btn-outline-primary" data-action="click->artist-search#addArtist" data-artist-id="${artist.id}">
                        Ajouter
                    </button>
                </div>
            </div>
        `).join('');

        this.resultsTarget.innerHTML = html;
    }

    clearResults() {
        this.resultsTarget.innerHTML = '';
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('d-none');
        }
    }

    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('d-none');
        }
    }

    showError() {
        this.resultsTarget.innerHTML = '<div class="p-3 text-danger">' + window.translations['js.error_search'] + '</div>';
    }

    selectArtist(event) {
        const artistId = event.currentTarget.dataset.artistId;
        // Logique pour sélectionner un artiste
        console.log('Selected artist:', artistId);
    }

    addArtist(event) {
        event.stopPropagation();
        const artistId = event.currentTarget.dataset.artistId;

        fetch('/artist/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ artistId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.MusicarrApp.showAlert('Artiste ajouté avec succès', 'success');
                this.clearResults();
                this.inputTarget.value = '';
            } else {
                window.MusicarrApp.showAlert(data.error || window.translations['js.error_adding'], 'danger');
            }
        })
        .catch(error => {
            console.error('Add artist error:', error);
                            window.MusicarrApp.showAlert(window.translations['js.error_adding_artist'], 'danger');
        });
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});

// Contrôleur pour les modales
app.register('modal', class extends window.Stimulus.Controller {
    static targets = ['dialog', 'content']
    static values = { url: String }

    connect() {
        this.modal = new bootstrap.Modal(this.dialogTarget);
    }

    open() {
        if (this.hasUrlValue) {
            this.loadContent();
        }
        this.modal.show();
    }

    close() {
        this.modal.hide();
    }

    async loadContent() {
        try {
            const response = await fetch(this.urlValue);
            const html = await response.text();
            this.contentTarget.innerHTML = html;
        } catch (error) {
            console.error('Error loading modal content:', error);
            this.contentTarget.innerHTML = '<div class="p-3 text-danger">Erreur lors du chargement</div>';
        }
    }
});

// Contrôleur pour les tableaux avec tri
app.register('sortable-table', class extends window.Stimulus.Controller {
    static targets = ['header', 'row']
    static values = {
        sortBy: { type: String, default: '' },
        sortOrder: { type: String, default: 'asc' }
    }

    connect() {
        this.initializeSorting();
    }

    initializeSorting() {
        this.headerTargets.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.column;
                this.sort(column);
            });
        });
    }

    sort(column) {
        const currentOrder = this.sortByValue === column ? this.sortOrderValue : 'asc';
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';

        this.sortByValue = column;
        this.sortOrderValue = newOrder;

        this.updateSortIndicators();
        this.sortRows();
    }

    updateSortIndicators() {
        this.headerTargets.forEach(header => {
            const column = header.dataset.column;
            const icon = header.querySelector('.sort-icon');

            if (icon) {
                icon.className = 'sort-icon';
                if (this.sortByValue === column) {
                    icon.classList.add(this.sortOrderValue === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down');
                }
            }
        });
    }

    sortRows() {
        const rows = Array.from(this.rowTargets);
        const column = this.sortByValue;
        const order = this.sortOrderValue;

        rows.sort((a, b) => {
            const aValue = this.getCellValue(a, column);
            const bValue = this.getCellValue(b, column);

            if (order === 'asc') {
                return aValue.localeCompare(bValue);
                } else {
                return bValue.localeCompare(aValue);
            }
        });

        const tbody = this.element.querySelector('tbody');
        rows.forEach(row => tbody.appendChild(row));
    }

    getCellValue(row, column) {
        const cell = row.querySelector(`[data-column="${column}"]`);
        return cell ? cell.textContent.trim() : '';
    }
});

// Contrôleur pour les formulaires avec validation
app.register('form-validation', class extends window.Stimulus.Controller {
    static targets = ['input', 'error', 'submit']

    connect() {
        this.validateForm();
    }

    validate() {
        let isValid = true;
        this.inputTargets.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = !isValid;
        }

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        const rules = field.dataset.rules ? JSON.parse(field.dataset.rules) : {};
        let isValid = true;
        let errorMessage = '';

        // Validation required
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'Ce champ est requis';
        }

        // Validation email
        if (rules.email && value && !this.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Format d\'email invalide';
        }

        // Validation minLength
        if (rules.minLength && value.length < rules.minLength) {
            isValid = false;
            errorMessage = `Minimum ${rules.minLength} caractères`;
        }

        // Validation maxLength
        if (rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            errorMessage = `Maximum ${rules.maxLength} caractères`;
        }

        this.showFieldError(field, errorMessage);
        return isValid;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    showFieldError(field, message) {
        const errorElement = this.errorTargets.find(error => error.dataset.field === field.name);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = message ? 'block' : 'none';
        }

        if (message) {
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }
    }

    validateForm() {
        this.inputTargets.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.validateField(input));
        });
    }
});

// Contrôleur pour les notifications toast
app.register('toast', class extends window.Stimulus.Controller {
    static targets = ['container']
    static values = {
        position: { type: String, default: 'top-right' },
        duration: { type: Number, default: 5000 }
    }

    connect() {
        if (!this.hasContainerTarget) {
            this.createContainer();
        }
    }

    createContainer() {
        const container = document.createElement('div');
        container.className = `toast-container position-fixed ${this.getPositionClass()}`;
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        this.containerTarget = container;
    }

    getPositionClass() {
        const positions = {
            'top-right': 'top-0 end-0',
            'top-left': 'top-0 start-0',
            'bottom-right': 'bottom-0 end-0',
            'bottom-left': 'bottom-0 start-0',
            'top-center': 'top-0 start-50 translate-middle-x',
            'bottom-center': 'bottom-0 start-50 translate-middle-x'
        };
        return positions[this.positionValue] || positions['top-right'];
    }

    show(message, type = 'info') {
        const toast = this.createToast(message, type);
        this.containerTarget.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: this.durationValue
        });

        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        return toast;
    }
});

// Contrôleur pour les graphiques
app.register('chart', class extends window.Stimulus.Controller {
    static targets = ['canvas']
    static values = {
        type: { type: String, default: 'line' },
        data: Object,
        options: Object
    }

    connect() {
        if (typeof Chart !== 'undefined' && this.hasCanvasTarget) {
            this.createChart();
        }
    }

    createChart() {
        const ctx = this.canvasTarget.getContext('2d');
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false
        };

        this.chart = new Chart(ctx, {
            type: this.typeValue,
            data: this.dataValue,
            options: { ...defaultOptions, ...this.optionsValue }
        });
    }

    updateData(newData) {
        if (this.chart) {
            this.chart.data = newData;
            this.chart.update();
        }
    }

    destroy() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
});

// Contrôleur pour les uploads de fichiers
app.register('file-upload', class extends window.Stimulus.Controller {
    static targets = ['input', 'preview', 'progress', 'dropZone']
    static values = {
        url: String,
        maxSize: { type: Number, default: 10485760 }, // 10MB
        allowedTypes: { type: Array, default: [] }
    }

    connect() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        if (this.hasInputTarget) {
            this.inputTarget.addEventListener('change', (e) => this.handleFiles(e.target.files));
        }

        if (this.hasDropZoneTarget) {
            this.setupDragAndDrop();
        }
    }

    setupDragAndDrop() {
        this.dropZoneTarget.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.dropZoneTarget.classList.add('drag-over');
        });

        this.dropZoneTarget.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.dropZoneTarget.classList.remove('drag-over');
        });

        this.dropZoneTarget.addEventListener('drop', (e) => {
            e.preventDefault();
            this.dropZoneTarget.classList.remove('drag-over');
            this.handleFiles(e.dataTransfer.files);
        });
    }

    handleFiles(files) {
        Array.from(files).forEach(file => {
            if (this.validateFile(file)) {
                this.uploadFile(file);
            }
        });
    }

    validateFile(file) {
        // Vérification de la taille
        if (file.size > this.maxSizeValue) {
            this.showError(`Le fichier ${file.name} est trop volumineux`);
            return console.error('File too large:', file.name);
        }

        // Vérification du type
        if (this.allowedTypesValue.length > 0) {
            const isValidType = this.allowedTypesValue.some(type =>
                file.type.startsWith(type) || file.name.endsWith(type)
            );
            if (!isValidType) {
                this.showError(`Le type de fichier ${file.name} n'est pas autorisé`);
                return false;
            }
        }

        return true;
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess(`Fichier ${file.name} uploadé avec succès`);
                this.updatePreview(file);
            } else {
                this.showError(result.error || window.translations['js.error_upload'].replace('{filename}', file.name));
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showError(window.translations['js.error_upload'].replace('{filename}', file.name));
        }
    }

    updatePreview(file) {
        if (this.hasPreviewTarget && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.previewTarget.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    }

    showError(message) {
        window.MusicarrApp.showAlert(message, 'danger');
    }

    showSuccess(message) {
        window.MusicarrApp.showAlert(message, 'success');
    }
});

// Export de l'application Stimulus
export default app;
