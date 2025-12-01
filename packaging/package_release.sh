#!/bin/bash

# Gibbon Core Release Packaging Script
# Package release version from development directory based on v30.0.00 file patterns

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Show help information
show_help() {
    cat << EOF
Usage: $0 [OPTIONS]

Options:
    -s, --source DIR     Specify source repository directory (Gibbon Core source code directory)
    -o, --output DIR     Specify output directory for packaged files
    -h, --help           Show this help message

Examples:
    $0
    $0 -s /path/to/gibbon-core -o /tmp/releases
    $0 --source /home/user/gibbon-core --output /home/user/releases

If options are not specified, default values will be used:
    - Source directory: Parent directory of script location (if script is in packaging/ subdirectory)
                        or current working directory
    - Output directory: Parent directory of source directory

EOF
}

# Default configuration (will be set after parsing arguments)
SOURCE_DIR=""
OUTPUT_DIR=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -s|--source)
            SOURCE_DIR="$2"
            shift 2
            ;;
        -o|--output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo -e "${RED}Error: Unknown argument '$1'${NC}"
            echo "Use -h or --help to see help information"
            exit 1
            ;;
    esac
done

# Auto-detect source directory if not specified
if [ -z "$SOURCE_DIR" ]; then
    # Get script directory
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"
    
    # If script is in packaging/ subdirectory, use parent directory
    if [ "$(basename "$SCRIPT_DIR")" = "packaging" ]; then
        SOURCE_DIR="$(dirname "$SCRIPT_DIR")"
    else
        # Otherwise use current working directory
        SOURCE_DIR="$(pwd)"
    fi
fi

# Use parent directory of source as output if not specified
if [ -z "$OUTPUT_DIR" ]; then
    OUTPUT_DIR="$(dirname "$SOURCE_DIR")"
fi

# Convert to absolute paths
SOURCE_DIR="$(cd "$SOURCE_DIR" && pwd)"
OUTPUT_DIR="$(cd "$OUTPUT_DIR" && pwd)"

# Validate source directory
if [ ! -d "$SOURCE_DIR" ]; then
    echo -e "${RED}Error: Source directory does not exist: $SOURCE_DIR${NC}"
    exit 1
fi

if [ ! -f "$SOURCE_DIR/version.php" ]; then
    echo -e "${RED}Error: version.php not found in source directory: $SOURCE_DIR${NC}"
    exit 1
fi

# Validate output directory
if [ ! -d "$OUTPUT_DIR" ]; then
    echo -e "${YELLOW}Warning: Output directory does not exist, creating: $OUTPUT_DIR${NC}"
    mkdir -p "$OUTPUT_DIR"
    if [ $? -ne 0 ]; then
        echo -e "${RED}Error: Cannot create output directory: $OUTPUT_DIR${NC}"
        exit 1
    fi
fi

# Extract version number
# Match format: $version = 'X.X.XX' or 'version' => 'X.X.XX'
VERSION_LINE=$(grep -E "\\\$version\\s*=|'version'\\s*=>" "$SOURCE_DIR/version.php" 2>/dev/null | head -1)

if [ -z "$VERSION_LINE" ]; then
    echo -e "${RED}Error: Cannot find version line in version.php${NC}"
    echo "Please check that version.php exists and contains: \$version = 'X.X.XX' or 'version' => 'X.X.XX'"
    exit 1
fi

if [ -n "$VERSION_LINE" ]; then
    # Try to extract from $version = 'X.X.XX' format
    if echo "$VERSION_LINE" | grep -q "\\\$version"; then
        VERSION=$(echo "$VERSION_LINE" | sed -E "s/.*\\\$version[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/")
    # Try to extract from 'version' => 'X.X.XX' format
    elif echo "$VERSION_LINE" | grep -q "'version'"; then
        VERSION=$(echo "$VERSION_LINE" | sed -E "s/.*'version'[[:space:]]*=>[[:space:]]*'([^']+)'.*/\\1/")
    fi
fi

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Cannot extract version number from version.php${NC}"
    echo "Found line: $VERSION_LINE"
    echo "Please check that version.php contains: \$version = 'X.X.XX' or 'version' => 'X.X.XX'"
    exit 1
fi

PACKAGE_NAME="gibbon-core-${VERSION}"
TEMP_DIR=$(mktemp -d)
PACKAGE_DIR="$TEMP_DIR/$PACKAGE_NAME"

echo -e "${GREEN}Starting packaging of Gibbon Core ${VERSION}${NC}"
echo -e "${BLUE}Configuration:${NC}"
echo "  Source directory: $SOURCE_DIR"
echo "  Output directory: $OUTPUT_DIR"
echo "  Version: $VERSION"
echo "  Temporary directory: $TEMP_DIR"
echo ""

# Create package directory
mkdir -p "$PACKAGE_DIR"

# Function: Copy file or directory (excluding .git directories)
copy_item() {
    local src="$SOURCE_DIR/$1"
    local dst="$PACKAGE_DIR/$1"
    
    if [ ! -e "$src" ]; then
        echo -e "${YELLOW}Warning: $src does not exist, skipping${NC}"
        return 1
    fi
    
    # Skip if source is a .git directory
    if [ -d "$src" ] && [ "$(basename "$src")" = ".git" ]; then
        return 0
    fi
    
    # Create target directory
    mkdir -p "$(dirname "$dst")"
    
    # Copy file or directory
    if [ -d "$src" ]; then
        cp -r "$src" "$dst" 2>/dev/null || true
        # Remove any .git directories that were copied
        find "$dst" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true
    else
        cp "$src" "$dst"
    fi
}

# 1. Copy root directory files (excluding development-related files)
echo -e "${GREEN}[1/9] Copying root directory files...${NC}"
cd "$SOURCE_DIR"
# Use find to precisely match root directory files, excluding .git
find . -maxdepth 1 -type f \( \
    -name "*.php" -o \
    -name "*.sql" -o \
    -name "*.txt" -o \
    -name "*.md" -o \
    -name "*.ico" -o \
    -name "LICENSE" -o \
    -name "robots.txt" -o \
    -name ".htaccess" \
\) ! -name "*.sh" ! -name "*.py" ! -path "*/.git/*" | while read file; do
    rel_file="${file#./}"
    copy_item "$rel_file"
done

# 2. Copy main directories
echo -e "${GREEN}[2/9] Copying main directories...${NC}"

# cli directory
if [ -d "$SOURCE_DIR/cli" ]; then
    copy_item "cli"
fi

# installer directory
if [ -d "$SOURCE_DIR/installer" ]; then
    copy_item "installer"
fi

# lib directory (all third-party libraries)
if [ -d "$SOURCE_DIR/lib" ]; then
    copy_item "lib"
fi

# modules directory (all modules)
if [ -d "$SOURCE_DIR/modules" ]; then
    copy_item "modules"
fi

# resources directory
if [ -d "$SOURCE_DIR/resources" ]; then
    copy_item "resources"
fi

# src directory (source code)
if [ -d "$SOURCE_DIR/src" ]; then
    copy_item "src"
fi

# themes directory
if [ -d "$SOURCE_DIR/themes" ]; then
    copy_item "themes"
fi

# uploads directory (copy structure only, not user-uploaded content)
echo -e "${GREEN}[3/9] Processing uploads directory...${NC}"
if [ -d "$SOURCE_DIR/uploads" ]; then
    # Copy directory structure only, exclude actual files and .git directories
    find "$SOURCE_DIR/uploads" -type d ! -path "*/.git" ! -path "*/.git/*" | while read dir; do
        rel_dir="${dir#$SOURCE_DIR/}"
        mkdir -p "$PACKAGE_DIR/$rel_dir"
        # Copy .htaccess and other configuration files
        if [ -f "$dir/.htaccess" ]; then
            cp "$dir/.htaccess" "$PACKAGE_DIR/$rel_dir/.htaccess"
        fi
    done
fi

# 4. Process i18n directory (exclude zh_CN and .git, as it's not in v30)
echo -e "${GREEN}[4/9] Processing i18n directory...${NC}"
if [ -d "$SOURCE_DIR/i18n" ]; then
    # Use find to exclude zh_CN and .git directories
    find "$SOURCE_DIR/i18n" -type d ! -path "*/zh_CN/*" ! -name "zh_CN" ! -path "*/.git" ! -path "*/.git/*" | while read dir; do
        rel_dir="${dir#$SOURCE_DIR/}"
        mkdir -p "$PACKAGE_DIR/$rel_dir"
    done
    
    # Copy files (excluding zh_CN and .git)
    find "$SOURCE_DIR/i18n" -type f ! -path "*/zh_CN/*" ! -path "*/.git/*" | while read file; do
        rel_file="${file#$SOURCE_DIR/}"
        copy_item "$rel_file"
    done
fi

# 5. Copy vendor directory (composer dependencies)
echo -e "${GREEN}[5/9] Copying vendor directory...${NC}"
if [ -d "$SOURCE_DIR/vendor" ]; then
    copy_item "vendor"
fi

# 6. Copy composer.json and composer.lock
echo -e "${GREEN}[6/9] Copying composer files...${NC}"
if [ -f "$SOURCE_DIR/composer.json" ]; then
    copy_item "composer.json"
fi
if [ -f "$SOURCE_DIR/composer.lock" ]; then
    copy_item "composer.lock"
fi

# 7. Remove all .git directories recursively
echo -e "${GREEN}[7/9] Removing .git directories...${NC}"
find "$PACKAGE_DIR" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true

# 8. Validate critical files exist
echo -e "${GREEN}[8/9] Validating critical files...${NC}"
MISSING_FILES=()
CRITICAL_FILES=(
    "gibbon.php"
    "index.php"
    "functions.php"
    "version.php"
    "composer.json"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ ! -f "$PACKAGE_DIR/$file" ]; then
        MISSING_FILES+=("$file")
    fi
done

if [ ${#MISSING_FILES[@]} -gt 0 ]; then
    echo -e "${RED}Error: Missing critical files:${NC}"
    for file in "${MISSING_FILES[@]}"; do
        echo "  - $file"
    done
    rm -rf "$TEMP_DIR"
    exit 1
fi

# 9. Create tarball (without top-level directory)
echo -e "${GREEN}[9/9] Creating tarball...${NC}"
cd "$PACKAGE_DIR"
# Create tarball with files directly in root, not in a subdirectory
tar -czf "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" .

# Calculate file size
SIZE=$(du -h "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" | cut -f1)

echo ""
echo -e "${GREEN}✓ Packaging complete!${NC}"
echo "  Package name: ${PACKAGE_NAME}.tar.gz"
echo "  Location: $OUTPUT_DIR/${PACKAGE_NAME}.tar.gz"
echo "  Size: $SIZE"
echo ""

# Clean up temporary directory
rm -rf "$TEMP_DIR"

echo -e "${GREEN}Done!${NC}"

