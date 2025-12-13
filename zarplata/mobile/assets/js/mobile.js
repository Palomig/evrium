/**
 * Mobile JS for Zarplata
 * Touch events, swipe navigation, menu handling
 */

// ===========================
// Menu Toggle
// ===========================

function initMenu() {
    const menuBtn = document.querySelector('.hamburger-btn');
    const menuOverlay = document.querySelector('.menu-overlay');
    const slideMenu = document.querySelector('.slide-menu');

    if (!menuBtn || !menuOverlay || !slideMenu) return;

    function openMenu() {
        menuOverlay.classList.add('active');
        slideMenu.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        menuOverlay.classList.remove('active');
        slideMenu.classList.remove('active');
        document.body.style.overflow = '';
    }

    menuBtn.addEventListener('click', openMenu);
    menuOverlay.addEventListener('click', closeMenu);

    // Swipe to close menu
    let touchStartX = 0;
    slideMenu.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });

    slideMenu.addEventListener('touchmove', (e) => {
        const diff = touchStartX - e.touches[0].clientX;
        if (diff > 50) {
            closeMenu();
        }
    }, { passive: true });
}

// ===========================
// Modal Handling
// ===========================

function initModals() {
    // Close modal on overlay click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    // Close buttons
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal');
            if (modal) closeModal(modal);
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Animate content
    setTimeout(() => {
        modal.querySelector('.modal-content')?.classList.add('show');
    }, 10);
}

function closeModal(modal) {
    if (typeof modal === 'string') {
        modal = document.getElementById(modal);
    }
    if (!modal) return;

    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// ===========================
// Day Swiper for Schedule
// ===========================

class DaySwiper {
    constructor(container, options = {}) {
        this.container = container;
        this.tabs = container.querySelectorAll('.schedule-tab');
        this.panels = container.querySelectorAll('.schedule-day-panel');
        this.currentIndex = options.initialIndex || 0;
        this.onDayChange = options.onDayChange || (() => {});

        this.touchStartX = 0;
        this.touchEndX = 0;

        this.init();
    }

    init() {
        // Tab clicks
        this.tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => this.goToDay(index));
        });

        // Swipe on panels container
        const panelsContainer = this.container.querySelector('.schedule-panels');
        if (panelsContainer) {
            panelsContainer.addEventListener('touchstart', (e) => {
                this.touchStartX = e.touches[0].clientX;
            }, { passive: true });

            panelsContainer.addEventListener('touchend', (e) => {
                this.touchEndX = e.changedTouches[0].clientX;
                this.handleSwipe();
            }, { passive: true });
        }

        // Set initial day
        this.goToDay(this.currentIndex, false);
    }

    handleSwipe() {
        const diff = this.touchStartX - this.touchEndX;
        const threshold = 50;

        if (Math.abs(diff) < threshold) return;

        if (diff > 0 && this.currentIndex < this.panels.length - 1) {
            // Swipe left - next day
            this.goToDay(this.currentIndex + 1);
        } else if (diff < 0 && this.currentIndex > 0) {
            // Swipe right - previous day
            this.goToDay(this.currentIndex - 1);
        }
    }

    goToDay(index, animate = true) {
        if (index < 0 || index >= this.panels.length) return;

        // Update tabs
        this.tabs.forEach((tab, i) => {
            tab.classList.toggle('active', i === index);
        });

        // Scroll active tab into view
        this.tabs[index]?.scrollIntoView({
            behavior: animate ? 'smooth' : 'auto',
            inline: 'center',
            block: 'nearest'
        });

        // Update panels
        this.panels.forEach((panel, i) => {
            panel.classList.toggle('active', i === index);
            panel.style.display = i === index ? 'block' : 'none';
        });

        this.currentIndex = index;
        this.onDayChange(index);
    }

    getCurrentDay() {
        return this.currentIndex;
    }
}

// ===========================
// Pull to Refresh
// ===========================

class PullToRefresh {
    constructor(container, onRefresh) {
        this.container = container;
        this.onRefresh = onRefresh;
        this.startY = 0;
        this.pulling = false;
        this.threshold = 80;

        this.init();
    }

    init() {
        this.container.addEventListener('touchstart', (e) => {
            if (this.container.scrollTop === 0) {
                this.startY = e.touches[0].clientY;
                this.pulling = true;
            }
        }, { passive: true });

        this.container.addEventListener('touchmove', (e) => {
            if (!this.pulling) return;

            const diff = e.touches[0].clientY - this.startY;
            if (diff > 0 && diff < this.threshold * 2) {
                // Visual feedback could be added here
            }
        }, { passive: true });

        this.container.addEventListener('touchend', (e) => {
            if (!this.pulling) return;

            const diff = e.changedTouches[0].clientY - this.startY;
            if (diff > this.threshold) {
                this.onRefresh();
            }

            this.pulling = false;
        }, { passive: true });
    }
}

// ===========================
// Toast Notifications
// ===========================

function showToast(message, type = 'success', duration = 3000) {
    // Remove existing toasts
    document.querySelectorAll('.toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span class="toast-message">${message}</span>`;

    document.body.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);

    // Auto hide
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ===========================
// API Helper
// ===========================

async function apiRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
    };

    const finalOptions = { ...defaultOptions, ...options };

    try {
        const response = await fetch(url, finalOptions);
        const data = await response.json();

        if (!data.success && data.error) {
            throw new Error(data.error);
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        showToast(error.message || 'Network error', 'error');
        throw error;
    }
}

// ===========================
// Loading Overlay
// ===========================

function showLoading() {
    let overlay = document.querySelector('.loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// ===========================
// Format Helpers
// ===========================

function formatMoney(amount) {
    return new Intl.NumberFormat('ru-RU').format(amount) + ' ₽';
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short'
    });
}

function formatTime(timeStr) {
    return timeStr.substring(0, 5);
}

// ===========================
// Initialization
// ===========================

document.addEventListener('DOMContentLoaded', () => {
    initMenu();
    initModals();

    // Prevent overscroll on iOS
    document.body.addEventListener('touchmove', (e) => {
        if (document.body.style.overflow === 'hidden') {
            e.preventDefault();
        }
    }, { passive: false });
});

// Export for use in page scripts
window.MobileApp = {
    DaySwiper,
    PullToRefresh,
    showToast,
    showLoading,
    hideLoading,
    apiRequest,
    formatMoney,
    formatDate,
    formatTime,
    openModal,
    closeModal
};
