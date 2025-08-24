import { Controller } from "@hotwired/stimulus"

/**
 * CSRF Protection Stimulus controller
 * Handles CSRF token generation and validation for form submissions
 */
export default class extends Controller {
    static targets = ["token"]

    static values = {
        cookie: String
    }

    connect() {
        console.log("CSRF Protection controller connected")
        this.setupEventListeners()
    }

    disconnect() {
        this.removeEventListeners()
    }

    setupEventListeners() {
        // Listen for form submissions
        document.addEventListener('submit', this.handleFormSubmit.bind(this), true)
        
        // Listen for Turbo form submissions
        if (typeof window.Turbo !== 'undefined') {
            document.addEventListener('turbo:submit-start', this.handleTurboSubmitStart.bind(this))
            document.addEventListener('turbo:submit-end', this.handleTurboSubmitEnd.bind(this))
        }
    }

    removeEventListeners() {
        document.removeEventListener('submit', this.handleFormSubmit.bind(this), true)
        
        if (typeof window.Turbo !== 'undefined') {
            document.removeEventListener('turbo:submit-start', this.handleTurboSubmitStart.bind(this))
            document.removeEventListener('turbo:submit-end', this.handleTurboSubmitEnd.bind(this))
        }
    }

    // Handle regular form submissions
    handleFormSubmit(event) {
        this.generateCsrfToken(event.target)
    }

    // Handle Turbo form submissions
    handleTurboSubmitStart(event) {
        const headers = this.generateCsrfHeaders(event.detail.formSubmission.formElement)
        Object.keys(headers).forEach(key => {
            event.detail.formSubmission.fetchRequest.headers[key] = headers[key]
        })
    }

    // Handle Turbo form submission end
    handleTurboSubmitEnd(event) {
        this.removeCsrfToken(event.detail.formSubmission.formElement)
    }

    // Generate CSRF token for a form
    generateCsrfToken(formElement) {
        const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]')

        if (!csrfField) {
            return
        }

        let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value')
        let csrfToken = csrfField.value

        // Generate new token if needed
        if (!csrfCookie && this.isValidTokenName(csrfToken)) {
            csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie = csrfToken)
            csrfField.defaultValue = csrfToken = this.generateRandomToken()
            csrfField.dispatchEvent(new Event('change', { bubbles: true }))
        }

        // Set cookie if we have valid values
        if (csrfCookie && this.isValidToken(csrfToken)) {
            this.setCsrfCookie(csrfCookie, csrfToken)
        }
    }

    // Generate CSRF headers for Turbo requests
    generateCsrfHeaders(formElement) {
        const headers = {}
        const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]')

        if (!csrfField) {
            return headers
        }

        const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value')

        if (this.isValidToken(csrfField.value) && this.isValidTokenName(csrfCookie)) {
            headers[csrfCookie] = csrfField.value
        }

        return headers
    }

    // Remove CSRF token cookie
    removeCsrfToken(formElement) {
        const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]')

        if (!csrfField) {
            return
        }

        const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value')

        if (this.isValidToken(csrfField.value) && this.isValidTokenName(csrfCookie)) {
            this.removeCsrfCookie(csrfCookie, csrfField.value)
        }
    }

    // Set CSRF cookie
    setCsrfCookie(cookieName, token) {
        const cookie = `${cookieName}_${token}=${cookieName}; path=/; samesite=strict`
        const secureCookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie
        document.cookie = secureCookie
    }

    // Remove CSRF cookie
    removeCsrfCookie(cookieName, token) {
        const cookie = `${cookieName}_${token}=0; path=/; samesite=strict; max-age=0`
        const secureCookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie
        document.cookie = secureCookie
    }

    // Generate random token
    generateRandomToken() {
        const array = new Uint8Array(18)
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(array)
        } else if (window.msCrypto && window.msCrypto.getRandomValues) {
            window.msCrypto.getRandomValues(array)
        } else {
            // Fallback for older browsers
            for (let i = 0; i < array.length; i++) {
                array[i] = Math.floor(Math.random() * 256)
            }
        }
        return btoa(String.fromCharCode.apply(null, array))
    }

    // Validate token name (4-22 characters, alphanumeric, underscore, hyphen)
    isValidTokenName(name) {
        return /^[-_a-zA-Z0-9]{4,22}$/.test(name)
    }

    // Validate token (24+ characters, alphanumeric, underscore, hyphen, slash, plus)
    isValidToken(token) {
        return /^[-_/+a-zA-Z0-9]{24,}$/.test(token)
    }

    // Public method to refresh token
    refreshToken() {
        if (this.hasTokenTarget) {
            const newToken = this.generateRandomToken()
            this.tokenTarget.value = newToken
            this.tokenTarget.dispatchEvent(new Event('change', { bubbles: true }))
        }
    }

    // Public method to validate current token
    validateToken() {
        if (this.hasTokenTarget) {
            const token = this.tokenTarget.value
            return this.isValidToken(token)
        }
        return false
    }
}
