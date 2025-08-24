import { Controller } from "@hotwired/stimulus"

/**
 * Locale management Stimulus controller
 * Replaces all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = []

    connect() {
        console.log("Locale controller connected")
    }

    // Locale management functions can be added here as needed
}
