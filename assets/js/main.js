// Main JavaScript File

// Mobile Navigation Toggle
document.addEventListener('DOMContentLoaded', function () {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('active');
        });
    }

    // Close menu when clicking outside
    document.addEventListener('click', function (event) {
        if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
            navMenu.classList.remove('active');
        }
    });

    // User Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('userSidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar when clicking on a link
    if (sidebar) {
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function () {
                setTimeout(closeSidebar, 300);
            });
        });
    }

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href.length > 1) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Staff Sidebar Toggle
    const staffToggle = document.getElementById('staffSidebarToggle');
    const staffSidebar = document.querySelector('.staff-sidebar');

    if (staffToggle && staffSidebar) {
        staffToggle.addEventListener('click', function () {
            staffSidebar.classList.toggle('open');
        });

        // Close when clicking main content
        document.querySelector('.staff-main-content').addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                staffSidebar.classList.remove('open');
            }
        });
    }

    // Admin Sidebar Toggle
    const adminToggle = document.getElementById('adminSidebarToggle');
    const adminSidebar = document.querySelector('.admin-sidebar');

    if (adminToggle && adminSidebar) {
        adminToggle.addEventListener('click', function () {
            adminSidebar.classList.toggle('active');
        });

        // Close when clicking main content
        document.querySelector('.admin-main-content').addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                adminSidebar.classList.remove('active');
            }
        });
    }

    // Handle hash in URL on page load for smooth scrolling
    if (window.location.hash) {
        setTimeout(function () {
            const target = document.querySelector(window.location.hash);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }, 300);
    }
});

// Form Validation
function validateFormById(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });

    return isValid;
}

// Show/Hide Password
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Auto-refresh the page at a specified interval
 * @param {number} seconds - Interval in seconds
 */
function startAutoRefresh(seconds) {
    if (!seconds || seconds <= 0) return;

    setInterval(() => {
        // Check if there are any open modals or active forms before refreshing
        const activeModal = document.querySelector('.modal.active, .modal.show');
        const activeInput = document.activeElement;
        const isTyping = activeInput && (activeInput.tagName === 'INPUT' || activeInput.tagName === 'TEXTAREA');

        if (!activeModal && !isTyping) {
            location.reload();
        }
    }, seconds * 1000);
}

// Contact Form Submission Handler
document.addEventListener('DOMContentLoaded', function () {
    const contactForm = document.querySelector('.contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            fetch('api/contact.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        contactForm.reset();
                    } else {
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again later.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
        });
    }
});
