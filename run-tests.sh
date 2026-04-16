#!/bin/bash

# Test Runner Script - Valsoft Inventory
# Ejecuta tests en Docker o localmente

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

function print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

function print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

function print_error() {
    echo -e "${RED}✗ $1${NC}"
}

function print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Parse arguments
MODE=${1:-"docker"}
TEST_FILE=${2:-""}

case "$MODE" in
    docker)
        print_header "Running Tests in Docker Environment"

        print_info "Checking if db_test service is running..."
        if ! docker ps | grep -q valsoft_db_test; then
            print_info "Starting db_test service..."
            docker-compose up -d db_test
            sleep 5
            print_success "db_test service started"
        else
            print_success "db_test service is already running"
        fi

        print_info "Running tests with Docker configuration (phpunit.xml)..."
        if [ -z "$TEST_FILE" ]; then
            php artisan test
        else
            php artisan test "$TEST_FILE"
        fi

        print_success "Tests completed in Docker mode"
        ;;

    local)
        print_header "Running Tests in Local Environment (Outside Docker)"

        print_info "Verifying MySQL is accessible at 127.0.0.1:3307..."
        if ! nc -z 127.0.0.1 3307 2>/dev/null; then
            print_error "MySQL not accessible at 127.0.0.1:3307"
            echo ""
            echo "To run tests locally, you need to:"
            echo "1. Start the db_test service in Docker: docker-compose up -d db_test"
            echo "2. Or configure your local MySQL database"
            echo ""
            echo "Alternative: Run 'bash run-tests.sh docker' to use Docker directly"
            exit 1
        fi

        print_success "MySQL accessible at 127.0.0.1:3307"

        print_info "Running tests with local configuration (phpunit.local.xml)..."
        if [ -z "$TEST_FILE" ]; then
            php -d memory_limit=-1 vendor/bin/phpunit --configuration phpunit.local.xml
        else
            php -d memory_limit=-1 vendor/bin/phpunit --configuration phpunit.local.xml "$TEST_FILE"
        fi

        print_success "Tests completed in local mode"
        ;;

    container)
        print_header "Running Tests Inside App Container"

        print_info "Checking if Docker containers are running..."
        if ! docker ps | grep -q valsoft_app; then
            print_error "App container not running. Start with: docker-compose up -d"
            exit 1
        fi

        print_info "Running tests inside container..."
        if [ -z "$TEST_FILE" ]; then
            docker-compose exec -T app php artisan test
        else
            docker-compose exec -T app php artisan test "$TEST_FILE"
        fi

        print_success "Tests completed inside container"
        ;;

    *)
        echo "Usage: bash run-tests.sh [MODE] [TEST_FILE]"
        echo ""
        echo "Modes:"
        echo "  docker    - Run tests using Docker services (default)"
        echo "  local     - Run tests locally against Docker db_test on port 3307"
        echo "  container - Run tests inside the app container"
        echo ""
        echo "Examples:"
        echo "  bash run-tests.sh docker"
        echo "  bash run-tests.sh local tests/Feature/Api/V1/ItemTest.php"
        echo "  bash run-tests.sh container"
        exit 1
        ;;
esac
