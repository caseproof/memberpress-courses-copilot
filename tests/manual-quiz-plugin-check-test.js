/**
 * Manual test to verify quiz plugin check is working
 * Run this in the browser console on a lesson edit page
 */

// Test 1: Check if quiz integration script is loaded
console.log('=== Quiz Plugin Check Test ===');
console.log('1. Checking if quiz integration script is loaded...');
if (typeof MPCCLessonQuizIntegration !== 'undefined') {
    console.log('✓ Quiz integration script is loaded - quiz plugin must be active');
} else {
    console.log('✓ Quiz integration script NOT loaded - quiz plugin must be inactive');
}

// Test 2: Check if Create Quiz button exists
console.log('\n2. Checking for Create Quiz button...');
const quizButton = document.querySelector('#mpcc-lesson-create-quiz');
if (quizButton) {
    console.log('✓ Create Quiz button found - quiz plugin is active');
} else {
    console.log('✓ Create Quiz button not found - expected when quiz plugin is inactive');
}

// Test 3: Check if quiz scripts are in page
console.log('\n3. Checking for quiz-related scripts in page...');
const scripts = Array.from(document.scripts);
const quizScripts = scripts.filter(s => s.src && s.src.includes('lesson-quiz-integration'));
if (quizScripts.length > 0) {
    console.log('✓ Quiz integration scripts found:', quizScripts.length);
    quizScripts.forEach(s => console.log('  -', s.src));
} else {
    console.log('✓ No quiz integration scripts found - expected when quiz plugin is inactive');
}

console.log('\n=== Test Complete ===');