#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): valida la regla de oro de COMENTARIOS sobre el
# archivo recién escrito y, si la viola, devuelve el error en el momento
# (exit 2) para corregir antes de correr los tests.
#
# Es la capa C. La garantía permanente es tests/Feature/GoldenRulesCommentsTest.php
# (capa B). Las dos comparten el MISMO scanner (tests/Support/CommentScanner.php),
# así que no pueden divergir.
#
# Regla: .ai/guidelines/comentarios.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -n "$file" ] || exit 0

# Solo el código que la regla alcanza. lang/ queda afuera a propósito.
case "$file" in
    */lang/*) exit 0 ;;
    */app/*.php|*/database/*.php|*/tests/*.php|*/routes/*.php|*/config/*.php) ;;
    */resources/views/*.blade.php|*/resources/js/*.js) ;;
    *) exit 0 ;;
esac

root="/var/www/atendia"
rel="${file#"$root"/}"

[ -f "$file" ] || exit 0

output=$(docker exec -w /var/www/html atendia-app php tests/Support/comment_check.php "$rel" 2>&1)

if [ $? -ne 0 ]; then
    echo "$output" >&2
    exit 2
fi

exit 0
