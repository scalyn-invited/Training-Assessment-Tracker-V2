#!/usr/bin/env bash
# Run as the dedicated backup account. Configure paths; never put passwords in command arguments.
set -euo pipefail
umask 077
: "${TRAINING_ROOT:?Set absolute application path}"
: "${BACKUP_DIRECTORY:?Set offsite-staged backup directory}"
: "${MARIADB_DEFAULTS_FILE:?Set owner-only MariaDB client option file}"
: "${BACKUP_AGE_RECIPIENT:?Set age public recipient; keep private identity off this host}"
: "${TRAINING_DATABASE:?Set the approved database name}"
[[ "$TRAINING_DATABASE" =~ ^[a-zA-Z0-9_]+$ ]] || exit 2
[[ "$TRAINING_ROOT" = /* && "$BACKUP_DIRECTORY" = /* && -f "$MARIADB_DEFAULTS_FILE" ]] || exit 2
command -v age >/dev/null
mkdir -p "$BACKUP_DIRECTORY"
stage=$(mktemp -d)
trap 'rm -rf -- "$stage"' EXIT
# Invoke during a maintenance window with queue workers stopped for file/database consistency.
mariadb-dump --defaults-extra-file="$MARIADB_DEFAULTS_FILE" --single-transaction --routines --events --triggers --databases "$TRAINING_DATABASE" > "$stage/database.sql"
cp -- "$TRAINING_ROOT/.env" "$stage/application.env"
cp -a -- "$TRAINING_ROOT/storage/app/private" "$stage/private"
printf '%s\n' "$(date -u +%FT%TZ)" > "$stage/created-at.txt"
archive="$BACKUP_DIRECTORY/training-$(date -u +%Y%m%dT%H%M%SZ).tar.age"
tar -C "$stage" -cf - . | age -r "$BACKUP_AGE_RECIPIENT" -o "$archive.partial"
mv -- "$archive.partial" "$archive"
sha256sum "$archive" > "$archive.sha256"
# Export/copy the latest deletion ledger after each approved purge as well as in the database backup.
# IdP configuration and encryption keys require their own tested backup under the named identity owner.
