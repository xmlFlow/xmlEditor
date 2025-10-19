#!/bin/bash

##############################################################################
# Fix Next.js Build Paths Script
#
# This script fixes the hardcoded /_next/ paths in the Next.js build output
# to use relative paths (../_next/) so they work correctly when embedded
# in the OJS plugin via iframe.
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
EDITOR_DIR="$SCRIPT_DIR/editor/editor"

echo -e "${YELLOW}=== Fixing Next.js Build Paths ===${NC}"
echo ""

# Check if the editor directory exists
if [ ! -d "$EDITOR_DIR" ]; then
    echo -e "${RED}Error: Editor directory not found at: $EDITOR_DIR${NC}"
    exit 1
fi

# Check if index.html exists
if [ ! -f "$EDITOR_DIR/index.html" ]; then
    echo -e "${RED}Error: index.html not found at: $EDITOR_DIR/index.html${NC}"
    exit 1
fi

echo -e "Processing: ${YELLOW}$EDITOR_DIR/index.html${NC}"

# Count occurrences before fixing (only count in href/src attributes)
BEFORE_COUNT=$(grep -oE '(href|src)="/_next/' "$EDITOR_DIR/index.html" | wc -l)
echo -e "Found ${YELLOW}$BEFORE_COUNT${NC} hardcoded /_next/ paths in href/src attributes"

# Fix the paths - handle all variations including inline scripts
sed -i 's|"/_next/|"../_next/|g; s|'\''/_next/|'\''../_next/|g' "$EDITOR_DIR/index.html"

# Count occurrences after fixing (only count in href/src attributes)
AFTER_COUNT=$(grep -oE '(href|src)="/_next/' "$EDITOR_DIR/index.html" | wc -l)
FIXED_COUNT=$(grep -oE '(href|src)="\.\./\._next/' "$EDITOR_DIR/index.html" | wc -l)

echo -e "Remaining hardcoded paths: ${YELLOW}$AFTER_COUNT${NC}"
echo -e "Fixed relative paths: ${GREEN}$FIXED_COUNT${NC}"

if [ $AFTER_COUNT -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ Success! All href/src paths have been fixed.${NC}"
    echo -e "${YELLOW}Note: Some /_next/ strings may remain in inline JSON data (this is normal).${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}✗ Error: Some /_next/ paths in href/src attributes were not fixed.${NC}"
    exit 1
fi
