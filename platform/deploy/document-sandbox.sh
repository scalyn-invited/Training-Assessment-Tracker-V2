#!/bin/sh
# Linux template. Qualify on the chosen host before setting DOCUMENT_SANDBOX_WRAPPER.
# Only the parser, vendor runtime and one private working directory are mounted.
set -eu
work=$(realpath -- "$1")
shift
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)
case "$work" in "$root"/storage/app/private/extraction-work/*) ;; *) exit 64 ;; esac
test -d "$work"
test -x /usr/bin/bwrap
exec /usr/bin/bwrap --unshare-all --die-with-parent --new-session \
    --ro-bind /usr /usr --ro-bind /lib /lib --ro-bind-try /lib64 /lib64 \
    --ro-bind-try /etc/ld.so.cache /etc/ld.so.cache \
    --ro-bind /etc/php /etc/php \
    --proc /proc --dev /dev --tmpfs /tmp \
    --dir "$root" --ro-bind "$root/vendor" "$root/vendor" \
    --dir "$root/app" --dir "$root/app/Services" \
    --ro-bind "$root/app/Services/DocumentParser.php" "$root/app/Services/DocumentParser.php" \
    --dir "$root/scripts" --ro-bind "$root/scripts/document-extract.php" "$root/scripts/document-extract.php" \
    --bind "$work" "$work" --chdir "$work" \
    --setenv HOME /tmp --setenv TMPDIR /tmp --setenv OMP_THREAD_LIMIT 1 \
    "$@"
