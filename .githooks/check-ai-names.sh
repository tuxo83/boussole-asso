#!/bin/bash
# ---------------------------------------------------------------------------
# NO ASSISTANT SIGNS ANYTHING HERE.
#
# The tools that write code leave their name behind by default: a
# `Co-Authored-By:` trailer, a session URL, an author field. This refuses it.
#
# The list is in the repository on purpose: every name in it is public, and
# anybody should be able to see what is refused and add to it. The other guard
# (guard.sh) is the opposite: its words must not be published, so it lives
# outside.
#
# Usage:
#   check-ai-names.sh message <file>   the commit message      (commit-msg)
#   check-ai-names.sh staged           git identity + added lines (pre-commit)
#   check-ai-names.sh range <range>    commits being pushed    (pre-push)
#
# Bash and grep -E only: this repository has no node, and the hooks run on
# whatever machine commits.
# ---------------------------------------------------------------------------
set -u

# This file has to name the names, so it exempts itself by path, and nothing else.
SELF=".githooks/check-ai-names.sh"

# Whole words, case-insensitive. Add freely.
NAMES='claude|anthropic|openai|chatgpt|copilot|gemini|codex|codeium|llama|bard|grok|deepseek|qwen|kimi|windsurf|aider|tabnine|opus|sonnet|haiku|fable|mistral|gpt-?[0-9]+(\.[0-9]+)?[a-z]?'

# A command somebody types is not a signature.
ALLOWED='claude[[:space:]]+mcp[[:space:]]+add'

# findings <where> : reads text on stdin, prints one line per finding, returns 1 if any
findings() {
    local where="$1" text found=0 line
    text="$(cat)"
    # The shape of a signature, whatever name it carries
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        echo "  $where: a co-author trailer -- ${line:0:90}"; found=1
    done < <(printf '%s\n' "$text" | grep -Ei '^[[:space:]]*Co-Authored-By:.*<[^@>]+@[^>]+>' | grep -Evi '@(example\.(com|org|net)|users\.noreply\.github\.com)>')
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        echo "  $where: a session identifier -- ${line:0:90}"; found=1
    done < <(printf '%s\n' "$text" | grep -E 'session_[A-Za-z0-9_-]{16,}' | grep -E 'session_[A-Za-z_-]*[0-9A-Z]')
    # The names
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        echo "  $where: an assistant is named -- ${line:0:90}"; found=1
    done < <(printf '%s\n' "$text" | sed -E "s/$ALLOWED/ /Ig" | grep -Eiw "$NAMES")
    return $found
}

refuse() {
    echo
    echo "REFUSED: an assistant is named."
    echo "Nothing in this repository is signed by an assistant -- not in a message,"
    echo "a trailer, the author field or a comment. See NAMES and ALLOWED in $SELF."
    exit 1
}

mode="${1:-}"
case "$mode" in
    message)
        # Only what git will keep: not the comments, not the diff below the
        # scissors line of "git commit -v"
        sed '/^# -\{24\} >8 -\{24\}$/,$d' "$2" | git stripspace --strip-comments \
            | findings "the commit message" || refuse
        echo "commit message: no assistant named"
        ;;
    staged)
        { git config user.name; git config user.email; } | findings "git identity" || bad=1
        # Added lines only: a commit that REMOVES a name must not be refused,
        # or the guard would forbid its own cleanup.
        git diff --cached --unified=0 | awk -v self="$SELF" '
            /^\+\+\+ / { f = $2; sub(/^b\//, "", f); in_self = (f == self); next }
            /^\+/ && !in_self { print }' | findings "a line this commit adds" || bad=1
        [ "${bad:-0}" = 1 ] && refuse
        echo "staged changes and git identity: no assistant named"
        ;;
    range)
        # Fails closed: a range that cannot be read has not been checked.
        if ! log="$(git log --format='%an%n%ae%n%B' "$2" 2>&1)"; then
            echo "REFUSED: cannot read the range $2 -- $log"
            exit 1
        fi
        printf '%s\n' "$log" | findings "a commit being pushed" || refuse
        echo "commits to push: no assistant named"
        ;;
    *)
        echo "usage: $0 message <file> | staged | range <range>"
        exit 2
        ;;
esac
