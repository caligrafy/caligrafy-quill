#!/bin/sh
# Caligrafy Quill plugin - PostToolUse hook
# Runs `php -l` on a .php file Claude just wrote or edited.
# Silent when the file is not PHP or when PHP is not installed (e.g. Docker-only setups).
# Exit code 2 sends the syntax error back to Claude so it can fix it.

command -v php >/dev/null 2>&1 || exit 0

FILE=$(php -r '$in = json_decode(stream_get_contents(STDIN), true); echo $in["tool_input"]["file_path"] ?? "";' 2>/dev/null)

case "$FILE" in
  *.php) ;;
  *) exit 0 ;;
esac
[ -f "$FILE" ] || exit 0

OUTPUT=$(php -l "$FILE" 2>&1)
if [ $? -ne 0 ]; then
  echo "php -l failed for $FILE:" >&2
  echo "$OUTPUT" >&2
  exit 2
fi
exit 0
