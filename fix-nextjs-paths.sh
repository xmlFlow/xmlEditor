#!/bin/bash

##############################################################################
# Fix Next.js Build Paths Script
#
# This script fixes the hardcoded /_next/ paths in the Next.js build output
# to use relative paths so they work correctly when embedded in the OJS plugin.
#
# It fixes paths in:
#   - HTML files (href/src attributes)
#   - CSS files (url() references)
#   - JavaScript files (dynamic imports and __webpack_require__)
#
# Usage:
#   ./fix-nextjs-paths.sh
#
# Run this script after every Next.js build to fix the asset paths.
##############################################################################

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Define the editor build directory
EDITOR_BASE="$SCRIPT_DIR/editor"

echo -e "${YELLOW}=== Fixing Next.js Build Paths ===${NC}"
echo ""

# Check if the editor directory exists
if [ ! -d "$EDITOR_BASE" ]; then
    echo -e "${RED}Error: Editor directory not found at: $EDITOR_BASE${NC}"
    exit 1
fi

# Function to fix paths in HTML files
fix_paths_in_html() {
    local file=$1
    local replacement=$2
    local file_rel_path=${file#$EDITOR_BASE/}

    echo -e "Processing HTML: ${YELLOW}$file_rel_path${NC}"

    # Count occurrences before fixing
    BEFORE_COUNT=$(grep -oE '(href|src)="/_next/' "$file" 2>/dev/null | wc -l)
    echo -e "  Found ${YELLOW}$BEFORE_COUNT${NC} hardcoded /_next/ paths"

    if [ $BEFORE_COUNT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Already fixed${NC}"
        return 0
    fi

    # Fix the paths - handle all variations including inline scripts
    sed -i "s|\"/_next/|\"$replacement|g; s|'/_next/|'$replacement|g" "$file"

    # Count occurrences after fixing
    AFTER_COUNT=$(grep -oE '(href|src)="/_next/' "$file" 2>/dev/null | wc -l)

    if [ $AFTER_COUNT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Fixed successfully${NC}"
        return 0
    else
        echo -e "  ${RED}✗ Failed - $AFTER_COUNT paths remaining${NC}"
        return 1
    fi
}

# Function to fix paths in CSS files
fix_paths_in_css() {
    local file=$1
    local file_rel_path=${file#$EDITOR_BASE/}

    echo -e "Processing CSS: ${YELLOW}$file_rel_path${NC}"

    # Count occurrences before fixing (url references)
    BEFORE_COUNT=$(grep -o 'url(/_next/' "$file" 2>/dev/null | wc -l)
    echo -e "  Found ${YELLOW}$BEFORE_COUNT${NC} hardcoded /_next/ paths"

    if [ $BEFORE_COUNT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Already fixed${NC}"
        return 0
    fi

    # Fix CSS url() references - CSS files in _next/static/css/ need ../ to get back to editor/
    sed -i 's|url(/_next/|url(../_next/|g' "$file"

    # Count occurrences after fixing
    AFTER_COUNT=$(grep -o 'url(/_next/' "$file" 2>/dev/null | wc -l)

    if [ $AFTER_COUNT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Fixed successfully${NC}"
        return 0
    else
        echo -e "  ${RED}✗ Failed - $AFTER_COUNT paths remaining${NC}"
        return 1
    fi
}

# Function to fix paths in JS files
fix_paths_in_js() {
    local file=$1
    local file_rel_path=${file#$EDITOR_BASE/}

    echo -e "Processing JS: ${YELLOW}$file_rel_path${NC}"

    # Count occurrences before fixing (various formats)
    # Check for webpack public path (d.p="/_next/"), dynamic imports, and static paths
    WEBPACK_PATH_COUNT=$(grep -o 'd\.p="/_next/"' "$file" 2>/dev/null | wc -l)
    CHUNK_PATH_COUNT=$(grep -o '["'\''](/_next/static/chunks/' "$file" 2>/dev/null | wc -l)
    BEFORE_COUNT=$((WEBPACK_PATH_COUNT + CHUNK_PATH_COUNT))

    echo -e "  Found ${YELLOW}$BEFORE_COUNT${NC} hardcoded /_next/ paths"

    if [ $BEFORE_COUNT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Already fixed${NC}"
        return 0
    fi

    # Fix webpack public path (critical for dynamic chunk loading)
    sed -i 's|d\.p="/_next/"|d.p="./_next/"|g' "$file"

    # Fix JS chunk paths - JS files in _next/static/chunks/ need relative paths
    sed -i 's|"/_next/static/chunks/|"./_next/static/chunks/|g; s|'\''/_next/static/chunks/|'\''./_next/static/chunks/|g' "$file"

    # Count occurrences after fixing
    WEBPACK_PATH_AFTER=$(grep -o 'd\.p="/_next/"' "$file" 2>/dev/null | wc -l)
    CHUNK_PATH_AFTER=$(grep -o '["'\''](/_next/static/chunks/' "$file" 2>/dev/null | wc -l)
    AFTER_COUNT=$((WEBPACK_PATH_AFTER + CHUNK_PATH_AFTER))

    if [ $AFTER_COUNT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Fixed successfully${NC}"
        return 0
    else
        echo -e "  ${RED}✗ Failed - $AFTER_COUNT paths remaining${NC}"
        return 1
    fi
}

TOTAL_ERRORS=0

# Fix HTML files at editor/ level (need ./_next/)
echo -e "\n${YELLOW}=== Fixing HTML files at editor/ level ===${NC}"
for file in "$EDITOR_BASE"/*.html; do
    if [ -f "$file" ]; then
        if ! fix_paths_in_html "$file" "./_next/"; then
            TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
        fi
    fi
done

# Fix HTML files in subdirectories (need ../_next/)
echo -e "\n${YELLOW}=== Fixing HTML files in subdirectories ===${NC}"
for file in "$EDITOR_BASE"/*/*.html; do
    if [ -f "$file" ]; then
        if ! fix_paths_in_html "$file" "../_next/"; then
            TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
        fi
    fi
done

# Fix CSS files
echo -e "\n${YELLOW}=== Fixing CSS files ===${NC}"
for file in "$EDITOR_BASE"/_next/static/css/*.css; do
    if [ -f "$file" ]; then
        if ! fix_paths_in_css "$file"; then
            TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
        fi
    fi
done

# Fix JS files (webpack chunks, including nested app chunks)
echo -e "\n${YELLOW}=== Fixing JavaScript files ===${NC}"
# Fix top-level chunks
for file in "$EDITOR_BASE"/_next/static/chunks/*.js; do
    if [ -f "$file" ]; then
        if ! fix_paths_in_js "$file"; then
            TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
        fi
    fi
done

# Fix nested app chunks
find "$EDITOR_BASE"/_next/static/chunks/app -name "*.js" 2>/dev/null | while read file; do
    if [ -f "$file" ]; then
        if ! fix_paths_in_js "$file"; then
            TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
        fi
    fi
done

echo ""
echo -e "${YELLOW}Note: Some /_next/ strings may remain in inline JSON data (this is normal).${NC}"
echo ""

if [ $TOTAL_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ Success! All files have been processed.${NC}"
    exit 0
else
    echo -e "${RED}✗ Error: Failed to fix $TOTAL_ERRORS file(s).${NC}"
    exit 1
fi
