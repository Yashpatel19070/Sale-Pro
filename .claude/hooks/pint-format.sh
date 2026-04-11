#!/usr/bin/env bash
# PostToolUse: Auto-runs Pint (PSR-12 formatter) after any PHP file is edited or written.
# This enforces code style automatically so Claude never needs to run Pint manually.

set -euo pipefail

INPUT=$(cat)

# Extract file_path from tool input
FILE_PATH=$(echo "$INPUT" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    tool_input = data.get('tool_input', data)
    print(tool_input.get('file_path', ''))
except:
    print('')
" 2>/dev/null || true)

# Only act on PHP files
if [[ "$FILE_PATH" != *.php ]]; then
    exit 0
fi

# Only run if the file actually exists
if [[ ! -f "$FILE_PATH" ]]; then
    exit 0
fi

PROJECT_ROOT="/Users/npc/sale-pro"
PINT="$PROJECT_ROOT/vendor/bin/pint"

if [[ ! -f "$PINT" ]]; then
    exit 0
fi

# Run Pint on the specific file (quiet — only show if there was a problem)
cd "$PROJECT_ROOT"
OUTPUT=$("$PINT" "$FILE_PATH" 2>&1) || true

# If Pint reformatted the file, let Claude know
if echo "$OUTPUT" | grep -q "FIXED\|fixed"; then
    echo "[PINT AUTO-FORMAT] Reformatted: $FILE_PATH"
fi

exit 0
