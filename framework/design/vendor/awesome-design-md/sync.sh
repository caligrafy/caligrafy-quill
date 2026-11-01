#!/bin/sh
# Refreshes framework/design/vendor/awesome-design-md/<name>/DESIGN.md from VoltAgent/awesome-design-md (MIT, see LICENSE in this folder).
#
#   sh framework/design/vendor/awesome-design-md/sync.sh           # clones the latest upstream into a temporary folder
#   sh framework/design/vendor/awesome-design-md/sync.sh <clone>   # or copies from an existing clone
#
# Only the DESIGN.md files are copied (the upstream per-site README.md only points to getdesign.md);
# styles removed upstream are removed here, and SOURCE records the upstream commit.
set -eu
DEST=$(cd "$(dirname "$0")" && pwd)
SRC=${1:-}
if [ -z "$SRC" ]; then
    SRC=$(mktemp -d)
    trap 'rm -rf "$SRC"' EXIT
    git clone -q --depth 1 https://github.com/VoltAgent/awesome-design-md.git "$SRC"
fi
[ -d "$SRC/design-md" ] || { echo "sync.sh: $SRC/design-md not found" >&2; exit 1; }
for dir in "$DEST"/*/; do
    [ -d "$dir" ] || continue
    [ -d "$SRC/design-md/$(basename "$dir")" ] || rm -rf "$dir"
done
count=0
for dir in "$SRC"/design-md/*/; do
    name=$(basename "$dir")
    [ -f "$dir/DESIGN.md" ] || continue
    mkdir -p "$DEST/$name"
    cp "$dir/DESIGN.md" "$DEST/$name/DESIGN.md"
    count=$((count + 1))
done
cp "$SRC/LICENSE" "$DEST/LICENSE"
echo "https://github.com/VoltAgent/awesome-design-md $(git -C "$SRC" log -1 --format='%H %cs')" > "$DEST/SOURCE"
echo "sync.sh: $count styles in framework/design/vendor/awesome-design-md ($(cat "$DEST/SOURCE"))"
