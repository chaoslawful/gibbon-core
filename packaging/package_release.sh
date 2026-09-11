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
    -n, --no-vendor-lib  Exclude vendor and lib directories from the package
    -k, --skills-zip     Also package each skill under modules/API/skills/ into versioned
                         zip + tar.gz plus a generated manifest.json, installable and
                         update-checkable by other agent tools (each skill's artifacts go
                         to <output>/skills/<skill-name>/).
                         Skill version comes from each SKILL.md frontmatter "version:"
                         and must match modules/API/version.php \$moduleVersion (not the core version).
    -h, --help           Show this help message

Examples:
    $0
    $0 -s /path/to/gibbon-core -o /tmp/releases
    $0 --source /home/user/gibbon-core --output /home/user/releases
    $0 -n                    # Package without vendor and lib directories
    $0 -k                    # Also create skill packages (zip/tar.gz/manifest.json)

If options are not specified, default values will be used:
    - Source directory: Parent directory of script location (if script is in packaging/ subdirectory)
                        or current working directory
    - Output directory: Parent directory of source directory

Skill packaging (optional, only used with -k, set via environment variables):
    GIBBON_SKILLS_BASE_URL        Public base URL written into manifest.json
                                  (default: https://SKILL_HOST/skills)

Uploading the skill packages to a server is NOT done by this script —
copy <output>/skills/ manually (upload each skill's manifest.json LAST).

EOF
}

# Default configuration (will be set after parsing arguments)
SOURCE_DIR=""
OUTPUT_DIR=""
SKIP_VENDOR_LIB=false
PACKAGE_SKILLS=false

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
        -n|--no-vendor-lib)
            SKIP_VENDOR_LIB=true
            shift
            ;;
        -k|--skills-zip)
            PACKAGE_SKILLS=true
            shift
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

# Convert to absolute paths (create output directory first if missing,
# so the cd below can resolve it)
SOURCE_DIR="$(cd "$SOURCE_DIR" && pwd)"
if [ ! -d "$OUTPUT_DIR" ]; then
    echo -e "${YELLOW}Warning: Output directory does not exist, creating: $OUTPUT_DIR${NC}"
    mkdir -p "$OUTPUT_DIR" || {
        echo -e "${RED}Error: Cannot create output directory: $OUTPUT_DIR${NC}"
        exit 1
    }
fi
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

# API module version ($moduleVersion in modules/API/version.php). Must match
# each skill's SKILL.md version:; agents also compare it with /v1/openapi.json.
module_version() {
    sed -n "s/.*\$moduleVersion[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" \
        "$SOURCE_DIR/modules/API/version.php" | head -1
}

# Skill version from a SKILL.md frontmatter (between the first pair of ---
# lines). Single source of truth for skill packaging — never fall back to the
# core version.
skill_version() {
    awk 'NR==1 { if ($0 != "---") exit }
         NR>1 { if ($0 == "---") exit
                if ($1 == "version:") { sub(/^version:[[:space:]]*/, ""); gsub(/[" ]/, ""); print; exit } }' \
        "$1/SKILL.md"
}

echo -e "${GREEN}Starting packaging of Gibbon Core ${VERSION}${NC}"
echo -e "${BLUE}Configuration:${NC}"
echo "  Source directory: $SOURCE_DIR"
echo "  Output directory: $OUTPUT_DIR"
echo "  Exclude vendor/lib: $SKIP_VENDOR_LIB"
echo "  Package skill zips: $PACKAGE_SKILLS"
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
if [ "$SKIP_VENDOR_LIB" = "false" ] && [ -d "$SOURCE_DIR/lib" ]; then
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
if [ "$SKIP_VENDOR_LIB" = "false" ] && [ -d "$SOURCE_DIR/vendor" ]; then
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

# 7. Remove all .git directories and local secret files recursively
echo -e "${GREEN}[7/9] Removing .git directories and local secret files...${NC}"
find "$PACKAGE_DIR" -type d \( -name ".git" -o -name ".workbuddy" \) -exec rm -rf {} + 2>/dev/null || true
# Remove .env / .env.* variants (keep .env.example templates) so local tokens never ship
find "$PACKAGE_DIR" -type f \( -name ".env" -o -name ".env.*" \) ! -name ".env.example" -exec rm -f {} + 2>/dev/null || true

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

# 10. Package agent-installable skills (optional, -k/--skills-zip)
if [ "$PACKAGE_SKILLS" = "true" ]; then
    echo -e "${GREEN}[skills] Packaging agent-installable skills...${NC}"
    SKILLS_SRC="$SOURCE_DIR/modules/API/skills"

    if [ ! -d "$SKILLS_SRC" ]; then
        echo -e "${RED}Error: Skills directory not found: $SKILLS_SRC${NC}"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    if ! command -v zip >/dev/null 2>&1; then
        echo -e "${RED}Error: 'zip' command is required for skill packaging but not installed${NC}"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    MOD_VER="$(module_version)"
    if [ -z "$MOD_VER" ]; then
        echo -e "${RED}Error: Cannot extract \$moduleVersion from $SOURCE_DIR/modules/API/version.php${NC}"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    SKILLS_BASE_URL="${GIBBON_SKILLS_BASE_URL:-https://SKILL_HOST/skills}"
    SKILLS_OUTPUT_DIR="$OUTPUT_DIR/skills"
    SKILLS_STAGE="$TEMP_DIR/skills-stage"
    mkdir -p "$SKILLS_OUTPUT_DIR"

    # sha256 helper: sha256sum → shasum → openssl fallback chain (the skill-side
    # update script in SKILL.md mirrors this chain)
    sha256_of() {
        if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | cut -d' ' -f1
        elif command -v shasum >/dev/null 2>&1; then shasum -a 256 "$1" | cut -d' ' -f1
        else openssl dgst -sha256 "$1" | awk '{print $NF}'; fi
    }

    # Cap stdin at N Unicode characters without splitting a UTF-8 sequence.
    # `cut -c` is byte-oriented (even under UTF-8 locales on GNU coreutils 8.32)
    # and would emit invalid UTF-8 when the cap lands inside a Chinese character.
    utf8_trunc_chars() {
        local n="${1:-500}"
        iconv -f UTF-8 -t UTF-32BE | head -c $((n * 4)) | iconv -f UTF-32BE -t UTF-8
    }

    # Collect installable skills first so an empty set is reported once, not silently
    SKILL_DIRS=()
    for skill_dir in "$SKILLS_SRC"/*/; do
        [ -d "$skill_dir" ] || continue
        if [ ! -f "$skill_dir/SKILL.md" ]; then
            echo -e "${YELLOW}Warning: $skill_dir has no SKILL.md, skipping${NC}"
            continue
        fi
        SKILL_DIRS+=("$skill_dir")
    done

    if [ "${#SKILL_DIRS[@]}" -eq 0 ]; then
        echo -e "${YELLOW}Warning: No skills found under $SKILLS_SRC${NC}"
    else
        for skill_dir in "${SKILL_DIRS[@]}"; do
            skill_name="$(basename "$skill_dir")"

            SKILL_VER="$(skill_version "$skill_dir")"
            if ! echo "$SKILL_VER" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
                echo -e "${RED}Error: $skill_name/SKILL.md frontmatter has no valid semver 'version:' (found: '${SKILL_VER:-<none>}'). Fix it — the skill version never falls back to the core version.${NC}"
                rm -rf "$TEMP_DIR"
                exit 1
            fi
            if [ "$SKILL_VER" != "$MOD_VER" ]; then
                echo -e "${RED}Error: $skill_name version '$SKILL_VER' must match API module version '$MOD_VER'. Bump both together on any functional change.${NC}"
                rm -rf "$TEMP_DIR"
                exit 1
            fi

            # reference.md backtick paths must be a subset of Spec.php path keys
            if [ -f "$skill_dir/reference.md" ]; then
                missing_paths="$(php -r '
$spec = file_get_contents($argv[1]);
$ref = file_get_contents($argv[2]);
preg_match_all("#'\''(/v1/[^'\'']+)'\''#", $spec, $m1);
preg_match_all("#`(/v1/[A-Za-z0-9_{}/.-]+)`#", $ref, $m2);
$specPaths = array_flip($m1[1]);
$missing = [];
foreach (array_unique($m2[1]) as $path) {
    if (!isset($specPaths[$path])) {
        $missing[] = $path;
    }
}
if ($missing) {
    fwrite(STDERR, implode("\n", $missing));
    exit(1);
}
' "$SOURCE_DIR/modules/API/src/OpenApi/Spec.php" "$skill_dir/reference.md" 2>&1)" || true
                if [ -n "$missing_paths" ]; then
                    echo -e "${RED}Error: $skill_name/reference.md has routes missing from OpenAPI Spec.php:${NC}"
                    echo "$missing_paths"
                    rm -rf "$TEMP_DIR"
                    exit 1
                fi
            fi

            # Stage the skill directory, strip local-only files (same rules as the
            # main package cleanup), then build both archive formats from the
            # staging copy so their contents match exactly and no find | zip -@
            # pipe is needed (it breaks on filenames with spaces).
            mkdir -p "$SKILLS_STAGE"
            rm -rf "$SKILLS_STAGE/$skill_name"
            cp -r "$skill_dir" "$SKILLS_STAGE/$skill_name"
            find "$SKILLS_STAGE/$skill_name" -type d \( -name ".git" -o -name ".workbuddy" \) -exec rm -rf {} + 2>/dev/null || true
            find "$SKILLS_STAGE/$skill_name" -type f \( -name ".env" -o -name ".env.*" \) ! -name ".env.example" -exec rm -f {} + 2>/dev/null || true

            # Each skill gets its own output subdir: skills/<name>/{zip,tar.gz,manifest.json}
            skill_out="$SKILLS_OUTPUT_DIR/$skill_name"
            mkdir -p "$skill_out"

            zip_file="$skill_out/${skill_name}-${SKILL_VER}.zip"
            tar_file="$skill_out/${skill_name}-${SKILL_VER}.tar.gz"
            rm -f "$zip_file" "$tar_file"
            (cd "$SKILLS_STAGE" && zip -q -r -X "$zip_file" "$skill_name")
            (cd "$SKILLS_STAGE" && tar -czf "$tar_file" "$skill_name")

            if [ ! -s "$zip_file" ] || [ ! -s "$tar_file" ]; then
                echo -e "${RED}Error: Failed to create archives for skill: $skill_name${NC}"
                rm -rf "$TEMP_DIR"
                exit 1
            fi

            # Release notes: the "## <version>" section of CHANGELOG.md, sanitized
            # (no quotes/backslashes/newlines) and capped at 500 Unicode characters
            # so the consumer-side line-based extraction cannot break.
            notes="$(awk -v ver="$SKILL_VER" '
                !insec && $0 ~ ("^##[[:space:]]+" ver "([[:space:]]|$)") { insec = 1; next }
                insec && /^##[[:space:]]/ { insec = 0 }
                insec && NF { print }' "$skill_dir/CHANGELOG.md" 2>/dev/null \
                | tr '\n' ' ' | tr -d '"\\' | tr -s ' ' | utf8_trunc_chars 500)"
            if [ -z "$notes" ]; then
                echo -e "${YELLOW}Warning: no '## ${SKILL_VER}' section found in $skill_name/CHANGELOG.md — manifest 'notes' will be empty${NC}"
            fi

            zip_sha="$(sha256_of "$zip_file")"
            tar_sha="$(sha256_of "$tar_file")"
            zip_size="$(wc -c < "$zip_file" | tr -d '[:space:]')"
            released_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
            base_url="${SKILLS_BASE_URL%/}"

            # Own subdir per skill, so the manifest is always plain manifest.json
            manifest_file="$skill_out/manifest.json"

            # One field per line; values carry no quotes/newlines — the skill-side
            # update script (SKILL.md) relies on both guarantees.
            cat > "$manifest_file" << EOF
{
  "name": "$skill_name",
  "version": "$SKILL_VER",
  "moduleVersion": "$MOD_VER",
  "zipUrl": "$base_url/$skill_name/${skill_name}-${SKILL_VER}.zip",
  "tarUrl": "$base_url/$skill_name/${skill_name}-${SKILL_VER}.tar.gz",
  "sha256": "$zip_sha",
  "tarSha256": "$tar_sha",
  "size": $zip_size,
  "releasedAt": "$released_at",
  "notes": "$notes"
}
EOF

            echo "  Created: skills/${skill_name}/${skill_name}-${SKILL_VER}.zip ($(du -h "$zip_file" | cut -f1)) + .tar.gz + manifest.json"
        done

        echo ""
        echo -e "${GREEN}✓ ${#SKILL_DIRS[@]} skill package(s) created in $SKILLS_OUTPUT_DIR${NC}"
        echo "  Install: unzip into the target agent's skills directory"
        echo "  Upload is manual: copy the archives first, the manifest(s) LAST"
    fi
    echo ""
fi

# Clean up temporary directory
rm -rf "$TEMP_DIR"

echo -e "${GREEN}Done!${NC}"

