import { Controller } from "@hotwired/stimulus"

/**
 * Artist index management Stimulus controller
 * Replaces all inline JavaScript functionality
 */
export default class extends Controller {
    static targets = []

    connect() {
        console.log("Artist index controller connected")
    }

    // Artist index management functions can be added here as needed
}
