/**
 * Diagnostic Check for MemberPress AI Chat Integration
 * 
 * This script performs basic checks without using browser automation
 */

const fs = require('fs');
const path = require('path');

// Colors for console output
const colors = {
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    reset: '\x1b[0m'
};

function log(message, color = 'reset') {
    console.log(colors[color] + message + colors.reset);
}

function checkFileExists(filePath) {
    try {
        return fs.existsSync(filePath);
    } catch (error) {
        return false;
    }
}

function checkFileContent(filePath, searchString) {
    try {
        if (!checkFileExists(filePath)) return false;
        const content = fs.readFileSync(filePath, 'utf8');
        return content.includes(searchString);
    } catch (error) {
        return false;
    }
}

async function runDiagnostics() {
    log('\n=== MemberPress AI Chat Integration Diagnostic ===\n', 'blue');
    
    const pluginDir = process.cwd();
    const results = {
        fileChecks: [],
        codeChecks: [],
        configChecks: []
    };
    
    // File existence checks
    log('1. File Structure Checks:', 'yellow');
    
    const criticalFiles = [
        'src/MemberPressCoursesCopilot/Services/NewCourseIntegration.php',
        'src/MemberPressCoursesCopilot/Services/CourseAjaxService.php',
        'src/MemberPressCoursesCopilot/Container/ServiceProvider.php',
        'src/MemberPressCoursesCopilot/Plugin.php',
        'templates/components/metabox/course-edit-ai-assistant.php',
        'assets/js/course-edit-ai-chat.js'
    ];
    
    criticalFiles.forEach(file => {
        const fullPath = path.join(pluginDir, file);
        const exists = checkFileExists(fullPath);
        results.fileChecks.push({ file, exists });
        log(`   ${exists ? '✅' : '❌'} ${file}`, exists ? 'green' : 'red');
    });
    
    // Code integration checks
    log('\n2. Code Integration Checks:', 'yellow');
    
    const codeChecks = [
        {
            file: 'src/MemberPressCoursesCopilot/Container/ServiceProvider.php',
            check: 'NewCourseIntegration',
            description: 'NewCourseIntegration service registration'
        },
        {
            file: 'src/MemberPressCoursesCopilot/Services/CourseAjaxService.php',
            check: 'mpcc_new_ai_chat',
            description: 'AJAX handler for new AI chat'
        },
        {
            file: 'src/MemberPressCoursesCopilot/Services/NewCourseIntegration.php',
            check: 'add_meta_box',
            description: 'Metabox registration function'
        },
        {
            file: 'src/MemberPressCoursesCopilot/Plugin.php',
            check: '$new_course_integration->init()',
            description: 'NewCourseIntegration initialization'
        }
    ];
    
    codeChecks.forEach(check => {
        const fullPath = path.join(pluginDir, check.file);
        const hasCode = checkFileContent(fullPath, check.check);
        results.codeChecks.push({ ...check, hasCode });
        log(`   ${hasCode ? '✅' : '❌'} ${check.description}`, hasCode ? 'green' : 'red');
    });
    
    // WordPress hooks and actions check
    log('\n3. WordPress Integration Checks:', 'yellow');
    
    const hookChecks = [
        {
            file: 'src/MemberPressCoursesCopilot/Services/NewCourseIntegration.php',
            check: "add_action('add_meta_boxes'",
            description: 'Metabox hook registration'
        },
        {
            file: 'src/MemberPressCoursesCopilot/Services/CourseAjaxService.php',
            check: "add_action('wp_ajax_mpcc_new_ai_chat'",
            description: 'AJAX hook registration'
        }
    ];
    
    hookChecks.forEach(check => {
        const fullPath = path.join(pluginDir, check.file);
        const hasHook = checkFileContent(fullPath, check.check);
        results.configChecks.push({ ...check, hasHook });
        log(`   ${hasHook ? '✅' : '❌'} ${check.description}`, hasHook ? 'green' : 'red');
    });
    
    // JavaScript checks
    log('\n4. JavaScript Integration Checks:', 'yellow');
    
    const jsChecks = [
        {
            file: 'templates/components/metabox/course-edit-ai-assistant.php',
            check: 'mpcc-open-ai-chat',
            description: 'AI Chat button ID'
        },
        {
            file: 'templates/components/metabox/course-edit-ai-assistant.php',
            check: 'mpcc-ai-send',
            description: 'Send button ID'
        },
        {
            file: 'templates/components/metabox/course-edit-ai-assistant.php',
            check: 'mpcc_new_ai_chat',
            description: 'AJAX action name in template'
        }
    ];
    
    jsChecks.forEach(check => {
        const fullPath = path.join(pluginDir, check.file);
        const hasCode = checkFileContent(fullPath, check.check);
        log(`   ${hasCode ? '✅' : '❌'} ${check.description}`, hasCode ? 'green' : 'red');
    });
    
    // Summary
    log('\n=== Diagnostic Summary ===\n', 'blue');
    
    const totalChecks = results.fileChecks.length + results.codeChecks.length + results.configChecks.length + jsChecks.length;
    const passedChecks = results.fileChecks.filter(c => c.exists).length + 
                        results.codeChecks.filter(c => c.hasCode).length + 
                        results.configChecks.filter(c => c.hasHook).length +
                        jsChecks.filter(c => checkFileContent(path.join(pluginDir, c.file), c.check)).length;
    
    log(`Passed: ${passedChecks}/${totalChecks} checks`, passedChecks === totalChecks ? 'green' : 'yellow');
    
    if (passedChecks === totalChecks) {
        log('\n✅ All diagnostic checks passed!', 'green');
        log('The AI Chat integration should be working. Next steps:', 'green');
        log('1. Ensure the plugin is activated in WordPress', 'reset');
        log('2. Check that you have admin access to edit courses', 'reset');  
        log('3. Navigate to a course edit page (post_type=mpcs-course)', 'reset');
        log('4. Look for "AI Course Assistant" metabox in the sidebar', 'reset');
    } else {
        log('\n⚠️  Some checks failed. Issues found:', 'yellow');
        
        // Detailed failure report
        results.fileChecks.filter(c => !c.exists).forEach(c => {
            log(`❌ Missing file: ${c.file}`, 'red');
        });
        
        results.codeChecks.filter(c => !c.hasCode).forEach(c => {
            log(`❌ Missing code: ${c.description} in ${c.file}`, 'red');
        });
        
        results.configChecks.filter(c => !c.hasHook).forEach(c => {
            log(`❌ Missing hook: ${c.description} in ${c.file}`, 'red');
        });
    }
    
    // Additional diagnostics
    log('\n=== Additional Information ===\n', 'blue');
    
    // Check if WordPress is accessible
    const wpConfigPath = path.join(pluginDir, '../../../../wp-config.php');
    const hasWpConfig = checkFileExists(wpConfigPath);
    log(`WordPress config accessible: ${hasWpConfig ? '✅' : '❌'}`, hasWpConfig ? 'green' : 'red');
    
    // Check plugin main file
    const mainPluginFile = path.join(pluginDir, 'memberpress-courses-copilot.php');
    const hasMainFile = checkFileExists(mainPluginFile);
    log(`Main plugin file: ${hasMainFile ? '✅' : '❌'}`, hasMainFile ? 'green' : 'red');
    
    if (hasMainFile) {
        const hasActivationHook = checkFileContent(mainPluginFile, 'register_activation_hook');
        log(`Plugin activation hook: ${hasActivationHook ? '✅' : '❌'}`, hasActivationHook ? 'green' : 'red');
    }
    
    // Check for composer autoload
    const composerAutoload = path.join(pluginDir, 'vendor/autoload.php');
    const hasAutoload = checkFileExists(composerAutoload);
    log(`Composer autoload: ${hasAutoload ? '✅' : '❌'}`, hasAutoload ? 'green' : 'red');
    
    if (!hasAutoload) {
        log('\n⚠️  Missing Composer autoload. Run: composer install', 'yellow');
    }
    
    log('\n=== Testing Instructions ===\n', 'blue');
    log('To manually test the AI Chat integration:', 'reset');
    log('1. Log into WordPress admin as an administrator', 'reset');
    log('2. Go to: /wp-admin/edit.php?post_type=mpcs-course', 'reset');
    log('3. Edit an existing course or create a new one', 'reset');
    log('4. Look for "AI Course Assistant" metabox in the right sidebar', 'reset');
    log('5. Click "Open AI Chat" button to expand the interface', 'reset');
    log('6. Type a message and click "Send Message"', 'reset');
    log('7. Check browser console for any JavaScript errors', 'reset');
    log('8. Check WordPress error logs for any PHP errors', 'reset');
    
    log('\n=== Debug URLs ===\n', 'blue');
    log('If running locally, try these URLs:', 'reset');
    log('• http://localhost:10044/wp-admin/ (port 10044)', 'reset');
    log('• http://localhost:10039/wp-admin/ (port 10039)', 'reset');
    log('• http://memberpress-testing.local/wp-admin/', 'reset');
    log('• Check Local by Flywheel for correct URL and port', 'reset');
}

// Run diagnostics
runDiagnostics().catch(console.error);