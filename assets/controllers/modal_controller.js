import { Controller } from "@hotwired/stimulus"

/**
 * Reusable modal management Stimulus controller
 * Handles Bootstrap modal functionality and form management
 */
export default class extends Controller {
    static targets = ["form"]
    
    static values = {
        resetOnHide: { type: Boolean, default: true }
    }

    connect() {
        console.log("✅ Modal controller connected successfully")

        // Listen for Bootstrap modal events
        this.element.addEventListener('hidden.bs.modal', this.onModalHidden.bind(this))
        this.element.addEventListener('shown.bs.modal', this.onModalShown.bind(this))
    }

    disconnect() {
        this.element.removeEventListener('hidden.bs.modal', this.onModalHidden.bind(this))
        this.element.removeEventListener('shown.bs.modal', this.onModalShown.bind(this))
    }

    // Show modal
    show() {
        const modal = new bootstrap.Modal(this.element)
        modal.show()
    }

    // Hide modal
    hide() {
        const modal = bootstrap.Modal.getInstance(this.element)
        if (modal) {
            modal.hide()
        }
    }

    // Toggle modal
    toggle() {
        const modal = bootstrap.Modal.getInstance(this.element) || new bootstrap.Modal(this.element)
        modal.toggle()
    }

    // Modal event handlers
    onModalShown(event) {
        // Focus first input when modal is shown
        const firstInput = this.element.querySelector('input:not([type="hidden"]), textarea, select')
        if (firstInput) {
            firstInput.focus()
        }

        // Dispatch custom event
        this.dispatch('shown')
    }

    onModalHidden(event) {
        // Reset form if configured to do so
        if (this.resetOnHideValue && this.hasFormTarget) {
            this.resetForm()
        }

        // Dispatch custom event
        this.dispatch('hidden')
    }

    // Form handling
    submitForm(event) {
        event.preventDefault()
        
        if (!this.hasFormTarget) return

        // Dispatch custom event with form data
        const formData = new FormData(this.formTarget)
        const data = Object.fromEntries(formData.entries())
        
        this.dispatch('submit', { detail: { data, form: this.formTarget } })
    }

    resetForm() {
        if (this.hasFormTarget) {
            this.formTarget.reset()
            
            // Clear validation states
            this.clearValidation()
        }
    }

    clearValidation() {
        // Remove Bootstrap validation classes
        const elements = this.element.querySelectorAll('.is-valid, .is-invalid')
        elements.forEach(element => {
            element.classList.remove('is-valid', 'is-invalid')
        })

        // Clear validation feedback
        const feedback = this.element.querySelectorAll('.valid-feedback, .invalid-feedback')
        feedback.forEach(element => {
            element.textContent = ''
        })
    }

    // Validation helpers
    setFieldValid(fieldName, message = '') {
        const field = this.element.querySelector(`[name="${fieldName}"]`)
        if (field) {
            field.classList.remove('is-invalid')
            field.classList.add('is-valid')
            
            const feedback = this.element.querySelector(`[name="${fieldName}"] ~ .valid-feedback`)
            if (feedback) {
                feedback.textContent = message
            }
        }
    }

    setFieldInvalid(fieldName, message = '') {
        const field = this.element.querySelector(`[name="${fieldName}"]`)
        if (field) {
            field.classList.remove('is-valid')
            field.classList.add('is-invalid')
            
            const feedback = this.element.querySelector(`[name="${fieldName}"] ~ .invalid-feedback`)
            if (feedback) {
                feedback.textContent = message
            }
        }
    }

    // Utility methods
    showLoading(button) {
        if (button) {
            const originalText = button.innerHTML
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'
            button.disabled = true
            return originalText
        }
    }

    hideLoading(button, originalText) {
        if (button && originalText) {
            button.innerHTML = originalText
            button.disabled = false
        }
    }
}
