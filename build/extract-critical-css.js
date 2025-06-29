/**
 * Critical CSS Extraction Tool
 * Extracts critical CSS for above-the-fold content
 */
const fs = require('fs');
const path = require('path');

// Define critical CSS patterns for WordPress admin and frontend
const criticalPatterns = [
    // WordPress core styles
    '.wp-admin', '.wp-core-ui', '.widefat',
    // Plugin specific critical styles
    '.insurance-crm-wrap', '.insurance-crm-header', '.insurance-crm-stats',
    '.insurance-crm-widget', '.insurance-crm-dashboard', '.insurance-crm-loading',
    // Representative panel critical
    '.representative-panel-container', '.nav-menu', '.dashboard-grid',
    // Responsive breakpoints
    '@media (max-width: 768px)', '@media (max-width: 1024px)'
];

function extractCriticalCSS() {
    const cssFiles = [
        'assets/css/admin.css',
        'assets/css/representative-panel-global.css',
        'public/css/representative-panel.css'
    ];

    cssFiles.forEach(file => {
        const filePath = path.join(__dirname, '..', file);
        if (fs.existsSync(filePath)) {
            const cssContent = fs.readFileSync(filePath, 'utf8');
            const critical = extractCriticalFromContent(cssContent);
            
            const outputPath = filePath.replace('.css', '.critical.css');
            fs.writeFileSync(outputPath, critical);
            console.log(`Critical CSS extracted for ${file}`);
        }
    });
}

function extractCriticalFromContent(cssContent) {
    // Simple critical CSS extraction - in production, use tools like critical or penthouse
    const lines = cssContent.split('\n');
    const criticalLines = [];
    let inCriticalBlock = false;
    let braceCount = 0;

    lines.forEach(line => {
        const trimmed = line.trim();
        
        // Check if line contains critical patterns
        const isCritical = criticalPatterns.some(pattern => 
            trimmed.includes(pattern) || 
            (pattern.startsWith('@media') && trimmed.startsWith('@media'))
        );

        if (isCritical) {
            inCriticalBlock = true;
        }

        if (inCriticalBlock) {
            criticalLines.push(line);
            
            // Count braces to know when rule ends
            braceCount += (trimmed.match(/\{/g) || []).length;
            braceCount -= (trimmed.match(/\}/g) || []).length;
            
            if (braceCount === 0 && trimmed.includes('}')) {
                inCriticalBlock = false;
            }
        }
    });

    return criticalLines.join('\n');
}

// Run if called directly
if (require.main === module) {
    extractCriticalCSS();
}

module.exports = { extractCriticalCSS };