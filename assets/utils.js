/**
 * Utilitaires JavaScript pour Musicarr Symfony
 */

/**
 * Classe pour la gestion des utilitaires
 */
class Utils {
    /**
     * Formate une durée en secondes vers un format lisible
     */
    static formatDuration(seconds) {
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
     * Formate une taille de fichier en bytes vers un format lisible
     */
    static formatFileSize(bytes) {
        if (!bytes || bytes < 0) return '0 B';

        const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const size = bytes / Math.pow(1024, i);
        
        return `${size.toFixed(2)} ${sizes[i]}`;
    }

    /**
     * Formate une date vers un format lisible
     */
    static formatDate(date, locale = 'fr-FR', options = {}) {
        if (!date) return 'N/A';

        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        const dateObj = new Date(date);
        return dateObj.toLocaleDateString(locale, { ...defaultOptions, ...options });
    }

    /**
     * Formate un timestamp relatif (il y a X temps)
     */
    static formatRelativeTime(date) {
        if (!date) return 'N/A';

        const now = new Date();
        const targetDate = new Date(date);
        const diffInSeconds = Math.floor((now - targetDate) / 1000);

        if (diffInSeconds < 60) {
            return 'À l\'instant';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `Il y a ${minutes} minute${minutes > 1 ? 's' : ''}`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `Il y a ${hours} heure${hours > 1 ? 's' : ''}`;
        } else if (diffInSeconds < 2592000) {
            const days = Math.floor(diffInSeconds / 86400);
            return `Il y a ${days} jour${days > 1 ? 's' : ''}`;
        } else {
            return this.formatDate(date);
        }
    }

    /**
     * Valide une adresse email
     */
    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Valide un MBID MusicBrainz
     */
    static isValidMbid(mbid) {
        const mbidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
        return mbidRegex.test(mbid);
    }

    /**
     * Nettoie une chaîne de caractères pour l'URL
     */
    static slugify(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    /**
     * Tronque un texte à une longueur donnée
     */
    static truncate(text, length = 100, suffix = '...') {
        if (!text || text.length <= length) return text;
        return text.substring(0, length) + suffix;
    }

    /**
     * Génère un ID unique
     */
    static generateId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Debounce une fonction
     */
    static debounce(func, wait) {
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

    /**
     * Throttle une fonction
     */
    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Copie du texte dans le presse-papiers
     */
    static async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            console.error(window.translations['js.error_copy'], err);
            return false;
        }
    }

    /**
     * Télécharge un fichier
     */
    static downloadFile(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Convertit un objet en FormData
     */
    static objectToFormData(obj) {
        const formData = new FormData();
        Object.keys(obj).forEach(key => {
            if (obj[key] !== null && obj[key] !== undefined) {
                formData.append(key, obj[key]);
            }
        });
        return formData;
    }

    /**
     * Parse les paramètres d'URL
     */
    static parseUrlParams(url = window.location.href) {
        const urlObj = new URL(url);
        const params = {};
        urlObj.searchParams.forEach((value, key) => {
            params[key] = value;
        });
        return params;
    }

    /**
     * Construit une URL avec des paramètres
     */
    static buildUrl(baseUrl, params = {}) {
        const url = new URL(baseUrl, window.location.origin);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        return url.toString();
    }

    /**
     * Vérifie si un élément est visible dans le viewport
     */
    static isElementInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    /**
     * Scroll vers un élément avec animation
     */
    static scrollToElement(element, offset = 0, duration = 500) {
        const targetPosition = element.offsetTop - offset;
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = Utils.easeInOutQuad(timeElapsed, startPosition, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        requestAnimationFrame(animation);
    }

    /**
     * Fonction d'easing pour les animations
     */
    static easeInOutQuad(t, b, c, d) {
        t /= d / 2;
        if (t < 1) return c / 2 * t * t + b;
        t--;
        return -c / 2 * (t * (t - 2) - 1) + b;
    }

    /**
     * Retourne un nombre aléatoire entre min et max
     */
    static random(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    /**
     * Retourne un élément aléatoire d'un tableau
     */
    static randomElement(array) {
        return array[Math.floor(Math.random() * array.length)];
    }

    /**
     * Retourne un tableau mélangé
     */
    static shuffle(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }

    /**
     * Retourne un tableau sans doublons
     */
    static unique(array) {
        return [...new Set(array)];
    }

    /**
     * Retourne un objet avec les propriétés communes
     */
    static intersection(obj1, obj2) {
        const result = {};
        Object.keys(obj1).forEach(key => {
            if (obj2.hasOwnProperty(key) && obj1[key] === obj2[key]) {
                result[key] = obj1[key];
            }
        });
        return result;
    }

    /**
     * Retourne un objet avec les propriétés différentes
     */
    static difference(obj1, obj2) {
        const result = {};
        Object.keys(obj1).forEach(key => {
            if (!obj2.hasOwnProperty(key) || obj1[key] !== obj2[key]) {
                result[key] = obj1[key];
            }
        });
        return result;
    }

    /**
     * Retourne un objet avec les propriétés fusionnées
     */
    static merge(...objects) {
        return objects.reduce((result, obj) => {
            return { ...result, ...obj };
        }, {});
    }

    /**
     * Retourne un objet avec les propriétés profondément fusionnées
     */
    static deepMerge(target, source) {
        const result = { ...target };
        Object.keys(source).forEach(key => {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                result[key] = this.deepMerge(result[key] || {}, source[key]);
            } else {
                result[key] = source[key];
            }
        });
        return result;
    }

    /**
     * Retourne un objet avec les propriétés filtrées
     */
    static filterObject(obj, predicate) {
        const result = {};
        Object.keys(obj).forEach(key => {
            if (predicate(obj[key], key, obj)) {
                result[key] = obj[key];
            }
        });
        return result;
    }

    /**
     * Retourne un objet avec les propriétés mappées
     */
    static mapObject(obj, mapper) {
        const result = {};
        Object.keys(obj).forEach(key => {
            result[key] = mapper(obj[key], key, obj);
        });
        return result;
    }

    /**
     * Retourne un objet avec les clés et valeurs inversées
     */
    static invertObject(obj) {
        const result = {};
        Object.keys(obj).forEach(key => {
            result[obj[key]] = key;
        });
        return result;
    }

    /**
     * Retourne un objet avec les clés et valeurs groupées
     */
    static groupBy(array, key) {
        return array.reduce((result, item) => {
            const group = item[key];
            if (!result[group]) {
                result[group] = [];
            }
            result[group].push(item);
            return result;
        }, {});
    }

    /**
     * Retourne un objet avec les clés et valeurs comptées
     */
    static countBy(array, key) {
        return array.reduce((result, item) => {
            const group = item[key];
            result[group] = (result[group] || 0) + 1;
            return result;
        }, {});
    }

    /**
     * Retourne un objet avec les clés et valeurs sommées
     */
    static sumBy(array, key) {
        return array.reduce((result, item) => {
            const group = item[key];
            result[group] = (result[group] || 0) + (item.value || 0);
            return result;
        }, {});
    }

    /**
     * Retourne un objet avec les clés et valeurs moyennées
     */
    static averageBy(array, key) {
        const grouped = this.groupBy(array, key);
        const result = {};
        Object.keys(grouped).forEach(group => {
            const values = grouped[group].map(item => item.value || 0);
            result[group] = values.reduce((sum, value) => sum + value, 0) / values.length;
        });
        return result;
    }

    /**
     * Retourne un objet avec les clés et valeurs min/max
     */
    static minMaxBy(array, key) {
        const grouped = this.groupBy(array, key);
        const result = {};
        Object.keys(grouped).forEach(group => {
            const values = grouped[group].map(item => item.value || 0);
            result[group] = {
                min: Math.min(...values),
                max: Math.max(...values)
            };
        });
        return result;
    }

    /**
     * Retourne un objet avec les clés et valeurs triées
     */
    static sortBy(array, key, order = 'asc') {
        const sorted = [...array].sort((a, b) => {
            const aValue = a[key];
            const bValue = b[key];
            if (order === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });
        return sorted;
    }

    /**
     * Retourne un objet avec les clés et valeurs filtrées
     */
    static filterBy(array, predicate) {
        return array.filter(predicate);
    }

    /**
     * Retourne un objet avec les clés et valeurs mappées
     */
    static mapBy(array, mapper) {
        return array.map(mapper);
    }

    /**
     * Retourne un objet avec les clés et valeurs réduites
     */
    static reduceBy(array, reducer, initialValue) {
        return array.reduce(reducer, initialValue);
    }

    /**
     * Retourne un objet avec les clés et valeurs trouvées
     */
    static findBy(array, predicate) {
        return array.find(predicate);
    }

    /**
     * Retourne un objet avec les clés et valeurs trouvées par index
     */
    static findIndexBy(array, predicate) {
        return array.findIndex(predicate);
    }

    /**
     * Retourne un objet avec les clés et valeurs incluses
     */
    static includesBy(array, value) {
        return array.includes(value);
    }

    /**
     * Retourne un objet avec les clés et valeurs indexées
     */
    static indexOfBy(array, value) {
        return array.indexOf(value);
    }

    /**
     * Retourne un objet avec les clés et valeurs last indexées
     */
    static lastIndexOfBy(array, value) {
        return array.lastIndexOf(value);
    }

    /**
     * Retourne un objet avec les clés et valeurs some
     */
    static someBy(array, predicate) {
        return array.some(predicate);
    }

    /**
     * Retourne un objet avec les clés et valeurs every
     */
    static everyBy(array, predicate) {
        return array.every(predicate);
    }

    /**
     * Retourne un objet avec les clés et valeurs flat
     */
    static flatBy(array, depth = 1) {
        return array.flat(depth);
    }

    /**
     * Retourne un objet avec les clés et valeurs flatMap
     */
    static flatMapBy(array, mapper) {
        return array.flatMap(mapper);
    }

    /**
     * Retourne un objet avec les clés et valeurs slice
     */
    static sliceBy(array, start, end) {
        return array.slice(start, end);
    }

    /**
     * Retourne un objet avec les clés et valeurs splice
     */
    static spliceBy(array, start, deleteCount, ...items) {
        const result = [...array];
        result.splice(start, deleteCount, ...items);
        return result;
    }

    /**
     * Retourne un objet avec les clés et valeurs reverse
     */
    static reverseBy(array) {
        return [...array].reverse();
    }

    /**
     * Retourne un objet avec les clés et valeurs join
     */
    static joinBy(array, separator = ',') {
        return array.join(separator);
    }

    /**
     * Retourne un objet avec les clés et valeurs toString
     */
    static toStringBy(array) {
        return array.toString();
    }

    /**
     * Retourne un objet avec les clés et valeurs toLocaleString
     */
    static toLocaleStringBy(array, locale, options) {
        return array.toLocaleString(locale, options);
    }

    /**
     * Retourne un objet avec les clés et valeurs toLocaleDateString
     */
    static toLocaleDateStringBy(array, locale, options) {
        return array.toLocaleDateString(locale, options);
    }

    /**
     * Retourne un objet avec les clés et valeurs toLocaleTimeString
     */
    static toLocaleTimeStringBy(array, locale, options) {
        return array.toLocaleTimeString(locale, options);
    }

    /**
     * Retourne un objet avec les clés et valeurs toFixed
     */
    static toFixedBy(number, digits = 0) {
        return number.toFixed(digits);
    }

    /**
     * Retourne un objet avec les clés et valeurs toPrecision
     */
    static toPrecisionBy(number, precision) {
        return number.toPrecision(precision);
    }

    /**
     * Retourne un objet avec les clés et valeurs toExponential
     */
    static toExponentialBy(number, fractionDigits) {
        return number.toExponential(fractionDigits);
    }

    /**
     * Retourne un objet avec les clés et valeurs toLocaleString
     */
    static toLocaleStringByNumber(number, locale, options) {
        return number.toLocaleString(locale, options);
    }

    /**
     * Retourne un objet avec les clés et valeurs toLocaleDateString
     */
    static toLocaleDateStringByNumber(number, locale, options) {
        return number.toLocaleDateString(locale, options);
    }

    /**
     * Retourne un objet avec les clés et valeurs toLocaleTimeString
     */
    static toLocaleTimeStringByNumber(number, locale, options) {
        return number.toLocaleTimeString(locale, options);
    }
}

// Export pour utilisation globale
window.Utils = Utils;

// Export par défaut pour les modules
export default Utils; 