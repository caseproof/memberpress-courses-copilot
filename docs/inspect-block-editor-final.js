const puppeteer = require('puppeteer');

(async () => {
  let browser;
  let page;
  
  try {
    browser = await puppeteer.launch({
      headless: false,
      devtools: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    page = await browser.newPage();
    
    // Set viewport
    await page.setViewport({ width: 1920, height: 1080 });
    
    // Capture all console logs
    const consoleLogs = [];
    page.on('console', msg => {
      const text = msg.text();
      consoleLogs.push({ type: msg.type(), text: text });
      if (text.includes('MPCC') || text.includes('memberpress-courses-copilot')) {
        console.log(`[${msg.type().toUpperCase()}] ${text}`);
      }
    });

    // Log errors
    page.on('pageerror', error => {
      console.error('Page error:', error.message);
    });

    // Login to WordPress
    console.log('Step 1: Logging in to WordPress...');
    await page.goto('http://localhost:10044/wp-login.php', { 
      waitUntil: 'networkidle2',
      timeout: 30000 
    });
    
    await page.type('#user_login', 'admin', { delay: 50 });
    await page.type('#user_pass', 'password', { delay: 50 });
    
    await Promise.all([
      page.click('#wp-submit'),
      page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 })
    ]);
    console.log('Login successful');

    // Navigate to the post edit page
    console.log('\nStep 2: Navigating to post 1575 edit page...');
    try {
      await page.goto('http://localhost:10044/wp-admin/post.php?post=1575&action=edit', { 
        waitUntil: 'domcontentloaded',
        timeout: 30000 
      });
    } catch (e) {
      console.log('Navigation timeout, but page may have loaded. Continuing...');
    }

    // Wait a bit for JavaScript to execute
    await new Promise(resolve => setTimeout(resolve, 5000));

    console.log('Current URL:', page.url());

    // Take screenshot
    await page.screenshot({ 
      path: 'block-editor-page.png',
      fullPage: false 
    });
    console.log('Screenshot saved as block-editor-page.png');

    // Get comprehensive page analysis
    console.log('\nStep 3: Analyzing page...');
    const pageAnalysis = await page.evaluate(() => {
      const analysis = {
        url: window.location.href,
        title: document.title,
        editorType: 'unknown',
        mpccAI: {
          exists: typeof window.mpccAI !== 'undefined',
          initialized: false,
          methods: []
        },
        modal: {
          overlay: !!document.querySelector('.mpcc-ai-modal-overlay'),
          container: !!document.querySelector('.mpcc-ai-modal-container'),
          button: !!document.querySelector('.mpcc-ai-button, [class*="mpcc-ai"]')
        },
        blockEditor: {
          detected: false,
          toolbarLocations: []
        },
        scripts: {
          mpcc: [],
          total: 0
        }
      };

      // Check editor type
      if (document.querySelector('.block-editor') || document.querySelector('.edit-post-layout')) {
        analysis.editorType = 'block';
        analysis.blockEditor.detected = true;
        
        // Find toolbar locations
        const toolbarSelectors = [
          '.edit-post-header-toolbar',
          '.editor-header__toolbar', 
          '.edit-post-header__toolbar',
          '.editor-document-tools',
          '.edit-post-header__settings',
          '.editor-header__settings'
        ];
        
        toolbarSelectors.forEach(selector => {
          const element = document.querySelector(selector);
          if (element) {
            analysis.blockEditor.toolbarLocations.push({
              selector: selector,
              exists: true,
              childCount: element.children.length
            });
          }
        });
      } else if (document.querySelector('#postdivrich') || document.querySelector('.wp-editor-area')) {
        analysis.editorType = 'classic';
      }

      // Check MPCC AI object
      if (analysis.mpccAI.exists) {
        analysis.mpccAI.initialized = true;
        analysis.mpccAI.methods = Object.keys(window.mpccAI);
      }

      // Check scripts
      const scripts = Array.from(document.querySelectorAll('script[src]'));
      analysis.scripts.total = scripts.length;
      scripts.forEach(script => {
        if (script.src.includes('memberpress-courses-copilot')) {
          analysis.scripts.mpcc.push(script.src.split('/').pop());
        }
      });

      // Check for AI button
      const aiButtons = document.querySelectorAll('[class*="mpcc-ai"], [id*="mpcc-ai"]');
      if (aiButtons.length > 0) {
        analysis.modal.buttonDetails = Array.from(aiButtons).map(btn => ({
          tagName: btn.tagName,
          id: btn.id,
          className: btn.className,
          text: btn.textContent.trim()
        }));
      }

      return analysis;
    });

    console.log('Page Analysis:', JSON.stringify(pageAnalysis, null, 2));

    // Check console logs
    console.log('\nStep 4: Console logs related to MPCC:');
    const mpccLogs = consoleLogs.filter(log => 
      log.text.includes('MPCC') || 
      log.text.includes('memberpress') || 
      log.text.includes('mpcc')
    );
    mpccLogs.forEach(log => {
      console.log(`[${log.type}] ${log.text}`);
    });

    // If block editor detected, check for specific integration points
    if (pageAnalysis.blockEditor.detected) {
      console.log('\nStep 5: Checking Block Editor integration points...');
      
      const integrationPoints = await page.evaluate(() => {
        const points = {
          publishButton: {
            selector: '.editor-post-publish-button__button, .editor-post-publish-panel__toggle',
            found: false,
            parentInfo: null
          },
          moreMenu: {
            selector: '.interface-more-menu-dropdown',
            found: false
          },
          documentTools: {
            selector: '.editor-document-tools__left, .edit-post-header-toolbar__left',
            found: false
          }
        };

        // Check publish button area
        const publishBtn = document.querySelector(points.publishButton.selector);
        if (publishBtn) {
          points.publishButton.found = true;
          points.publishButton.parentInfo = {
            className: publishBtn.parentElement?.className,
            siblingCount: publishBtn.parentElement?.children.length
          };
        }

        // Check more menu
        const moreMenu = document.querySelector(points.moreMenu.selector);
        if (moreMenu) {
          points.moreMenu.found = true;
        }

        // Check document tools area
        const docTools = document.querySelector(points.documentTools.selector);
        if (docTools) {
          points.documentTools.found = true;
          points.documentTools.childCount = docTools.children.length;
        }

        return points;
      });

      console.log('Integration Points:', JSON.stringify(integrationPoints, null, 2));
    }

    // Summary
    console.log('\n=== SUMMARY ===');
    console.log('1. Editor Type:', pageAnalysis.editorType);
    console.log('2. MPCC AI Initialized:', pageAnalysis.mpccAI.initialized);
    console.log('3. Modal HTML Present:', pageAnalysis.modal.overlay || pageAnalysis.modal.container);
    console.log('4. MPCC Scripts Loaded:', pageAnalysis.scripts.mpcc.join(', '));
    console.log('5. Block Editor Detected:', pageAnalysis.blockEditor.detected);
    console.log('6. Toolbar Locations Found:', pageAnalysis.blockEditor.toolbarLocations.length);

    // Recommendations
    console.log('\n=== RECOMMENDATIONS ===');
    if (pageAnalysis.blockEditor.detected) {
      console.log('Block Editor detected. Best locations for AI button:');
      pageAnalysis.blockEditor.toolbarLocations.forEach(loc => {
        console.log(`  - ${loc.selector}`);
      });
    } else {
      console.log('Classic Editor detected. Consider adding button to:');
      console.log('  - #wp-content-media-buttons');
      console.log('  - .wp-editor-tools');
    }

  } catch (error) {
    console.error('Error during inspection:', error.message);
  } finally {
    console.log('\nClosing browser in 10 seconds...');
    await new Promise(resolve => setTimeout(resolve, 10000));
    if (browser) {
      await browser.close();
    }
  }
})();