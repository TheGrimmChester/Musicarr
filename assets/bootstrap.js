// Import utilities
import Utils from './utils.js';

// Global configuration
window.MusicarrUtils = Utils;

// Configuration des options par défaut
window.MusicarrConfig = {
    // Configuration des alertes
    alerts: {
        duration: 5000,
        position: 'top-right'
    },

    // Configuration des requêtes AJAX
    ajax: {
        timeout: 30000,
        retryAttempts: 3
    },

    // Configuration des uploads
    upload: {
        maxSize: 10485760, // 10MB
        allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'audio/mpeg', 'audio/wav', 'audio/flac']
    },

    // Configuration des graphiques
    charts: {
        colors: ['#17a2b8', '#20c997', '#f39c12', '#e74c3c', '#6c757d', '#343a40'],
        responsive: true
    }
};

// Gestionnaire d'erreurs global
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);

    // Envoi de l'erreur au serveur si nécessaire
    if (window.MusicarrApp && window.MusicarrApp.handleGlobalError) {
        window.MusicarrApp.handleGlobalError(event.error);
    }
});

// Gestionnaire d'erreurs non gérées
window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);

    // Envoi de l'erreur au serveur si nécessaire
    if (window.MusicarrApp && window.MusicarrApp.handleGlobalError) {
        window.MusicarrApp.handleGlobalError(event.reason);
    }
});

// Configuration des performances
if ('performance' in window) {
    // Mesure du temps de chargement
    window.addEventListener('load', () => {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`Page loaded in ${loadTime}ms`);
    });
}

// Configuration du service worker (si disponible)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Configuration des notifications push (si disponible)
if ('Notification' in window) {
    // Demande de permission pour les notifications
    if (Notification.permission === 'default') {
        // La permission sera demandée lors de la première interaction utilisateur
        console.log('Notification permission will be requested on first user interaction');
    }
}

// Configuration du cache
if ('caches' in window) {
    // Mise en cache des ressources statiques
    const staticCacheName = 'musicarr-static-v1';
    const staticAssets = [
        '/build/app.css',
        '/build/app.js',
        '/build/runtime.js'
    ];

    window.addEventListener('install', (event) => {
        event.waitUntil(
            caches.open(staticCacheName)
                .then(cache => cache.addAll(staticAssets))
        );
    });
}

// Configuration de l'intersection observer pour le lazy loading
if ('IntersectionObserver' in window) {
    // Configuration par défaut pour le lazy loading des images
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });

    // Observer les images avec la classe 'lazy'
    document.addEventListener('DOMContentLoaded', () => {
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => imageObserver.observe(img));
    });
}

// Configuration du resize observer pour les graphiques
if ('ResizeObserver' in window) {
    // Observer les changements de taille pour redimensionner les graphiques
    const resizeObserver = new ResizeObserver(entries => {
        entries.forEach(entry => {
            const element = entry.target;
            if (element.chart) {
                element.chart.resize();
            }
        });
    });

    // Observer les conteneurs de graphiques
    document.addEventListener('DOMContentLoaded', () => {
        const chartContainers = document.querySelectorAll('.chart-container');
        chartContainers.forEach(container => resizeObserver.observe(container));
    });
}

// Configuration des mutations observer pour les mises à jour dynamiques
if ('MutationObserver' in window) {
    // Observer les changements dans le DOM pour les mises à jour automatiques
    const mutationObserver = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            if (mutation.type === 'childList') {
                // Re-initialiser les composants si nécessaire
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Re-initialiser les tooltips
                        const tooltips = node.querySelectorAll('[data-bs-toggle="tooltip"]');
                        tooltips.forEach(element => {
                            new bootstrap.Tooltip(element);
                        });

                        // Re-initialiser les popovers
                        const popovers = node.querySelectorAll('[data-bs-toggle="popover"]');
                        popovers.forEach(element => {
                            new bootstrap.Popover(element);
                        });
                    }
                });
            }
        });
    });

    // Observer le body pour les changements
    mutationObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
}

// Configuration des événements personnalisés
window.MusicarrEvents = {
    // Événements pour les artistes
    ARTIST_ADDED: 'musicarr:artist:added',
    ARTIST_UPDATED: 'musicarr:artist:updated',
    ARTIST_DELETED: 'musicarr:artist:deleted',

    // Événements pour les albums
    ALBUM_ADDED: 'musicarr:album:added',
    ALBUM_UPDATED: 'musicarr:album:updated',
    ALBUM_DELETED: 'musicarr:album:deleted',

    // Événements pour les bibliothèques
    LIBRARY_SCANNED: 'musicarr:library:scanned',
    LIBRARY_UPDATED: 'musicarr:library:updated',

    // Événements pour les pistes
    TRACK_ADDED: 'musicarr:track:added',
    TRACK_UPDATED: 'musicarr:track:updated',
    TRACK_DELETED: 'musicarr:track:deleted',

    // Événements pour les notifications
    NOTIFICATION_SHOWN: 'musicarr:notification:shown',
    NOTIFICATION_HIDDEN: 'musicarr:notification:hidden',

    // Événements pour les modales
    MODAL_OPENED: 'musicarr:modal:opened',
    MODAL_CLOSED: 'musicarr:modal:closed',

    // Événements pour les formulaires
    FORM_SUBMITTED: 'musicarr:form:submitted',
    FORM_VALIDATED: 'musicarr:form:validated',

    // Événements pour les uploads
    FILE_UPLOADED: 'musicarr:file:uploaded',
    FILE_UPLOAD_ERROR: 'musicarr:file:upload:error',

    // Événements pour les graphiques
    CHART_CREATED: 'musicarr:chart:created',
    CHART_UPDATED: 'musicarr:chart:updated',

    // Événements pour les tableaux
    TABLE_SORTED: 'musicarr:table:sorted',
    TABLE_FILTERED: 'musicarr:table:filtered',
    TABLE_PAGINATED: 'musicarr:table:paginated'
};

// Fonction utilitaire pour émettre des événements personnalisés
window.MusicarrEventEmitter = {
    emit(eventName, data = {}) {
        const event = new CustomEvent(eventName, {
            detail: data,
            bubbles: true,
            cancelable: true
        });
        document.dispatchEvent(event);
    },

    on(eventName, callback) {
        document.addEventListener(eventName, (event) => {
            callback(event.detail);
        });
    },

    off(eventName, callback) {
        document.removeEventListener(eventName, callback);
    }
};

// Configuration des raccourcis clavier
window.MusicarrShortcuts = {
    shortcuts: new Map(),

    register(key, callback, description = '') {
        this.shortcuts.set(key, { callback, description });
    },

    unregister(key) {
        this.shortcuts.delete(key);
    },

    init() {
        document.addEventListener('keydown', (event) => {
            const key = this.getKeyCombo(event);
            const shortcut = this.shortcuts.get(key);

            if (shortcut) {
                event.preventDefault();
                shortcut.callback(event);
            }
        });
    },

    getKeyCombo(event) {
        const keys = [];

        if (event.ctrlKey) keys.push('Ctrl');
        if (event.altKey) keys.push('Alt');
        if (event.shiftKey) keys.push('Shift');
        if (event.metaKey) keys.push('Meta');

        if (event.key && event.key !== 'Control' && event.key !== 'Alt' && event.key !== 'Shift' && event.key !== 'Meta') {
            keys.push(event.key.toUpperCase());
        }

        return keys.join('+');
    },

    // Initialize shortcuts after MusicarrApp is available
    initShortcuts(retryCount = 0) {
        const maxRetries = 50; // Maximum 5 seconds of retries
        
        // More comprehensive check for MusicarrApp availability
        if (!window.MusicarrApp || 
            typeof window.MusicarrApp !== 'object' || 
            !window.MusicarrApp.getTranslation || 
            typeof window.MusicarrApp.getTranslation !== 'function') {
            
            if (retryCount < maxRetries) {
                // If MusicarrApp is not available yet, try again later
                console.log(`MusicarrShortcuts: MusicarrApp not ready (attempt ${retryCount + 1}/${maxRetries}), retrying in 100ms...`);
                setTimeout(() => this.initShortcuts(retryCount + 1), 100);
                return;
            } else {
                console.warn('MusicarrShortcuts: MusicarrApp not available after maximum retries, using fallback shortcuts');
                this.initFallbackShortcuts();
                return;
            }
        }

        console.log('MusicarrShortcuts: MusicarrApp is available, initializing shortcuts...');
        
        try {
            // Raccourcis par défaut
            this.register('Ctrl+S', (event) => {
                // Sauvegarder
                console.log('Save triggered');
            }, window.MusicarrApp.getTranslation('js.shortcuts.save', 'Save'));



            this.register('Ctrl+N', (event) => {
                // Nouveau
                console.log('New triggered');
            }, window.MusicarrApp.getTranslation('js.shortcuts.new', 'New'));

            this.register('Ctrl+Z', (event) => {
                // Annuler
                console.log('Undo triggered');
            }, window.MusicarrApp.getTranslation('js.shortcuts.undo', 'Undo'));

            this.register('Ctrl+Y', (event) => {
                // Rétablir
                console.log('Redo triggered');
            }, window.MusicarrApp.getTranslation('js.shortcuts.redo', 'Redo'));

            console.log('Keyboard shortcuts initialized successfully');
        } catch (error) {
            console.error('Error initializing keyboard shortcuts:', error);
            // Fallback: register shortcuts with default descriptions
            this.initFallbackShortcuts();
        }
    },

    // Initialize fallback shortcuts with default descriptions
    initFallbackShortcuts() {
        this.register('Ctrl+S', (event) => {
            console.log('Save triggered');
        }, 'Save');

        this.register('Ctrl+N', (event) => {
            console.log('New triggered');
        }, 'New');

        this.register('Ctrl+Z', (event) => {
            console.log('Undo triggered');
        }, 'Undo');

        this.register('Ctrl+Y', (event) => {
            console.log('Redo triggered');
        }, 'Redo');

        console.log('Fallback keyboard shortcuts initialized');
    }
};

// Initialize shortcuts system
window.MusicarrShortcuts.init();

// Initialize shortcuts after MusicarrApp is available
console.log('MusicarrShortcuts: Starting initialization...');
window.MusicarrShortcuts.initShortcuts();

// Also try to initialize when the window loads as a backup
window.addEventListener('load', () => {
    console.log('MusicarrShortcuts: Window loaded, checking if shortcuts need initialization...');
    if (window.MusicarrShortcuts && !window.MusicarrShortcuts.shortcuts.size) {
        console.log('MusicarrShortcuts: No shortcuts registered, reinitializing...');
        window.MusicarrShortcuts.initShortcuts();
    }
});

// Final fallback initialization after 3 seconds
setTimeout(() => {
    if (window.MusicarrShortcuts && !window.MusicarrShortcuts.shortcuts.size) {
        console.log('MusicarrShortcuts: Final fallback initialization...');
        window.MusicarrShortcuts.initFallbackShortcuts();
    }
}, 3000);

// Global error handler for shortcut-related errors
window.addEventListener('error', (event) => {
    if (event.error && event.error.message && event.error.message.includes('getTranslation')) {
        console.error('MusicarrShortcuts: Translation error detected:', event.error);
        console.log('MusicarrShortcuts: Attempting to reinitialize with fallback...');
        if (window.MusicarrShortcuts && window.MusicarrShortcuts.initFallbackShortcuts) {
            window.MusicarrShortcuts.initFallbackShortcuts();
        }
    }
});

// Export utilities
export default Utils;
