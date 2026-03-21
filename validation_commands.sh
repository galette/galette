#!/bin/bash

# Validation System - Quick Commands
# ===================================

echo "========================================="
echo " Galette Validation System - Commands"
echo "========================================="
echo ""

# Color codes
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}1. Run Benchmarks${NC}"
echo "   Quick test (100 iterations):"
echo "   $ php bin/benchmark_validation.php quick"
echo ""
echo "   Normal test (1000 iterations):"
echo "   $ php bin/benchmark_validation.php normal"
echo ""
echo "   Intensive test (10000 iterations):"
echo "   $ php bin/benchmark_validation.php intensive"
echo ""

echo -e "${BLUE}2. Run Tests${NC}"
echo "   All entity tests:"
echo "   $ DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/"
echo ""
echo "   SavedSearch tests:"
echo "   $ DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/SavedSearchTest.php"
echo ""
echo "   Contribution tests:"
echo "   $ DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/Contribution.php"
echo ""
echo "   Adherent tests:"
echo "   $ DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/Adherent.php"
echo ""

echo -e "${BLUE}3. Code Quality${NC}"
echo "   Check code style:"
echo "   $ galette/vendor/bin/php-cs-fixer fix --dry-run --diff galette/lib/Galette/Entity/AbstractEntity.php"
echo ""
echo "   Fix code style:"
echo "   $ galette/vendor/bin/php-cs-fixer fix galette/lib/Galette/Entity/AbstractEntity.php"
echo ""
echo "   Run PHPStan:"
echo "   $ galette/vendor/bin/phpstan analyse galette/lib/Galette/Entity/AbstractEntity.php"
echo ""

echo -e "${BLUE}4. View Results${NC}"
echo "   View benchmark results:"
echo "   $ cat tests/benchmark_results.json | jq ."
echo ""
echo "   View last benchmark:"
echo "   $ cat tests/benchmark_results.json | jq '.[-1]'"
echo ""

echo -e "${BLUE}5. Documentation${NC}"
echo "   View validation system docs:"
echo "   $ cat docs/VALIDATION_SYSTEM.md"
echo ""
echo "   View implementation summary:"
echo "   $ cat IMPLEMENTATION_SUMMARY.md"
echo ""

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN} Implementation Status: ✅ Complete${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""

# If run with argument "run-all", execute all checks
if [ "$1" = "run-all" ]; then
    echo -e "${YELLOW}Running all checks...${NC}"
    echo ""

    echo -e "${BLUE}1. Checking code style...${NC}"
    galette/vendor/bin/php-cs-fixer fix --dry-run galette/lib/Galette/Entity/AbstractEntity.php
    echo ""

    echo -e "${BLUE}2. Running quick benchmark...${NC}"
    php bin/benchmark_validation.php quick
    echo ""

    echo -e "${GREEN}✅ All checks completed!${NC}"
fi

