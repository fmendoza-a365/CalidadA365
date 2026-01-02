import './bootstrap';
import Alpine from 'alpinejs';

// Theme Management
const ThemeManager = {
    init() {
        // Check for saved theme or system preference
        const savedTheme = localStorage.getItem('theme');
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },

    toggle() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        return isDark;
    },

    get isDark() {
        return document.documentElement.classList.contains('dark');
    }
};

// Initialize theme before Alpine
ThemeManager.init();

// Make available globally
window.ThemeManager = ThemeManager;

// Alpine.js Data Components
Alpine.data('sidebar', () => ({
    open: window.innerWidth >= 1024,
    
    init() {
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                this.open = true;
            }
        });
    },

    toggle() {
        this.open = !this.open;
    }
}));

Alpine.data('themeToggle', () => ({
    isDark: ThemeManager.isDark,

    toggle() {
        this.isDark = ThemeManager.toggle();
    }
}));

Alpine.data('dropdown', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    },

    close() {
        this.open = false;
    }
}));

window.Alpine = Alpine;
Alpine.start();
