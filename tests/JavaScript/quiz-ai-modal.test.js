/**
 * Comprehensive tests for quiz-ai-modal.js
 * 
 * Tests modal functionality, context detection, question generation,
 * and block creation for the MemberPress Courses Copilot quiz AI modal
 * 
 * @package MemberPressCoursesCopilot\Tests\JavaScript
 */

// Import the module (we'll load it differently since it's in IIFE format)
const fs = require('fs');
const path = require('path');

// Load the quiz modal script content
const quizModalPath = path.join(__dirname, '../../assets/js/quiz-ai-modal.js');
const quizModalContent = fs.readFileSync(quizModalPath, 'utf8');

describe('MPCCQuizAIModal', () => {
    let modal;
    
    /**
     * Setup before each test
     * Initializes the modal and resets mocks
     */
    beforeEach(() => {
        // Reset DOM
        document.body.innerHTML = `
            <div class="wrap">
                <h1>Edit Quiz</h1>
                <div class="editor-header__settings">
                    <button class="editor-post-publish-button">Update</button>
                </div>
            </div>
            <select id="_mpcs_lesson_id">
                <option value="">Select lesson</option>
                <option value="123">Test Lesson</option>
            </select>
        `;
        
        // Reset body classes
        document.body.className = 'post-type-mpcs-quiz';
        
        // Reset window location
        Object.defineProperty(window, 'location', {
            value: {
                href: 'http://test.local/wp-admin/post.php?post=123&action=edit',
                search: '?post=123&action=edit'
            },
            writable: true
        });
        
        // Reset document referrer
        Object.defineProperty(document, 'referrer', {
            value: '',
            writable: true
        });
        
        // Reset mocks
        jest.clearAllMocks();
        
        // Reset jQuery AJAX mock
        $.ajax = jest.fn();
        $.get = jest.fn();
        
        // Execute the script to create the modal class
        eval(quizModalContent);
        
        // The script creates an instance automatically, but we'll create our own for testing
        modal = new window.MPCCQuizAIModal();
    });

    afterEach(() => {
        // Clean up any modals that might have been created
        $('#mpcc-quiz-ai-modal').remove();
        $('#mpcc-modal-styles').remove();
        $('.mpcc-ai-notice').remove();
        
        // Reset timers
        jest.clearAllTimers();
    });

    describe('Initialization', () => {
        /**
         * Test that modal initializes correctly on quiz edit pages
         */
        test('should initialize on quiz edit pages', () => {
            expect(modal).toBeDefined();
            expect(modal.modalOpen).toBe(false);
            expect(modal.generatedQuestions).toEqual([]);
        });

        /**
         * Test that modal does not initialize on non-quiz pages
         */
        test('should not initialize on non-quiz pages', () => {
            document.body.className = 'post-type-post';
            
            const newModal = new window.MPCCQuizAIModal();
            
            // Should not add button since it's not a quiz page
            expect($('#mpcc-quiz-generate-ai')).toHaveLength(0);
        });

        /**
         * Test that AI button is added to the editor toolbar
         */
        test('should add AI button to editor toolbar', () => {
            // Trigger the button addition
            modal.addGenerateButton();
            
            // Fast-forward timers to trigger the interval
            jest.advanceTimersByTime(200);
            
            expect($('#mpcc-quiz-generate-ai')).toHaveLength(1);
            expect($('#mpcc-quiz-generate-ai')).toHaveText('Generate with AI');
            expect($('#mpcc-quiz-generate-ai')).toHaveClass('is-primary');
        });
    });

    describe('Context Detection', () => {
        /**
         * Test lesson context detection from URL parameters
         */
        test('should detect lesson context from URL parameters', () => {
            // Set URL with lesson_id parameter
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://test.local/wp-admin/post.php?post=123&action=edit&lesson_id=456',
                    search: '?post=123&action=edit&lesson_id=456'
                },
                writable: true
            });
            
            modal.detectLessonContext();
            
            expect(modal.currentLessonId).toBe(456);
            expect(modal.detectionMethod).toBe('url');
        });

        /**
         * Test course context detection from URL parameters
         */
        test('should detect course context from URL parameters', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://test.local/wp-admin/post.php?post=123&action=edit&course_id=789',
                    search: '?post=123&action=edit&course_id=789'
                },
                writable: true
            });
            
            modal.detectLessonContext();
            
            expect(modal.currentCourseId).toBe(789);
        });

        /**
         * Test lesson context detection from lesson selector
         */
        test('should detect lesson context from lesson selector', () => {
            // Set lesson selector value
            $('#_mpcs_lesson_id').val('123');
            
            modal.detectLessonContext();
            
            expect(modal.currentLessonId).toBe(123);
            expect(modal.detectionMethod).toBe('lesson_selector');
        });

        /**
         * Test course context detection from curriculum referrer
         */
        test('should detect course context from curriculum referrer', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://test.local/wp-admin/post.php?post=123&action=edit&curriculum=1',
                    search: '?post=123&action=edit&curriculum=1'
                },
                writable: true
            });
            
            Object.defineProperty(document, 'referrer', {
                value: 'http://test.local/wp-admin/post.php?post=789&action=edit',
                writable: true
            });
            
            modal.detectLessonContext();
            
            expect(modal.currentCourseId).toBe(789);
            expect(modal.detectionMethod).toBe('curriculum-referrer');
        });
    });

    describe('Modal Operations', () => {
        /**
         * Test modal opening creates correct HTML structure
         */
        test('should create modal with correct structure when opened', () => {
            modal.openModal();
            
            expect($('#mpcc-quiz-ai-modal')).toHaveLength(1);
            expect($('#mpcc-modal-lesson-select')).toHaveLength(1);
            expect($('#mpcc-modal-question-type')).toHaveLength(1);
            expect($('#mpcc-modal-question-count')).toHaveLength(1);
            expect($('#mpcc-quiz-prompt')).toHaveLength(1);
            expect($('#mpcc-generate-quiz')).toHaveLength(1);
            
            expect(modal.modalOpen).toBe(true);
        });

        /**
         * Test modal closing removes DOM elements
         */
        test('should remove modal elements when closed', () => {
            modal.openModal();
            expect($('#mpcc-quiz-ai-modal')).toHaveLength(1);
            
            modal.closeModal();
            
            expect($('#mpcc-quiz-ai-modal')).toHaveLength(0);
            expect(modal.modalOpen).toBe(false);
        });

        /**
         * Test modal closes when clicking outside
         */
        test('should close when clicking outside modal', () => {
            modal.openModal();
            
            // Simulate click on modal background
            const clickEvent = new Event('click');
            Object.defineProperty(clickEvent, 'target', {
                value: document.getElementById('mpcc-quiz-ai-modal')
            });
            
            $('#mpcc-quiz-ai-modal').trigger(clickEvent);
            
            expect(modal.modalOpen).toBe(false);
        });
    });

    describe('Lesson Loading', () => {
        /**
         * Test loading course lessons via AJAX
         */
        test('should load course lessons when course ID is available', () => {
            modal.currentCourseId = 456;
            
            // Mock successful AJAX response
            $.ajax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        lessons: [
                            { id: 123, title: 'Lesson 1' },
                            { id: 124, title: 'Lesson 2' }
                        ],
                        course_title: 'Test Course'
                    }
                });
            });
            
            modal.openModal();
            modal.loadCourseLessonsOnly();
            
            expect($.ajax).toHaveBeenCalledWith(expect.objectContaining({
                data: expect.objectContaining({
                    action: 'mpcc_get_course_lessons',
                    course_id: 456
                })
            }));
        });

        /**
         * Test handling AJAX error when loading lessons
         */
        test('should handle AJAX error when loading course lessons', () => {
            modal.currentCourseId = 456;
            modal.openModal();
            
            // Mock AJAX error
            $.ajax.mockImplementation(({ error }) => {
                error({ status: 500, statusText: 'Server Error' });
            });
            
            modal.loadCourseLessonsOnly();
            
            // Should fallback to loading recent lessons
            expect($.get).toHaveBeenCalled();
        });

        /**
         * Test loading recent lessons as fallback
         */
        test('should load recent lessons when no context available', () => {
            // Mock successful REST API response
            $.get.mockResolvedValue([
                createMockLesson(123, 'Recent Lesson 1'),
                createMockLesson(124, 'Recent Lesson 2')
            ]);
            
            modal.openModal();
            modal.loadRecentLessons();
            
            expect($.get).toHaveBeenCalledWith('/wp-json/wp/v2/mpcs-lesson?per_page=50&orderby=modified&order=desc');
        });
    });

    describe('Question Generation', () => {
        beforeEach(() => {
            modal.openModal();
            
            // Set up modal form values
            $('#mpcc-modal-lesson-select').val('123');
            $('#mpcc-modal-question-count').val('5');
            $('#mpcc-modal-question-type').val('multiple_choice');
            $('#mpcc-quiz-prompt').val('Test prompt');
        });

        /**
         * Test successful question generation
         */
        test('should generate questions successfully', () => {
            const mockQuestions = [
                {
                    question: 'Test question?',
                    type: 'multiple_choice',
                    options: { A: 'Option 1', B: 'Option 2' },
                    correct_answer: 'A',
                    explanation: 'Test explanation'
                }
            ];
            
            // Mock successful AJAX response
            $.ajax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        questions: mockQuestions,
                        suggestion: 'Generated successfully'
                    }
                });
            });
            
            modal.generateQuestions('medium');
            
            expect($.ajax).toHaveBeenCalledWith(expect.objectContaining({
                data: expect.objectContaining({
                    action: 'mpcc_generate_quiz',
                    lesson_id: '123',
                    options: expect.stringContaining('multiple_choice')
                })
            }));
            
            expect(modal.generatedQuestions).toEqual(mockQuestions);
            expect($('#mpcc-quiz-results')).toBeVisible();
        });

        /**
         * Test question generation without selected lesson
         */
        test('should show error when no lesson selected', () => {
            $('#mpcc-modal-lesson-select').val('');
            
            modal.generateQuestions();
            
            expect($('#mpcc-modal-error')).toBeVisible();
            expect($('#mpcc-modal-error .error-message')).toContainText('Please select a lesson');
        });

        /**
         * Test handling AJAX error in question generation
         */
        test('should handle AJAX error in question generation', () => {
            // Mock AJAX error
            $.ajax.mockImplementation(({ error }) => {
                error({
                    status: 400,
                    responseJSON: {
                        success: false,
                        data: {
                            message: 'Content too short',
                            suggestion: 'Add more content'
                        }
                    }
                });
            });
            
            modal.generateQuestions();
            
            expect($('#mpcc-modal-error')).toBeVisible();
            expect($('#mpcc-modal-error .error-message')).toContainText('Content too short');
            expect($('#mpcc-modal-error .error-suggestion')).toContainText('Add more content');
        });
    });

    describe('Question Display', () => {
        /**
         * Test displaying multiple choice questions
         */
        test('should display multiple choice questions correctly', () => {
            const questions = [
                {
                    question: 'What is 2+2?',
                    type: 'multiple_choice',
                    options: { A: '3', B: '4', C: '5', D: '6' },
                    correct_answer: 'B',
                    explanation: 'Basic math'
                }
            ];
            
            modal.openModal();
            modal.displayQuestions(questions);
            
            expect($('#mpcc-questions-preview .mpcc-question-item')).toHaveLength(1);
            expect($('#mpcc-questions-preview')).toContainText('What is 2+2?');
            expect($('#mpcc-questions-preview')).toContainText('✓ B) 4');
            expect($('#mpcc-questions-preview')).toContainText('Basic math');
        });

        /**
         * Test displaying true/false questions
         */
        test('should display true/false questions correctly', () => {
            const questions = [
                {
                    statement: 'The sky is blue',
                    type: 'true_false',
                    correct_answer: true,
                    explanation: 'Sky appears blue due to light scattering'
                }
            ];
            
            modal.openModal();
            modal.displayQuestions(questions);
            
            expect($('#mpcc-questions-preview')).toContainText('The sky is blue');
            expect($('#mpcc-questions-preview')).toContainText('✓ True');
            expect($('#mpcc-questions-preview')).toContainText('✗ False');
        });

        /**
         * Test displaying text answer questions
         */
        test('should display text answer questions correctly', () => {
            const questions = [
                {
                    question: 'What is the capital of France?',
                    type: 'text_answer',
                    correct_answer: 'Paris',
                    alternative_answers: ['paris', 'PARIS'],
                    explanation: 'Paris is the capital city'
                }
            ];
            
            modal.openModal();
            modal.displayQuestions(questions);
            
            expect($('#mpcc-questions-preview')).toContainText('What is the capital of France?');
            expect($('#mpcc-questions-preview')).toContainText('Expected: Paris');
            expect($('#mpcc-questions-preview')).toContainText('Also accepts: paris, PARIS');
        });

        /**
         * Test displaying multiple select questions
         */
        test('should display multiple select questions correctly', () => {
            const questions = [
                {
                    question: 'Which are programming languages?',
                    type: 'multiple_select',
                    options: { A: 'PHP', B: 'HTML', C: 'JavaScript', D: 'CSS' },
                    correct_answers: ['A', 'C'],
                    explanation: 'PHP and JavaScript are programming languages'
                }
            ];
            
            modal.openModal();
            modal.displayQuestions(questions);
            
            expect($('#mpcc-questions-preview')).toContainText('Which are programming languages?');
            expect($('#mpcc-questions-preview')).toContainText('☑ PHP');
            expect($('#mpcc-questions-preview')).toContainText('☑ JavaScript');
            expect($('#mpcc-questions-preview')).toContainText('☐ HTML');
            expect($('#mpcc-questions-preview')).toContainText('☐ CSS');
        });
    });

    describe('Question Block Creation', () => {
        beforeEach(() => {
            // Set up generated questions
            modal.generatedQuestions = [
                {
                    question: 'Test question?',
                    type: 'multiple_choice',
                    options: { A: 'Option 1', B: 'Option 2' },
                    correct_answer: 'A'
                }
            ];
        });

        /**
         * Test getBlockTypeForQuestion method
         */
        test('should return correct block types for question types', () => {
            expect(modal.getBlockTypeForQuestion('multiple_choice')).toBe('memberpress-courses/multiple-choice-question');
            expect(modal.getBlockTypeForQuestion('true_false')).toBe('memberpress-courses/true-false-question');
            expect(modal.getBlockTypeForQuestion('text_answer')).toBe('memberpress-courses/short-answer-question');
            expect(modal.getBlockTypeForQuestion('multiple_select')).toBe('memberpress-courses/multiple-answer-question');
            expect(modal.getBlockTypeForQuestion('unknown')).toBe('memberpress-courses/multiple-choice-question');
        });

        /**
         * Test prepareQuestionData for multiple choice
         */
        test('should prepare multiple choice question data correctly', () => {
            const question = {
                question: 'What is 2+2?',
                type: 'multiple_choice',
                options: { A: '3', B: '4', C: '5', D: '6' },
                correct_answer: 'B',
                explanation: 'Basic math'
            };
            
            const prepared = modal.prepareQuestionData(question, 0);
            
            expect(prepared.question).toBe('What is 2+2?');
            expect(prepared.type).toBe('multiple-choice');
            expect(prepared.number).toBe(1);
            expect(prepared.answer).toBe(1); // Index of correct answer
            expect(prepared.options).toHaveLength(4);
            expect(prepared.options[1].isCorrect).toBe(true);
            expect(prepared.feedback).toBe('Basic math');
        });

        /**
         * Test prepareQuestionData for true/false
         */
        test('should prepare true/false question data correctly', () => {
            const question = {
                statement: 'The sky is blue',
                type: 'true_false',
                correct_answer: true,
                explanation: 'Due to light scattering'
            };
            
            const prepared = modal.prepareQuestionData(question, 1);
            
            expect(prepared.question).toBe('The sky is blue');
            expect(prepared.type).toBe('true-false');
            expect(prepared.number).toBe(2);
            expect(prepared.correctAnswer).toBe(true);
        });

        /**
         * Test prepareQuestionData for text answer
         */
        test('should prepare text answer question data correctly', () => {
            const question = {
                question: 'What is the capital?',
                type: 'text_answer',
                correct_answer: 'Paris',
                explanation: 'Capital city'
            };
            
            const prepared = modal.prepareQuestionData(question, 2);
            
            expect(prepared.question).toBe('What is the capital?');
            expect(prepared.type).toBe('short-answer');
            expect(prepared.number).toBe(3);
            expect(prepared.expectedAnswer).toBe('Paris');
        });

        /**
         * Test prepareQuestionData for multiple select
         */
        test('should prepare multiple select question data correctly', () => {
            const question = {
                question: 'Which are languages?',
                type: 'multiple_select',
                options: { A: 'PHP', B: 'HTML', C: 'JavaScript' },
                correct_answers: ['A', 'C'],
                explanation: 'Programming languages'
            };
            
            const prepared = modal.prepareQuestionData(question, 3);
            
            expect(prepared.question).toBe('Which are languages?');
            expect(prepared.type).toBe('multiple-answer');
            expect(prepared.number).toBe(4);
            expect(prepared.answer).toEqual([0, 2]); // Indices of correct answers
            expect(prepared.options[0].isCorrect).toBe(true);
            expect(prepared.options[1].isCorrect).toBe(false);
            expect(prepared.options[2].isCorrect).toBe(true);
        });

        /**
         * Test createQuestionBlock method
         */
        test('should create question block with correct structure', async () => {
            const question = {
                question: 'Test question?',
                type: 'multiple_choice',
                options: { A: 'Option 1', B: 'Option 2' },
                correct_answer: 'A'
            };
            
            const mockDispatch = {
                addPlaceholder: jest.fn(),
                getNextQuestionId: jest.fn().mockResolvedValue({ id: 456 })
            };
            
            const block = await modal.createQuestionBlock(question, 0, 123, mockDispatch);
            
            expect(block).toBeDefined();
            expect(block.name).toBe('memberpress-courses/multiple-choice-question');
            expect(block.attributes.questionId).toBe(456);
            expect(mockDispatch.addPlaceholder).toHaveBeenCalled();
            expect(mockDispatch.getNextQuestionId).toHaveBeenCalledWith(123, expect.any(String));
        });

        /**
         * Test applyQuestions method
         */
        test('should apply questions to editor successfully', async () => {
            modal.openModal();
            modal.generatedQuestions = [
                {
                    question: 'Test question?',
                    type: 'multiple_choice',
                    options: { A: 'Option 1', B: 'Option 2' },
                    correct_answer: 'A'
                }
            ];
            
            // Mock WordPress data functions
            const mockInsertBlocks = jest.fn();
            const mockEditPost = jest.fn();
            
            wp.data.dispatch.mockReturnValue({
                insertBlocks: mockInsertBlocks,
                editPost: mockEditPost,
                addPlaceholder: jest.fn(),
                getNextQuestionId: jest.fn().mockResolvedValue({ id: 456 })
            });
            
            await modal.applyQuestions();
            
            expect(mockInsertBlocks).toHaveBeenCalledWith(expect.arrayContaining([
                expect.objectContaining({
                    name: 'memberpress-courses/multiple-choice-question'
                })
            ]));
            
            expect(mockEditPost).toHaveBeenCalled();
            expect(modal.modalOpen).toBe(false);
        });
    });

    describe('Utility Functions', () => {
        /**
         * Test copyQuestions method
         */
        test('should copy questions to clipboard', async () => {
            modal.generatedQuestions = [
                {
                    question: 'What is 2+2?',
                    type: 'multiple_choice',
                    options: { A: '3', B: '4', C: '5' },
                    correct_answer: 'B',
                    explanation: 'Basic math'
                },
                {
                    statement: 'Sky is blue',
                    type: 'true_false',
                    correct_answer: true
                }
            ];
            
            modal.copyQuestions();
            
            expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
                expect.stringContaining('Question 1: What is 2+2?')
            );
            expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
                expect.stringContaining('Question 2: Sky is blue')
            );
        });

        /**
         * Test showNotice method
         */
        test('should show notice with correct styling', () => {
            modal.showNotice('Test success message', 'success');
            
            expect($('.mpcc-ai-notice')).toHaveLength(1);
            expect($('.mpcc-ai-notice')).toHaveClass('notice-success');
            expect($('.mpcc-ai-notice p')).toContainText('Test success message');
        });

        /**
         * Test showModalError method
         */
        test('should show modal error with suggestion', () => {
            modal.openModal();
            modal.showModalError('Test error', 'Test suggestion');
            
            expect($('#mpcc-modal-error')).toBeVisible();
            expect($('#mpcc-modal-error .error-message')).toContainText('Test error');
            expect($('#mpcc-modal-error .error-suggestion')).toContainText('Test suggestion');
        });

        /**
         * Test auto-hide functionality for modal error
         */
        test('should auto-hide modal error after timeout', () => {
            modal.openModal();
            modal.showModalError('Test error');
            
            expect($('#mpcc-modal-error')).toBeVisible();
            
            // Fast-forward past the auto-hide timeout
            jest.advanceTimersByTime(11000);
            
            expect($('#mpcc-modal-error')).not.toBeVisible();
        });
    });

    describe('Auto-open Functionality', () => {
        /**
         * Test auto-opening modal from lesson context
         */
        test('should auto-open modal when coming from lesson', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://test.local/wp-admin/post.php?post=123&action=edit&lesson_id=456&auto_open=true',
                    search: '?post=123&action=edit&lesson_id=456&auto_open=true'
                },
                writable: true
            });
            
            document.body.className = 'post-new-php post-type-mpcs-quiz';
            
            const openModalSpy = jest.spyOn(modal, 'openModal');
            
            modal.checkAutoOpenModal();
            
            // Fast-forward past the delay
            jest.advanceTimersByTime(1500);
            
            expect(openModalSpy).toHaveBeenCalled();
        });

        /**
         * Test that auto-open removes parameter from URL
         */
        test('should remove auto_open parameter from URL after opening', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://test.local/wp-admin/post.php?post=123&action=edit&lesson_id=456&auto_open=true',
                    search: '?post=123&action=edit&lesson_id=456&auto_open=true'
                },
                writable: true
            });
            
            document.body.className = 'post-new-php';
            
            modal.checkAutoOpenModal();
            jest.advanceTimersByTime(1500);
            
            expect(window.history.replaceState).toHaveBeenCalled();
        });
    });

    describe('Event Handling', () => {
        /**
         * Test lesson selector monitoring
         */
        test('should monitor lesson selector for changes', () => {
            modal.startLessonMonitoring();
            
            // Change lesson selector value
            $('#_mpcs_lesson_id').val('456').trigger('change');
            
            expect(modal.currentLessonId).toBe(456);
        });

        /**
         * Test modal button event binding
         */
        test('should bind modal events correctly', () => {
            modal.openModal();
            
            // Test close button
            $('.mpcc-modal-close').trigger('click');
            expect(modal.modalOpen).toBe(false);
        });

        /**
         * Test generate button click
         */
        test('should handle generate button click', () => {
            modal.openModal();
            
            const generateSpy = jest.spyOn(modal, 'generateQuestions');
            $('#mpcc-generate-quiz').trigger('click');
            
            expect(generateSpy).toHaveBeenCalledWith('medium');
        });
    });

    describe('Error Scenarios', () => {
        /**
         * Test handling missing mpcc_ajax configuration
         */
        test('should handle missing AJAX configuration', () => {
            delete global.mpcc_ajax;
            
            modal.openModal();
            modal.loadCourseLessonsOnly();
            
            expect($('#mpcc-modal-error')).toBeVisible();
            expect($('#mpcc-modal-error .error-message')).toContainText('Configuration error');
        });

        /**
         * Test handling invalid course ID
         */
        test('should handle invalid course ID', () => {
            modal.currentCourseId = 0;
            modal.openModal();
            modal.loadCourseLessonsOnly();
            
            expect($('#mpcc-modal-error')).toBeVisible();
            expect($('#mpcc-modal-error .error-message')).toContainText('No course context available');
        });

        /**
         * Test handling failed question ID reservation
         */
        test('should handle failed question ID reservation gracefully', async () => {
            const question = {
                question: 'Test?',
                type: 'multiple_choice',
                options: { A: 'A', B: 'B' },
                correct_answer: 'A'
            };
            
            const mockDispatch = {
                addPlaceholder: jest.fn(),
                getNextQuestionId: jest.fn().mockRejectedValue(new Error('Reservation failed'))
            };
            
            const block = await modal.createQuestionBlock(question, 0, 123, mockDispatch);
            
            expect(block).toBeDefined();
            expect(block.attributes.questionId).toBe(0); // Should use 0 as fallback
        });
    });

    describe('Integration Tests', () => {
        /**
         * Test complete workflow from opening modal to applying questions
         */
        test('should complete full workflow successfully', async () => {
            // Set up lesson context
            modal.currentLessonId = 123;
            modal.detectionMethod = 'url';
            
            // Open modal
            modal.openModal();
            expect($('#mpcc-quiz-ai-modal')).toBeVisible();
            
            // Set form values
            $('#mpcc-modal-lesson-select').val('123');
            $('#mpcc-modal-question-count').val('3');
            
            // Mock successful question generation
            $.ajax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        questions: [
                            {
                                question: 'Generated question?',
                                type: 'multiple_choice',
                                options: { A: 'Option 1', B: 'Option 2' },
                                correct_answer: 'A'
                            }
                        ]
                    }
                });
            });
            
            // Generate questions
            modal.generateQuestions();
            
            expect(modal.generatedQuestions).toHaveLength(1);
            expect($('#mpcc-quiz-results')).toBeVisible();
            
            // Apply questions
            const mockInsertBlocks = jest.fn();
            wp.data.dispatch.mockReturnValue({
                insertBlocks: mockInsertBlocks,
                editPost: jest.fn(),
                addPlaceholder: jest.fn(),
                getNextQuestionId: jest.fn().mockResolvedValue({ id: 456 })
            });
            
            await modal.applyQuestions();
            
            expect(mockInsertBlocks).toHaveBeenCalled();
            expect(modal.modalOpen).toBe(false);
        });

        /**
         * Test error recovery during workflow
         */
        test('should recover from errors during workflow', () => {
            modal.openModal();
            
            // Simulate AJAX error
            $.ajax.mockImplementation(({ error }) => {
                error({ status: 500, statusText: 'Server Error' });
            });
            
            $('#mpcc-modal-lesson-select').val('123');
            modal.generateQuestions();
            
            // Should show error but modal should remain open
            expect($('#mpcc-modal-error')).toBeVisible();
            expect(modal.modalOpen).toBe(true);
            
            // Should be able to try again
            $.ajax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { questions: [{ question: 'Retry question?' }] }
                });
            });
            
            modal.generateQuestions();
            expect(modal.generatedQuestions).toHaveLength(1);
        });
    });

    describe('Performance and Memory', () => {
        /**
         * Test that modal cleans up properly
         */
        test('should clean up resources when closed', () => {
            modal.openModal();
            
            // Set up some intervals that should be cleaned
            modal.lessonMonitorInterval = setInterval(() => {}, 100);
            
            modal.closeModal();
            
            expect(modal.lessonMonitorInterval).toBeNull();
            expect($('#mpcc-quiz-ai-modal')).toHaveLength(0);
        });

        /**
         * Test handling large numbers of questions
         */
        test('should handle large numbers of questions efficiently', () => {
            const manyQuestions = Array.from({ length: 50 }, (_, i) => ({
                question: `Question ${i + 1}?`,
                type: 'multiple_choice',
                options: { A: 'A', B: 'B' },
                correct_answer: 'A'
            }));
            
            modal.openModal();
            
            const startTime = performance.now();
            modal.displayQuestions(manyQuestions);
            const endTime = performance.now();
            
            // Should complete within reasonable time (less than 100ms)
            expect(endTime - startTime).toBeLessThan(100);
            expect($('#mpcc-questions-preview .mpcc-question-item')).toHaveLength(50);
        });
    });
});