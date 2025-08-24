import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
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
}
