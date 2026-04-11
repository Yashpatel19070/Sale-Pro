#!/usr/bin/env bash
# PreToolUse: Before writing any PHP file, outputs the relevant reference file content
# so Claude always has the full, authoritative rules in context — not a summary.

set -euo pipefail

INPUT=$(cat)

FILE_PATH=$(echo "$INPUT" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    tool_input = data.get('tool_input', data)
    print(tool_input.get('file_path', ''))
except:
    print('')
" 2>/dev/null || true)

if [[ "$FILE_PATH" != *.php ]]; then
    exit 0
fi

REFS="/Users/npc/sale-pro/.claude/skills/references"

if [[ "$FILE_PATH" == *"/Controllers/"* ]]; then
    REF="$REFS/controller.md"
elif [[ "$FILE_PATH" == *"/Services/"* ]]; then
    REF="$REFS/service.md"
elif [[ "$FILE_PATH" == *"/Requests/"* ]]; then
    REF="$REFS/form-request.md"
elif [[ "$FILE_PATH" == *"/Models/"* ]]; then
    REF="$REFS/model.md"
elif [[ "$FILE_PATH" == *"/Policies/"* ]]; then
    REF="$REFS/permissions-spatie.md"
elif [[ "$FILE_PATH" == *"Test"* || "$FILE_PATH" == *"/tests/"* ]]; then
    REF="$REFS/testing.md"
elif [[ "$FILE_PATH" == *"/database/migrations/"* ]]; then
    REF="$REFS/database.md"
elif [[ "$FILE_PATH" == *"/Middleware/"* ]]; then
    REF="$REFS/middleware.md"
elif [[ "$FILE_PATH" == *"/Jobs/"* || "$FILE_PATH" == *"/Events/"* || "$FILE_PATH" == *"/Listeners/"* ]]; then
    REF="$REFS/queue-events.md"
else
    REF="$REFS/code-style.md"
fi

echo "[PHP PATTERN GUARD] $FILE_PATH — rules from: $REF"
echo "---"
cat "$REF"

exit 0
