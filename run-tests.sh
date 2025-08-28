#!/bin/bash

# MemberPress Courses Copilot Test Runner
# Provides easy commands for running tests with proper configuration

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Script directory
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${RED}Error: Vendor directory not found. Run 'composer install' first.${NC}"
    exit 1
fi

# Check if phpunit exists
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}Error: PHPUnit not found. Run 'composer install' first.${NC}"
    exit 1
fi

# Function to display usage
usage() {
    echo "MemberPress Courses Copilot Test Runner"
    echo ""
    echo "Usage: $0 [command] [options]"
    echo ""
    echo "Commands:"
    echo "  all                Run all tests"
    echo "  unit               Run unit tests only"
    echo "  security           Run security tests only"
    echo "  integration        Run integration tests only"
    echo "  coverage           Generate code coverage report"
    echo "  coverage-html      Generate HTML coverage report"
    echo "  watch              Watch for changes and re-run tests"
    echo "  specific [file]    Run specific test file"
    echo "  help               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 all"
    echo "  $0 unit"
    echo "  $0 specific tests/Services/DatabaseServiceTest.php"
    echo "  $0 coverage"
}

# Function to run tests
run_tests() {
    local args="$@"
    echo -e "${GREEN}Running tests...${NC}"
    php -d memory_limit=512M vendor/bin/phpunit $args
}

# Main script logic
case "$1" in
    all|"")
        echo -e "${YELLOW}Running all tests...${NC}"
        run_tests
        ;;
    unit)
        echo -e "${YELLOW}Running unit tests...${NC}"
        run_tests --testsuite Unit
        ;;
    security)
        echo -e "${YELLOW}Running security tests...${NC}"
        run_tests tests/Security/
        ;;
    integration)
        echo -e "${YELLOW}Running integration tests...${NC}"
        run_tests --testsuite Integration
        ;;
    coverage)
        echo -e "${YELLOW}Generating code coverage report...${NC}"
        run_tests --coverage-text
        ;;
    coverage-html)
        echo -e "${YELLOW}Generating HTML coverage report...${NC}"
        run_tests --coverage-html coverage/
        echo -e "${GREEN}Coverage report generated in coverage/index.html${NC}"
        ;;
    watch)
        echo -e "${YELLOW}Watching for changes... Press Ctrl+C to stop${NC}"
        while true; do
            find src/ tests/ -name "*.php" | entr -d bash -c "clear && $0 all"
        done
        ;;
    specific)
        if [ -z "$2" ]; then
            echo -e "${RED}Error: Please specify a test file${NC}"
            usage
            exit 1
        fi
        echo -e "${YELLOW}Running specific test: $2${NC}"
        run_tests "$2"
        ;;
    help)
        usage
        ;;
    *)
        echo -e "${RED}Error: Unknown command '$1'${NC}"
        usage
        exit 1
        ;;
esac

# Check exit code
EXIT_CODE=$?
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Tests passed!${NC}"
else
    echo -e "${RED}✗ Tests failed!${NC}"
fi

exit $EXIT_CODE