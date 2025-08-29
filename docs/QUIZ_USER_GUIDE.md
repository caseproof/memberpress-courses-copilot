# MemberPress Courses Copilot - Quiz User Guide

## Overview

This guide explains how to use the AI-powered quiz generation feature to quickly create quiz questions from your course content.

## Getting Started

### Prerequisites

1. MemberPress Courses and MemberPress Course Quizzes must be installed and activated
2. You must have permission to edit quizzes
3. At least one lesson or course with content must exist

### Two Ways to Create AI-Generated Quizzes

#### Method 1: From a Lesson (Fastest - One Click!)

1. Navigate to **Courses** → **Lessons**
2. Edit any lesson
3. Click the **"Create Quiz"** button in the editor toolbar
4. You'll be redirected to the quiz editor and the AI Quiz Generator opens automatically!
5. The lesson is pre-selected - just choose difficulty and generate

#### Method 2: From Quiz Editor

1. Navigate to **Courses** → **Quizzes**
2. Click **Add New** to create a new quiz or edit an existing quiz
3. Look for the **"Generate with AI"** button in the editor toolbar (purple gradient button with a gear icon)
4. Click to open the AI Quiz Generator

![Generate with AI Button Location]
The button appears next to the Publish/Update button in the quiz editor.

## Using the Quiz AI Modal

### Step 1: Open the Modal

- If coming from a lesson: The modal opens automatically!
- If starting from quiz editor: Click the **"Generate with AI"** button

### Step 2: Select Content Source

**Select Lesson:** Choose the lesson you want to generate questions from. 
- If you came from a lesson, it's already selected! Look for the green "Auto-detected" indicator.
- Otherwise, use the dropdown to select from all available lessons.

### Step 3: Configure Options

**Number of Questions:** Select how many questions to generate (1-20). Default is 10.

### Step 4: Choose Generation Method

You have three quick-start options:

- **Generate Easy Questions** 🙂 - Creates straightforward questions testing basic recall
- **Generate Medium Questions** 🏆 - Creates balanced questions requiring comprehension
- **Generate Hard Questions** 🦸 - Creates challenging questions testing deeper understanding

Alternatively, use the custom prompt field to provide specific instructions.

### Step 5: Review Generated Questions

After generation, you'll see a preview of all questions including:
- Question text
- Four answer options (A, B, C, D)
- Correct answer (highlighted in green)
- Explanation (if provided)

### Step 6: Apply Questions

If satisfied with the questions:
1. Click **"Apply Questions"** to add them to your quiz
2. The questions will appear as blocks in the editor
3. Click **"Update"** or **"Publish"** to save the quiz

## Features

### Auto-Detection (New!)

The AI Quiz Generator intelligently detects lesson context:
- **From URL**: When navigating with lesson parameters
- **From Lesson Editor**: When using "Create Quiz" button
- **From Quiz Form**: When lesson is already selected
- **Visual Feedback**: Green indicator shows "Auto-detected from [source]"
- **Dynamic Updates**: Changes are detected in real-time

### Question Preview

Before applying questions, you can:
- Review all generated questions
- See correct answers highlighted
- Read explanations for each question
- Decide if regeneration is needed

### Copy to Clipboard

Click **"Copy to Clipboard"** to copy all questions in text format. Useful for:
- Sharing with colleagues
- Creating printed versions
- Backup purposes

### Regenerate

Not satisfied with the questions? Click **"Regenerate"** to get a new set.

## Editing Generated Questions

After applying questions to the editor:

1. **Edit Question Text**: Click on any question to modify the text
2. **Change Options**: Edit any answer option
3. **Update Correct Answer**: Click the radio button for the correct answer
4. **Add Feedback**: Add explanations or feedback for each question
5. **Reorder Questions**: Drag and drop to rearrange
6. **Delete Questions**: Use the block toolbar to remove unwanted questions

## Best Practices

### Content Selection

- **Choose Relevant Lessons**: Select lessons with substantial content
- **Multiple Lessons**: For comprehensive quizzes, generate from multiple lessons separately
- **Course-Level Generation**: Use course ID for broader topic coverage

### Question Quality

1. **Review Carefully**: AI generates good questions but always review for accuracy
2. **Edit as Needed**: Don't hesitate to modify questions for clarity
3. **Add Context**: Some questions may need additional context
4. **Verify Answers**: Double-check that correct answers are accurate

### Difficulty Levels

- **Easy**: Best for introduction or review quizzes
- **Medium**: Ideal for regular assessments
- **Hard**: Suitable for advanced students or final exams

### Custom Prompts

Use custom prompts to:
- Focus on specific topics: "Focus on the water cycle stages"
- Request specific formats: "Include questions about dates and events"
- Adjust complexity: "Make questions suitable for beginners"

## Troubleshooting

### "Generate with AI" Button Not Visible

**Possible Causes:**
- Not on a quiz edit page
- Browser compatibility issue
- JavaScript conflict

**Solutions:**
1. Refresh the page
2. Clear browser cache
3. Try a different browser
4. Check browser console for errors

### No Questions Generated

**Possible Causes:**
- Selected lesson has no content
- Network connection issue
- API service temporarily unavailable

**Solutions:**
1. Verify the lesson has content
2. Try a different lesson
3. Check your internet connection
4. Wait a moment and try again

### Questions Not Appearing in Editor

**Issue:** Questions generated but blocks are empty

**Solution:** 
1. Click the **"Update"** button to save the quiz
2. The questions will be properly saved and displayed
3. Refresh the page if needed

### Error Messages

**"Security check failed"**
- Your session has expired
- Solution: Refresh the page and try again

**"Insufficient permissions"**
- You don't have permission to edit quizzes
- Solution: Contact your administrator

**"No content available"**
- The selected lesson is empty
- Solution: Choose a different lesson or add content first

## Tips for Success

### 1. Start Small
Generate a few questions first to test the quality before creating large quizzes.

### 2. Mix and Match
Generate questions from multiple lessons to create comprehensive quizzes.

### 3. Use Difficulty Variety
Mix easy, medium, and hard questions for balanced assessments.

### 4. Review and Refine
Always review generated questions and refine them for your specific needs.

### 5. Save Frequently
Click "Update" regularly to save your work.

## Limitations

### Current Limitations

1. **Question Type**: Only multiple-choice questions are currently supported
2. **Maximum Questions**: Limited to 20 questions per generation
3. **Language**: English only at this time
4. **Media**: Text-only questions (no images/videos in generated content)

### Planned Features

- True/False questions
- Short answer questions
- Fill in the blank
- Question bank management
- Bulk quiz generation

## FAQ

**Q: Can I generate questions from multiple lessons at once?**
A: Currently, you need to select one lesson at a time. Generate from multiple lessons separately and they'll all be added to your quiz.

**Q: Are the generated questions saved automatically?**
A: No, you must click "Update" or "Publish" to save the questions permanently.

**Q: Can I edit questions after generation?**
A: Yes! All questions are fully editable in the quiz editor.

**Q: How many questions can I generate?**
A: You can generate 1-20 questions per request. Run multiple generations for larger quizzes.

**Q: What happens if I don't like the questions?**
A: Use the "Regenerate" button to get a new set, or close the modal without applying.

**Q: Can I use custom content instead of lessons?**
A: Not through the UI currently, but this feature is planned for future updates.

## Support

For additional help:
1. Check the [Technical Documentation](QUIZ_INTEGRATION_TECHNICAL.md)
2. Review the [Troubleshooting Guide](TROUBLESHOOTING.md)
3. Contact MemberPress support

## Feedback

We welcome your feedback! If you have suggestions for improving the quiz generation feature:
- Note what types of questions you'd like to see
- Share examples of good/bad generations
- Suggest UI improvements

Your input helps us improve the tool for everyone!