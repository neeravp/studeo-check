#!/bin/sh
# Replay golden fixtures against the check CLI.
# Copies each case to a temporary directory, executes the CLI with
# JSON formatting, and verifies stdout and process exit codes match
# expected artifacts. With --record, writes actual outputs as golden files.

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
php="${PHP:-php}"
record=0; cases=""; total=0; failed=0

for arg do
    case "$arg" in
        --record) record=1 ;;
        *) cases="$cases $arg" ;;
    esac
done

if [ -z "$cases" ]; then
    cases=$(find "$root/spec" -name check.php.txt | sort | while read -r p; do dirname "$p"; done)
fi

for c in $cases; do
    [ -d "$c" ] || continue
    total=$((total + 1))
    label="${c#*spec/}"
    label="${label%/}"
    tmp_base=$(mktemp -d 2>/dev/null || mktemp -d -t 'check-spec')
    tmp_tree="$tmp_base/tree"
    tmp_out="$tmp_base/out"
    mkdir -p "$tmp_tree"
    cp -R "$c/." "$tmp_tree"
    for f in $(find "$tmp_tree" -name '*.php.txt'); do mv "$f" "${f%.txt}"; done
    rm -f "$tmp_tree/expected.json" "$tmp_tree/expected.exit"
    args=""
    [ -f "$tmp_tree/args" ] && args=$(cat "$tmp_tree/args")
    (cd "$tmp_tree" && "$php" "$root/bin/check" --format=json $args > "$tmp_out" 2> /dev/null)
    code=$?
    if [ "$record" = 1 ]; then
        cp "$tmp_out" "$c/expected.json"
        echo "$code" > "$c/expected.exit"
        echo "recorded $label"
    else
        cmp -s "$tmp_out" "$c/expected.json" && j_ok=1 || j_ok=0
        exp_code=$(cat "$c/expected.exit" 2>/dev/null || echo "")
        [ "$code" = "$exp_code" ] && e_ok=1 || e_ok=0
        if [ "$j_ok" = 1 ] && [ "$e_ok" = 1 ]; then
            echo "ok $label"
        else
            [ "$j_ok" = 0 ] && [ "$e_ok" = 0 ] && fail_type="json+exit"
            [ "$j_ok" = 0 ] && [ "$e_ok" = 1 ] && fail_type="json"
            [ "$j_ok" = 1 ] && [ "$e_ok" = 0 ] && fail_type="exit"
            echo "FAIL $label $fail_type"
            failed=$((failed + 1))
        fi
    fi
    rm -rf "$tmp_base"
done

[ "$record" = 1 ] || { echo "$total cases, $failed failed"; [ "$failed" -eq 0 ]; }
