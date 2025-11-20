#!/bin/bash

# ensure that realpath exists in the environment
# (compatibility with macos)
if ! which realpath 2>&1 >/dev/null; then
    # declare a bash function which functions as
    # normal realpath in bash
    function realpath() {
        [[ $1 = /* ]] && echo "$1" || echo "$PWD/${1#./}"
    }
fi

# Print usage messages. First argument being the name of
# the command.
function usage() {
    echo "Usage: $1 [OPTIONS]"
    echo "Extracts translatable text from GibbonEdu source code to generate the"
    echo "i18n folder containing mo/po files."
    echo
    echo "Options:"
    echo
    echo "-h            Displaying this message."
    echo
    echo "-l <locale>   Locale to process. If not specified, all existing locale"
    echo "              will be processed."
    echo
    echo "-o <folder>   Setting output folder. Supposedly the folder(s) to output"
    echo "              the mo/po files."
    echo "              (Default: $I18N_HOME)"
    echo
    echo "-s <folder>   Source code folder of GibbonEdu/core."
    echo "              (Default: $GIBBON_HOME)"
    echo
}

# declare locale code in the array
# in alphbetical order.
declare -a LOCALES=(
    "af_ZA"
    "am_ET"
    "ar_SA"
    "bg_BG"
    "bn_BD"
    "da_DK"
    "de_DE"
    "el_GR"
    "en_GB"
    "en_US"
    "es_DO"
    "es_ES"
    "es_MX"
    "et_EE"
    "fa_IR"
    "fi_FI"
    "fr_FR"
    "he_IL"
    "hr_HR"
    "hu_HU"
    "id_ID"
    "in_OR"
    "it_IT"
    "ja_JP"
    "ka_GE"
    "ko_KP"
    "lt_LT"
    "my_MM"
    "nl_NL"
    "no_NO"
    "om_ET"
    "pl_PL"
    "pt_BR"
    "pt_PT"
    "ro_RO"
    "ru_RU"
    "sq_AL"
    "sw_KE"
    "th_TH"
    "tr_TR"
    "uk_UA"
    "ur_IN"
    "ur_PK"
    "vi_VN"
    "zh_CN"
    "zh_HK"
)

# default variables
GIBBON_HOME="/Applications/MAMP/htdocs/github_gibbonEdu/core/"
I18N_HOME=$(dirname $(realpath $0))
COLS=$(tput cols)
LOGFILE=$PWD/$(basename -s .sh $0).log

if [ ! -d "$GIBBON_HOME" ]; then
    # if the system do not have that default home directory,
    # use the parent folder of this source code repository
    # as the Gibbon source code home.
    GIBBON_HOME=$(dirname $(dirname $(realpath $0)))
fi

# get options to override default variables
while getopts "hl:o:s:" ARG; do
    case $ARG in
        h)
            usage $0
            exit 0
            ;;
        l)
            LOCALE="$OPTARG"
            ;;
        o)
            I18N_HOME=$(realpath "$OPTARG")
            ;;
        s)
            GIBBON_HOME=$(realpath "$OPTARG")
            ;;
        *)
            usage $0
            exit 1
            ;;
    esac
done

if [ ! -d "$GIBBON_HOME" ]; then
    echo "\"$GIBBON_HOME\" is not a valid directory."
    exit 1
fi
if [ ! -d "$I18N_HOME" ]; then
    echo "\"$I18N_HOME\" is not a valid directory."
    exit 1
fi

# remove trailing slash from I18N_HOME
I18N_HOME=${I18N_HOME%/}

# go to the Gibbon installation home folder
OLD_PWD=$PWD
cd $GIBBON_HOME

# generate the locale
if [ "$LOCALE" != "" ]; then
    if [ ! -d "$I18N_HOME/$LOCALE" ]; then
        echo "\"$I18N_HOME/$LOCALE\" is not a valid directory."
        exit 1
    fi
    declare -a LOCALES=($LOCALE)
fi

# loop through all the specified locale to run
# text extractions and compile catalog to binary
# format.
echo -en "" > $LOGFILE
FAIL=0
for LOCALE in "${LOCALES[@]}"
do
    echo -e "\nlocale: $LOCALE\n-------------"

    echo -en "* generate locale text file (.po)\r"
    # Create a temporary directory for cleaned files
    TEMP_DIR=$(mktemp -d)

    # Copy and clean files with problematic characters
    find . -type f \( -iname "*.php" -o -iname "*.twig.html" \) ! -path "./lib/*" ! -path "./tests/*" ! -path "./vendor/*" ! -path "./.git/*" ! -path "./uploads/*" | while read file; do
        # Create directory structure in temp
        mkdir -p "$TEMP_DIR/$(dirname "$file")"
        # Clean problematic characters and copy using perl for better Unicode support
        # Remove soft hyphens, replace smart quotes with regular quotes, remove \r characters
        perl -pe 's/\x{00AD}//g; s/\x{201C}/"/g; s/\x{201D}/"/g; s/\r//g;' "$file" > "$TEMP_DIR/$file"
    done

    # Change to temp directory for xgettext to use relative paths
    OLD_PWD_TEMP=$PWD
    cd "$TEMP_DIR"

    # Check if PO file exists to determine if we should append
    PO_FILE="$I18N_HOME/$LOCALE/LC_MESSAGES/gibbon.po"
    
    # Build array of files to process, handling paths with spaces correctly
    FILES_ARRAY=()
    while IFS= read -r -d '' file; do
        FILES_ARRAY+=("$file")
    done < <(find . -type f \( -iname "*.php" -o -iname "*.twig.html" \) -print0)
    
    if [ -f "$PO_FILE" ]; then
        # File exists, use -j to append (join) new entries
        xgettext \
            --from-code=utf-8 \
            --omit-header -j \
            --language=PHP \
            --keyword=__:1 \
            --keyword=__n:1,2 \
            --add-comments=TRANSLATORS: \
            --force-po \
            --package-name="GibbonEdu Core" \
            --package-version="1.0" \
            --msgid-bugs-address="translations@gibbonedu.org" \
            -o "$PO_FILE" \
            "${FILES_ARRAY[@]}" \
            2>>$LOGFILE >/dev/null
    else
        # File doesn't exist, create new file without -j
        xgettext \
            --from-code=utf-8 \
            --omit-header \
            --language=PHP \
            --keyword=__:1 \
            --keyword=__n:1,2 \
            --add-comments=TRANSLATORS: \
            --force-po \
            --package-name="GibbonEdu Core" \
            --package-version="1.0" \
            --msgid-bugs-address="translations@gibbonedu.org" \
            -o "$PO_FILE" \
            "${FILES_ARRAY[@]}" \
            2>>$LOGFILE >/dev/null
    fi

    # Return to original directory
    cd "$OLD_PWD_TEMP"

    # Clean up temporary directory
    rm -rf "$TEMP_DIR"

    # Deduplicate location references in PO file and optionally replace spaces to conform with PO format specifications
    if [ -f "$PO_FILE" ]; then
        SCRIPT_DIR=$(dirname $(realpath "$0"))
        DEDUP_SCRIPT="$SCRIPT_DIR/scripts/deduplicate_po_locations.pl"
        if [ -f "$DEDUP_SCRIPT" ]; then
            # Use --replace-spaces flag to replace spaces with correct quoting to conform with PO format specifications
            perl "$DEDUP_SCRIPT" "$PO_FILE" "$PO_FILE.tmp" --replace-spaces 2>/dev/null
            if [ -f "$PO_FILE.tmp" ]; then
                mv "$PO_FILE.tmp" "$PO_FILE"
            fi
        fi
    fi

    # Remove duplicate empty msgid entries (keep only the first one in header)
    if [ -f "$PO_FILE" ]; then
        # Use perl to remove duplicate empty msgid entries
        # Keep the first msgid "" (header) and remove all subsequent ones
        perl -i -0777 -pe '
            # Split by msgid "" entries
            my @parts = split(/(?=^msgid ""$)/m, $_);
            my $result = "";
            my $first = 1;
            foreach my $part (@parts) {
                if ($part =~ /^msgid ""$/m) {
                    if ($first) {
                        # Keep first occurrence (header)
                        $result .= $part;
                        $first = 0;
                    } else {
                        # Remove subsequent empty msgid entries
                        # Remove msgid "" and its msgstr "" and following blank line
                        $part =~ s/^msgid ""\nmsgstr ""\n\n?//m;
                        $result .= $part;
                    }
                } else {
                    $result .= $part;
                }
            }
            $_ = $result;
        ' "$PO_FILE"
    fi

    # Fix PO file header for proper charset and plural forms
    # Ensure charset is always set correctly
    if [ -f "$PO_FILE" ]; then
        # Check if charset is missing or incorrect
        if ! grep -q "Content-Type: text/plain; charset=UTF-8" "$PO_FILE" 2>/dev/null || ! grep -q "charset=UTF-8" "$PO_FILE" 2>/dev/null; then
            # Use perl to fix the charset in the header msgstr
            perl -i -pe '
                if (/^msgstr ""$/) {
                    $in_msgstr = 1;
                }
                if ($in_msgstr && /^"Content-Type: text\/plain; charset=/) {
                    s/charset=[^"\\n]+/charset=UTF-8/;
                }
                if ($in_msgstr && /^"Content-Type: text\/plain;\\n"$/) {
                    # Missing charset, add it
                    s/"Content-Type: text\/plain;\\n"/"Content-Type: text\/plain; charset=UTF-8\\n"/;
                }
                if ($in_msgstr && /^"Plural-Forms:/) {
                    $in_msgstr = 0;
                }
            ' "$PO_FILE"
            
            # If still no charset found, rebuild header
            if ! grep -q "charset=UTF-8" "$PO_FILE" 2>/dev/null; then
                TEMP_PO=$(mktemp)
                cat > "$TEMP_PO" << EOF
# SOME DESCRIPTIVE TITLE.
# Copyright (C) YEAR THE PACKAGE'S COPYRIGHT HOLDER
# This file is distributed under the same license as the GibbonEdu Core package.
# FIRST AUTHOR <EMAIL@ADDRESS>, YEAR.
#
msgid ""
msgstr ""
"Project-Id-Version: GibbonEdu Core 1.0\\n"
"Report-Msgid-Bugs-To: translations@gibbonedu.org\\n"
"POT-Creation-Date: $(date '+%Y-%m-%d %H:%M%z')\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: $LOCALE\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"

EOF
                # Append the rest of the PO file (skip the header)
                tail -n +17 "$PO_FILE" >> "$TEMP_PO"
                mv "$TEMP_PO" "$PO_FILE"
            fi
        fi
    fi

    if [ "$?" -eq 0 ]; then
        echo -e  "* generate locale text file (.po)\t\t\e[32m✓\e[0m"
    else
        echo -e  "* generate locale text file (.po)\t\t\e[31m✘\e[0m"
        FAIL=1
    fi

    echo -en "* generate locale binary file (.mo)\r"
    msgfmt --check-header --check-domain -v \
        -o "$I18N_HOME/$LOCALE/LC_MESSAGES/gibbon.mo" \
        "$PO_FILE" \
        2>>$LOGFILE >/dev/null
    if [ "$?" -eq 0 ]; then
        echo -e  "* generate locale binary file (.mo)\t\t\e[32m✓\e[0m"
    else
        echo -e  "* generate locale binary file (.mo)\t\t\e[31m✘\e[0m"
        FAIL=1
    fi
done

# Show log, if needed
if [ $FAIL -eq 1 ]; then
    echo
    echo Failed
    echo ------
    cat $LOGFILE
    exit 1
fi

# return to original folder
cd $OLD_PWD
