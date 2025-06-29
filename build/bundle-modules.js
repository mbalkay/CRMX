/**
 * JavaScript Module Bundler
 * Creates optimized bundles for different sections of the application
 */
const fs = require('fs');
const path = require('path');

// Define module bundles
const bundles = {
    'admin-core': [
        'assets/js/admin.js',
        'admin/js/insurance-crm-admin.js'
    ],
    'representative-panel': [
        'assets/js/representative-panel.js',
        'public/js/representative-panel.js'
    ],
    'dashboard-widgets': [
        'assets/js/dashboard-widgets.js'
    ]
};

function bundleModules() {
    Object.entries(bundles).forEach(([bundleName, files]) => {
        let bundleContent = '';
        
        // Add bundle header
        bundleContent += `/**\n * ${bundleName} Bundle\n * Generated: ${new Date().toISOString()}\n */\n\n`;
        
        files.forEach(file => {
            const filePath = path.join(__dirname, '..', file);
            if (fs.existsSync(filePath)) {
                const content = fs.readFileSync(filePath, 'utf8');
                bundleContent += `\n/* === ${file} === */\n`;
                bundleContent += content;
                bundleContent += '\n';
            } else {
                console.warn(`File not found: ${file}`);
            }
        });
        
        // Write bundle
        const outputPath = path.join(__dirname, '..', 'assets', 'js', `${bundleName}.bundle.js`);
        fs.writeFileSync(outputPath, bundleContent);
        console.log(`Bundle created: ${bundleName}.bundle.js`);
    });
}

// Create optimized loader for dynamic imports
function createModuleLoader() {
    const loaderContent = `
/**
 * Dynamic Module Loader
 * Supports lazy loading and conditional loading of modules
 */
(function(window) {
    'use strict';
    
    const InsuranceCRMLoader = {
        loadedModules: new Set(),
        
        // Load module dynamically
        async loadModule(moduleName, condition = true) {
            if (!condition || this.loadedModules.has(moduleName)) {
                return Promise.resolve();
            }
            
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = \`\${insuranceCrmConfig.assetsUrl}/js/\${moduleName}.min.js\`;
                script.onload = () => {
                    this.loadedModules.add(moduleName);
                    resolve();
                };
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },
        
        // Load CSS dynamically
        async loadCSS(cssName, condition = true) {
            if (!condition) return Promise.resolve();
            
            return new Promise((resolve, reject) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = \`\${insuranceCrmConfig.assetsUrl}/css/\${cssName}.min.css\`;
                link.onload = resolve;
                link.onerror = reject;
                document.head.appendChild(link);
            });
        },
        
        // Conditional loading based on page type
        loadForPage(pageType) {
            const moduleMap = {
                'dashboard': ['dashboard-widgets'],
                'representative-panel': ['representative-panel'],
                'admin': ['admin-core']
            };
            
            const modules = moduleMap[pageType] || [];
            return Promise.all(modules.map(module => this.loadModule(module)));
        }
    };
    
    // Global access
    window.InsuranceCRMLoader = InsuranceCRMLoader;
    
    // Auto-detect page type and load appropriate modules
    document.addEventListener('DOMContentLoaded', () => {
        const bodyClasses = document.body.className;
        let pageType = 'default';
        
        if (bodyClasses.includes('representative-panel')) {
            pageType = 'representative-panel';
        } else if (bodyClasses.includes('wp-admin')) {
            pageType = 'admin';
        } else if (bodyClasses.includes('dashboard')) {
            pageType = 'dashboard';
        }
        
        InsuranceCRMLoader.loadForPage(pageType);
    });
    
})(window);
`;
    
    const loaderPath = path.join(__dirname, '..', 'assets', 'js', 'module-loader.js');
    fs.writeFileSync(loaderPath, loaderContent.trim());
    console.log('Module loader created');
}

// Run if called directly
if (require.main === module) {
    bundleModules();
    createModuleLoader();
}

module.exports = { bundleModules, createModuleLoader };