const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

(async () => {
    console.log('Starting icon spacing diagnostic...');
    
    const browser = await puppeteer.launch({
        headless: false, // Show browser for debugging
        devtools: true,  // Open DevTools
        args: ['--window-size=1920,1080']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    
    try {
        // Navigate to the course editor page
        const url = 'http://localhost:10044/wp-admin/admin.php?page=mpcc-course-editor&session=mpcc_session_1756384040059_1tfpbe3ok';
        console.log(`Navigating to: ${url}`);
        
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
        
        // Wait for login if needed
        if (await page.$('#user_login')) {
            console.log('Login required...');
            await page.type('#user_login', 'admin');
            await page.type('#user_pass', 'password');
            await page.click('#wp-submit');
            await page.waitForNavigation({ waitUntil: 'networkidle2' });
            console.log('Login successful, navigating to course editor...');
            
            // Navigate again after login
            await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
        }
        
        // Wait for the page to load - use a more flexible selector
        try {
            await page.waitForSelector('.mpcc-course-editor, #mpcc-course-editor, [data-course-editor]', { timeout: 10000 });
            console.log('Course editor page loaded');
        } catch (e) {
            console.log('Course editor selector not found, checking if page loaded anyway...');
            
            // Debug: Check what's on the page
            const pageTitle = await page.title();
            const pageURL = page.url();
            console.log(`Current page title: ${pageTitle}`);
            console.log(`Current URL: ${pageURL}`);
            
            // Check for any course-related elements
            const courseElements = await page.evaluate(() => {
                return {
                    hasSections: !!document.querySelector('.mpcc-section'),
                    hasLessons: !!document.querySelector('.mpcc-lesson'),
                    hasActions: !!document.querySelector('.mpcc-section-actions, .mpcc-lesson-actions'),
                    bodyClasses: document.body.className,
                    mainContent: document.querySelector('#wpbody-content') ? 'WordPress admin content found' : 'No admin content'
                };
            });
            console.log('Page elements:', courseElements);
            
            // Continue anyway to see what's on the page
        }
        
        // Create screenshots directory if it doesn't exist
        const screenshotsDir = path.join(__dirname, 'screenshots', 'icon-spacing');
        if (!fs.existsSync(screenshotsDir)) {
            fs.mkdirSync(screenshotsDir, { recursive: true });
        }
        
        // Take a full page screenshot
        await page.screenshot({ 
            path: path.join(screenshotsDir, 'full-page.png'),
            fullPage: true 
        });
        console.log('Full page screenshot saved');
        
        // Check CSS files loaded
        const cssFiles = await page.evaluate(() => {
            const stylesheets = Array.from(document.styleSheets);
            return stylesheets
                .filter(sheet => sheet.href)
                .map(sheet => {
                    try {
                        const rules = Array.from(sheet.cssRules || []);
                        return {
                            href: sheet.href,
                            rulesCount: rules.length,
                            hasIconRules: rules.some(rule => 
                                rule.selectorText && 
                                (rule.selectorText.includes('mpcc-section-actions') || 
                                 rule.selectorText.includes('mpcc-lesson-actions'))
                            )
                        };
                    } catch (e) {
                        return {
                            href: sheet.href,
                            error: e.message
                        };
                    }
                });
        });
        
        console.log('\nLoaded CSS Files:');
        cssFiles.forEach(file => {
            console.log(`- ${file.href}`);
            if (file.hasIconRules) {
                console.log('  ^ Contains icon rules');
            }
            if (file.error) {
                console.log(`  Error: ${file.error}`);
            }
        });
        
        // Check if specific CSS file is loaded
        const courseEditorCSS = cssFiles.find(file => 
            file.href && file.href.includes('course-editor-page.css')
        );
        console.log('\nCourse Editor CSS:', courseEditorCSS ? 'LOADED' : 'NOT FOUND');
        
        // Check section action icons
        const sectionActions = await page.evaluate(() => {
            const elements = document.querySelectorAll('.mpcc-section-actions');
            return Array.from(elements).map(el => {
                const computed = window.getComputedStyle(el);
                const buttons = el.querySelectorAll('button');
                return {
                    className: el.className,
                    display: computed.display,
                    gap: computed.gap,
                    flexDirection: computed.flexDirection,
                    alignItems: computed.alignItems,
                    marginRight: computed.marginRight,
                    buttonCount: buttons.length,
                    buttons: Array.from(buttons).map(btn => ({
                        className: btn.className,
                        marginLeft: window.getComputedStyle(btn).marginLeft,
                        marginRight: window.getComputedStyle(btn).marginRight
                    }))
                };
            });
        });
        
        console.log('\n.mpcc-section-actions styles:');
        sectionActions.forEach((action, index) => {
            console.log(`Element ${index + 1}:`);
            console.log(`  Display: ${action.display}`);
            console.log(`  Gap: ${action.gap}`);
            console.log(`  Flex Direction: ${action.flexDirection}`);
            console.log(`  Align Items: ${action.alignItems}`);
            console.log(`  Margin Right: ${action.marginRight}`);
            console.log(`  Button Count: ${action.buttonCount}`);
            action.buttons.forEach((btn, btnIndex) => {
                console.log(`  Button ${btnIndex + 1}:`);
                console.log(`    Class: ${btn.className}`);
                console.log(`    Margin Left: ${btn.marginLeft}`);
                console.log(`    Margin Right: ${btn.marginRight}`);
            });
        });
        
        // Check lesson action icons
        const lessonActions = await page.evaluate(() => {
            const elements = document.querySelectorAll('.mpcc-lesson-actions');
            return Array.from(elements).map(el => {
                const computed = window.getComputedStyle(el);
                const buttons = el.querySelectorAll('button');
                return {
                    className: el.className,
                    display: computed.display,
                    gap: computed.gap,
                    flexDirection: computed.flexDirection,
                    alignItems: computed.alignItems,
                    marginLeft: computed.marginLeft,
                    buttonCount: buttons.length,
                    buttons: Array.from(buttons).map(btn => ({
                        className: btn.className,
                        marginLeft: window.getComputedStyle(btn).marginLeft,
                        marginRight: window.getComputedStyle(btn).marginRight
                    }))
                };
            });
        });
        
        console.log('\n.mpcc-lesson-actions styles:');
        lessonActions.forEach((action, index) => {
            console.log(`Element ${index + 1}:`);
            console.log(`  Display: ${action.display}`);
            console.log(`  Gap: ${action.gap}`);
            console.log(`  Flex Direction: ${action.flexDirection}`);
            console.log(`  Align Items: ${action.alignItems}`);
            console.log(`  Margin Left: ${action.marginLeft}`);
            console.log(`  Button Count: ${action.buttonCount}`);
            action.buttons.forEach((btn, btnIndex) => {
                console.log(`  Button ${btnIndex + 1}:`);
                console.log(`    Class: ${btn.className}`);
                console.log(`    Margin Left: ${btn.marginLeft}`);
                console.log(`    Margin Right: ${btn.marginRight}`);
            });
        });
        
        // Check for any CSS rules affecting the icons
        const iconRules = await page.evaluate(() => {
            const rules = [];
            Array.from(document.styleSheets).forEach(sheet => {
                try {
                    Array.from(sheet.cssRules || []).forEach(rule => {
                        if (rule.selectorText && 
                            (rule.selectorText.includes('mpcc-section-actions') || 
                             rule.selectorText.includes('mpcc-lesson-actions') ||
                             rule.selectorText.includes('mpcc-action-icon'))) {
                            rules.push({
                                selector: rule.selectorText,
                                styles: rule.style.cssText,
                                source: sheet.href || 'inline'
                            });
                        }
                    });
                } catch (e) {
                    // Skip cross-origin stylesheets
                }
            });
            return rules;
        });
        
        console.log('\nCSS Rules affecting icons:');
        iconRules.forEach(rule => {
            console.log(`\nSelector: ${rule.selector}`);
            console.log(`Source: ${rule.source}`);
            console.log(`Styles: ${rule.styles}`);
        });
        
        // Take screenshots of specific icon areas
        const sectionElements = await page.$$('.mpcc-section');
        if (sectionElements.length > 0) {
            const firstSection = sectionElements[0];
            await firstSection.screenshot({
                path: path.join(screenshotsDir, 'section-icons.png')
            });
            console.log('\nSection icons screenshot saved');
        }
        
        const lessonElements = await page.$$('.mpcc-lesson');
        if (lessonElements.length > 0) {
            const firstLesson = lessonElements[0];
            await firstLesson.screenshot({
                path: path.join(screenshotsDir, 'lesson-icons.png')
            });
            console.log('Lesson icons screenshot saved');
        }
        
        // Check if styles are being overridden
        const overrides = await page.evaluate(() => {
            const checkOverrides = (selector) => {
                const elements = document.querySelectorAll(selector);
                if (elements.length === 0) return null;
                
                const el = elements[0];
                const computed = window.getComputedStyle(el);
                
                // Check all stylesheets for rules
                const appliedRules = [];
                Array.from(document.styleSheets).forEach(sheet => {
                    try {
                        Array.from(sheet.cssRules || []).forEach(rule => {
                            if (rule.selectorText && el.matches(rule.selectorText)) {
                                appliedRules.push({
                                    selector: rule.selectorText,
                                    gap: rule.style.gap,
                                    marginLeft: rule.style.marginLeft,
                                    marginRight: rule.style.marginRight,
                                    source: sheet.href || 'inline'
                                });
                            }
                        });
                    } catch (e) {
                        // Skip cross-origin stylesheets
                    }
                });
                
                return {
                    selector,
                    computedGap: computed.gap,
                    computedMarginLeft: computed.marginLeft,
                    computedMarginRight: computed.marginRight,
                    appliedRules
                };
            };
            
            return {
                sectionActions: checkOverrides('.mpcc-section-actions'),
                lessonActions: checkOverrides('.mpcc-lesson-actions')
            };
        });
        
        console.log('\nStyle Override Analysis:');
        if (overrides.sectionActions) {
            console.log('\n.mpcc-section-actions:');
            console.log(`  Computed gap: ${overrides.sectionActions.computedGap}`);
            console.log(`  Applied rules:`);
            overrides.sectionActions.appliedRules.forEach(rule => {
                console.log(`    - ${rule.selector} (${rule.source})`);
                if (rule.gap) console.log(`      gap: ${rule.gap}`);
                if (rule.marginLeft) console.log(`      margin-left: ${rule.marginLeft}`);
                if (rule.marginRight) console.log(`      margin-right: ${rule.marginRight}`);
            });
        }
        
        if (overrides.lessonActions) {
            console.log('\n.mpcc-lesson-actions:');
            console.log(`  Computed gap: ${overrides.lessonActions.computedGap}`);
            console.log(`  Applied rules:`);
            overrides.lessonActions.appliedRules.forEach(rule => {
                console.log(`    - ${rule.selector} (${rule.source})`);
                if (rule.gap) console.log(`      gap: ${rule.gap}`);
                if (rule.marginLeft) console.log(`      margin-left: ${rule.marginLeft}`);
                if (rule.marginRight) console.log(`      margin-right: ${rule.marginRight}`);
            });
        }
        
        console.log('\nDiagnostic complete!');
        console.log(`Screenshots saved to: ${screenshotsDir}`);
        
    } catch (error) {
        console.error('Error during diagnostic:', error);
    } finally {
        // Keep browser open for manual inspection
        console.log('\nBrowser will remain open for manual inspection.');
        console.log('Press Ctrl+C to close.');
    }
})();