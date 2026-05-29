/**
 * Utility Functions for Beaute Aesthetic Studio
 * Provides reusable JavaScript functions for common UI operations
 */

// ============================================================================
// LOADING STATES
// ============================================================================

/**
 * Show loading spinner in an element
 * @param {HTMLElement|string} element - Element or selector
 * @param {string} text - Loading text
 */
function showLoading(element, text = 'Loading...') {
    const el = typeof element === 'string' ? document.querySelector(element) : element;
    if (!el) return;

    el.classList.add('loading');
    el.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <span>${text}</span>
        </div>
    `;
}

/**
 * Hide loading spinner and restore content
 * @param {HTMLElement|string} element - Element or selector
 * @param {string} content - Content to display
 */
function hideLoading(element, content = '') {
    const el = typeof element === 'string' ? document.querySelector(element) : element;
    if (!el) return;

    el.classList.remove('loading');
    if (content) {
        el.innerHTML = content;
    }
}

/**
 * Show loading overlay on entire page
 */
function showPageLoading() {
    let overlay = document.getElementById('page-loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'page-loading-overlay';
        overlay.className = 'page-loading-overlay';
        overlay.innerHTML = `
            <div class="page-loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Please wait...</p>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    overlay.classList.add('active');
}

/**
 * Hide page loading overlay
 */
function hidePageLoading() {
    const overlay = document.getElementById('page-loading-overlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================

/**
 * Show toast notification
 * @param {string} message - Message to display
 * @param {string} type - Type: success, error, warning, info
 * @param {number} duration - Duration in milliseconds (0 = no auto-dismiss)
 */
function showToast(message, type = 'info', duration = 3000) {
    // Create toast container if it doesn't exist
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };

    toast.innerHTML = `
        <i class="fas fa-${icons[type] || 'info-circle'}"></i>
        <span>${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);

    // Auto-dismiss
    if (duration > 0) {
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}

// ============================================================================
// CONFIRMATION DIALOGS
// ============================================================================

/**
 * Show confirmation dialog
 * @param {string} message - Confirmation message
 * @param {string} title - Dialog title
 * @returns {Promise<boolean>} - Resolves to true if confirmed, false if cancelled
 */
function confirmDialog(message, title = 'Confirm Action') {
    return new Promise((resolve) => {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';

        // Create dialog
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.innerHTML = `
            <div class="confirm-header">
                <h3>${title}</h3>
            </div>
            <div class="confirm-body">
                <p>${message}</p>
            </div>
            <div class="confirm-footer">
                <button class="btn btn-outline" data-action="cancel">Cancel</button>
                <button class="btn btn-primary" data-action="confirm">Confirm</button>
            </div>
        `;

        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        // Show with animation
        setTimeout(() => overlay.classList.add('active'), 10);

        // Handle button clicks
        overlay.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;

            if (action) {
                overlay.classList.remove('active');
                setTimeout(() => overlay.remove(), 300);
                resolve(action === 'confirm');
            }
        });
    });
}

// ============================================================================
// FORM VALIDATION
// ============================================================================

/**
 * Validate form field
 * @param {HTMLInputElement} field - Form field to validate
 * @returns {boolean} - True if valid
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');

    // Clear previous errors
    clearFieldError(field);

    // Required validation
    if (required && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }

    // Skip other validations if empty and not required
    if (!value && !required) {
        return true;
    }

    // Email validation
    if (type === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Please enter a valid email address');
            return false;
        }
    }

    // Phone validation
    if (field.name === 'phone' || type === 'tel') {
        const phoneRegex = /^[\+\d\s\-\(\)]+$/;
        if (!phoneRegex.test(value) || value.replace(/\D/g, '').length < 7) {
            showFieldError(field, 'Please enter a valid phone number');
            return false;
        }
    }

    // Min length validation
    if (field.hasAttribute('minlength')) {
        const minLength = parseInt(field.getAttribute('minlength'));
        if (value.length < minLength) {
            showFieldError(field, `Minimum ${minLength} characters required`);
            return false;
        }
    }

    // Password confirmation
    if (field.name === 'confirm_password') {
        const password = document.querySelector('[name="password"]');
        if (password && value !== password.value) {
            showFieldError(field, 'Passwords do not match');
            return false;
        }
    }

    return true;
}

/**
 * Show field error message
 * @param {HTMLInputElement} field - Form field
 * @param {string} message - Error message
 */
function showFieldError(field, message) {
    field.classList.add('error');

    let errorEl = field.parentElement.querySelector('.field-error');
    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'field-error';
        field.parentElement.appendChild(errorEl);
    }

    errorEl.textContent = message;
}

/**
 * Clear field error
 * @param {HTMLInputElement} field - Form field
 */
function clearFieldError(field) {
    field.classList.remove('error');
    const errorEl = field.parentElement.querySelector('.field-error');
    if (errorEl) {
        errorEl.remove();
    }
}

/**
 * Validate entire form
 * @param {HTMLFormElement} form - Form to validate
 * @returns {boolean} - True if all fields are valid
 */
function validateForm(form) {
    const fields = form.querySelectorAll('input, textarea, select');
    let isValid = true;

    fields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });

    return isValid;
}

// ============================================================================
// AJAX HELPERS
// ============================================================================

/**
 * Make AJAX request with error handling
 * @param {string} url - Request URL
 * @param {object} options - Fetch options
 * @returns {Promise} - Fetch promise
 */
async function ajaxRequest(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        return data;
    } catch (error) {
        console.error('AJAX Error:', error);
        showToast(error.message || 'An error occurred. Please try again.', 'error');
        throw error;
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Debounce function calls
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} - Debounced function
 */
function debounce(func, wait = 300) {
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
 * Format currency
 * @param {number} amount - Amount to format
 * @returns {string} - Formatted currency
 */
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

/**
 * Smooth scroll to element
 * @param {string} selector - Element selector
 * @param {number} offset - Offset from top
 */
function scrollToElement(selector, offset = 0) {
    const element = document.querySelector(selector);
    if (element) {
        const top = element.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({ top, behavior: 'smooth' });
    }
}

/**
 * Copy text to clipboard
 * @param {string} text - Text to copy
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast('Copied to clipboard!', 'success', 2000);
    } catch (error) {
        console.error('Copy failed:', error);
        showToast('Failed to copy to clipboard', 'error');
    }
}

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Add real-time validation to all forms
    document.querySelectorAll('form').forEach(form => {
        const fields = form.querySelectorAll('input, textarea, select');

        fields.forEach(field => {
            // Validate on blur
            field.addEventListener('blur', () => validateField(field));

            // Clear error on input
            field.addEventListener('input', () => {
                if (field.classList.contains('error')) {
                    clearFieldError(field);
                }
            });
        });

        // Validate on submit
        form.addEventListener('submit', (e) => {
            if (!validateForm(form)) {
                e.preventDefault();
                showToast('Please fix the errors in the form', 'error');

                // Focus first error field
                const firstError = form.querySelector('.error');
                if (firstError) {
                    firstError.focus();
                }
            }
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#!') {
                e.preventDefault();
                scrollToElement(href, 80);
            }
        });
    });
});
