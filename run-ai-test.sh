#!/bin/bash

# Run the AI content generation test
echo "🚀 Running AI Content Generation Test..."
echo "=================================="

# Change to the test directory
cd ~/wordpress-automation-tests

# Create screenshots directory if it doesn't exist
mkdir -p screenshots

# Run the test
node test-ai-content-generation.js

echo "=================================="
echo "✅ Test complete!"
echo ""
echo "Screenshots saved in: ~/wordpress-automation-tests/screenshots/"
echo " - ai-content-generated.png (if successful)"
echo " - generation-failed.png (if failed)"
echo " - error-state.png (if error occurred)"