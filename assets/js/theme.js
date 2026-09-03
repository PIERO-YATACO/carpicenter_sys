(function() {
    // Theme and Color Palette Logic for Carpicenter
    
    const STORAGE_KEY_THEME = 'carpicenter_theme';
    const STORAGE_KEY_PRIMARY = 'carpicenter_primary';
    const STORAGE_KEY_PRIMARY_LIGHT = 'carpicenter_primary_light';
    const STORAGE_KEY_PRIMARY_DARK = 'carpicenter_primary_dark';

    // Default values (Light theme, Red palette)
    const defaults = {
        theme: 'light',
        primary: '#C62828',
        primaryLight: '#E53935',
        primaryDark: '#8B1A1A'
    };

    function applySettings() {
        const theme = localStorage.getItem(STORAGE_KEY_THEME) || defaults.theme;
        const primary = localStorage.getItem(STORAGE_KEY_PRIMARY) || defaults.primary;
        const primaryLight = localStorage.getItem(STORAGE_KEY_PRIMARY_LIGHT) || defaults.primaryLight;
        const primaryDark = localStorage.getItem(STORAGE_KEY_PRIMARY_DARK) || defaults.primaryDark;

        // Apply light theme as primary
        document.documentElement.setAttribute('data-theme', 'light');

        // Apply primary colors
        document.documentElement.style.setProperty('--primary', primary);
        document.documentElement.style.setProperty('--primary-light', primaryLight);
        document.documentElement.style.setProperty('--primary-dark', primaryDark);
        
        // Alpha versions for hovers/accents
        document.documentElement.style.setProperty('--primary-alpha-15', primary + '26'); // 15%
        document.documentElement.style.setProperty('--primary-alpha-10', primary + '1a'); // 10%
        document.documentElement.style.setProperty('--primary-alpha-5', primary + '0d');  // 5%
        
        // Update Chart.js defaults if available (global check)
        if (typeof Chart !== 'undefined') {
            Chart.defaults.color = theme === 'light' ? '#475569' : '#a0a0b0';
            Chart.defaults.borderColor = theme === 'light' ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)';
        }
    }

    // Run immediately to prevent flash
    applySettings();

    // Export functions to global scope for use in settings page
    window.CarpicenterTheme = {
        setTheme: function(theme) {
            localStorage.setItem(STORAGE_KEY_THEME, theme);
            applySettings();
        },
        setPrimaryColor: function(primary, light, dark) {
            localStorage.setItem(STORAGE_KEY_PRIMARY, primary);
            localStorage.setItem(STORAGE_KEY_PRIMARY_LIGHT, light);
            localStorage.setItem(STORAGE_KEY_PRIMARY_DARK, dark);
            applySettings();
        },
        saveConfig: function() {
            // This function will be called by the Save button
            const themeSelect = document.getElementById('theme-select');
            if (themeSelect) {
                const newTheme = themeSelect.value === 'Claro' ? 'light' : 'dark';
                this.setTheme(newTheme);
            }
            
            // Show feedback
            this.showToast('Configuración guardada correctamente');
        },
        showToast: function(message) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed; bottom: 20px; right: 20px;
                background: var(--primary); color: #fff;
                padding: 12px 24px; border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 9999; animation: fadeUp 0.3s ease;
                font-size: 0.9rem; font-weight: 500;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },
        getSettings: function() {
            return {
                theme: localStorage.getItem(STORAGE_KEY_THEME) || defaults.theme,
                primary: localStorage.getItem(STORAGE_KEY_PRIMARY) || defaults.primary
            };
        }
    };

    // Also listen for DOMContentLoaded to init UI elements if on configuration page
    document.addEventListener('DOMContentLoaded', function() {
        const themeSelect = document.getElementById('theme-select');
        if (themeSelect) {
            const current = CarpicenterTheme.getSettings();
            themeSelect.value = current.theme === 'light' ? 'Claro' : 'Oscuro (predeterminado)';
            
            themeSelect.addEventListener('change', function() {
                const newTheme = this.value === 'Claro' ? 'light' : 'dark';
                CarpicenterTheme.setTheme(newTheme);
            });
        }

        const colorOptions = document.querySelectorAll('.color-option');
        if (colorOptions.length > 0) {
            colorOptions.forEach(opt => {
                opt.addEventListener('click', function() {
                    const primary = this.dataset.primary;
                    const light = this.dataset.light;
                    const dark = this.dataset.dark;
                    
                    // Update UI selection
                    colorOptions.forEach(o => o.style.border = 'none');
                    this.style.border = '2px solid #fff';
                    
                    CarpicenterTheme.setPrimaryColor(primary, light, dark);
                });
                
                // Set initial selection border
                const current = CarpicenterTheme.getSettings();
                if (opt.dataset.primary === current.primary) {
                    opt.style.border = '2px solid #fff';
                }
            });
        }

        const saveBtn = document.getElementById('save-config-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                CarpicenterTheme.saveConfig();
            });
        }
    });
})();
