// Mobile Navigation Toggle
document.addEventListener('DOMContentLoaded', function () {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            navMenu.classList.toggle('active');

            // Change icon
            const icon = navToggle.querySelector('i');
            if (navMenu.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Close menu when clicking on a link
       // Close menu when clicking on a link (except dropdown toggles)
const navLinks = navMenu.querySelectorAll('a:not(.dropdown-toggle)');
navLinks.forEach(link => {
    link.addEventListener('click', function () {
        // Don't close if clicking dropdown toggle
        if (this.classList.contains('dropdown-toggle')) return;

        navMenu.classList.remove('active');
        const icon = navToggle.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
});

// Handle dropdowns (DESKTOP + MOBILE - FIXED)
const dropdowns = document.querySelectorAll('.dropdown');

dropdowns.forEach(dropdown => {
    const toggle = dropdown.querySelector('.dropdown-toggle');
    const menu = dropdown.querySelector('.dropdown-menu');

    if (!toggle || !menu) return;

    // Toggle dropdown on click
    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Close all other dropdowns
        dropdowns.forEach(d => {
            if (d !== dropdown) d.classList.remove('active');
        });

        dropdown.classList.toggle('active');
    });

    // Keep dropdown open when hovering the menu
    menu.addEventListener('mouseenter', () => {
        dropdown.classList.add('active');
    });

    // Add delay before closing
    dropdown.addEventListener('mouseleave', () => {
        setTimeout(() => {
            if (!dropdown.matches(':hover')) {
                dropdown.classList.remove('active');
            }
        }, 300);
    });
});

// Close dropdowns & mobile menu when clicking outside
document.addEventListener('click', function (event) {
    // Close dropdowns if click outside
    if (!event.target.closest('.dropdown')) {
        dropdowns.forEach(d => d.classList.remove('active'));
    }

    // Close mobile menu if click outside navbar
    if (
        navMenu &&
        navMenu.classList.contains('active') &&
        !event.target.closest('.navbar')
    ) {
        navMenu.classList.remove('active');

        const icon = navToggle.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});
    }
});

// Form validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[0-9]{10,13}$/;
    return re.test(phone.replace(/[\s\-]/g, ''));
}

// Alert auto-close
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

// Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
}

// Chart initialization for infographics (if Chart.js is loaded)
function initializeChart(elementId, type, data, options) {
    const ctx = document.getElementById(elementId);
    if (ctx && typeof Chart !== 'undefined') {
        return new Chart(ctx, {
            type: type,
            data: data,
            options: options
        });
    }
}

// Price calculator for reservation
function calculateTotalPrice() {
    const servicePrice = parseFloat(document.getElementById('service_price')?.value || 0);
    const numPassengers = parseInt(document.getElementById('num_passengers')?.value || 1);
    const totalPrice = servicePrice * numPassengers;

    const totalElement = document.getElementById('total_price');
    if (totalElement) {
        totalElement.textContent = formatCurrency(totalPrice);
    }

    const hiddenInput = document.getElementById('hidden_total_price');
    if (hiddenInput) {
        hiddenInput.value = totalPrice;
    }
}

// Format currency
function formatCurrency(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

// Date validation
function validateDate(dateString) {
    const selectedDate = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return selectedDate >= today;
}

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Print function
function printContent(elementId) {
    const content = document.getElementById(elementId);
    if (content) {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="assets/css/style.css">');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
}

// Loading overlay
function showLoading() {
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner"></div>';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}