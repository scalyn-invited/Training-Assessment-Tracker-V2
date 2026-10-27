"""Capture a read-only synthetic source and rehearse mapping/recovery on disposable copies."""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def connect_readonly(path):
    connection = sqlite3.connect(path.resolve().as_uri() + '?mode=ro', uri=True)
    connection.execute('PRAGMA query_only=ON')
    return connection


def backup(source, target):
    if target.exists():
        raise RuntimeError('Backup destination already exists')
    src = connect_readonly(source)
    dst = sqlite3.connect(target)
    try:
        src.backup(dst)
    finally:
        dst.close()
        src.close()


def snapshot(path, legacy=False, audit_id=None):
    excluded = {'roles', 'client_memberships', 'client_team_memberships', 'role_assignments',
                'coordinator_allocations', 'client_rehearsal_control'} if legacy else set()
    connection = connect_readonly(path)
    connection.row_factory = sqlite3.Row
    try:
        result = {}
        for row in connection.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"):
            table = row['name']
            if table in excluded or table.startswith('sqlite_'):
                continue
            quoted = '"' + table.replace('"', '""') + '"'
            hashes = []
            for record in connection.execute(f'SELECT * FROM {quoted}'):
                value = dict(record)
                if legacy and table == 'groups':
                    value.pop('kind', None)
                if legacy and table == 'audit_events' and value['id'] == audit_id:
                    continue
                hashes.append(hashlib.sha256(json.dumps(value, sort_keys=True, default=str).encode()).hexdigest())
            result[table] = {'count': len(hashes), 'sha256': hashlib.sha256('\n'.join(sorted(hashes)).encode()).hexdigest()}
        return result
    finally:
        connection.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--evidence', type=Path, required=True, help='New evidence directory; never overwritten')
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    platform = Path(__file__).resolve().parents[1]
    original = args.source.resolve(strict=True)
    original_hash = sha(original)
    source_connection = connect_readonly(original)
    try:
        if source_connection.execute("SELECT COUNT(*) FROM users WHERE is_synthetic IS NOT 1 OR environment IS NOT 'test'").fetchone()[0]:
            raise RuntimeError('Non-synthetic source refused')
    finally:
        source_connection.close()
    args.evidence.mkdir(parents=True, exist_ok=False)
    evidence = args.evidence.resolve()
    runtime = platform / '.runtime/client-backfill'
    runtime.mkdir(parents=True, exist_ok=True)
    run = Path(tempfile.mkdtemp(prefix='rehearsal-', dir=runtime))
    source, target = run / 'source.sqlite', run / 'target.sqlite'
    backup(original, source)
    if sha(original) != original_hash:
        raise RuntimeError('Original changed while capturing; discard rehearsal')
    shutil.copyfile(source, target)
    baseline = snapshot(source, legacy=True)
    env = os.environ.copy()
    env.update({'APP_ENV': 'testing', 'TRAINING_ENVIRONMENT': 'test', 'DB_CONNECTION': 'sqlite',
                'DB_DATABASE': str(target), 'DB_URL': '', 'APP_CONFIG_CACHE': str(run / 'unused-config.php'),
                'APP_KEY': 'base64:' + base64.b64encode(os.urandom(32)).decode(), 'CACHE_STORE': 'array',
                'SESSION_DRIVER': 'array', 'QUEUE_CONNECTION': 'sync', 'MAIL_MAILER': 'array', 'MOCK_IDENTITY_ENABLED': 'true'})
    manifest_path = evidence / 'mapping-manifest.json'

    def command(script, arguments, database=target, success=True):
        execution_env = dict(env, DB_DATABASE=str(database))
        result = subprocess.run([args.php, str(platform / 'scripts' / script), *arguments], cwd=platform,
                                env=execution_env, capture_output=True, text=True, timeout=60)
        if (result.returncode == 0) != success:
            raise RuntimeError(f'{script} unexpected exit {result.returncode}: {result.stderr} {result.stdout}')
        return json.loads(result.stdout) if success else {'exit_code': result.returncode, 'reason': result.stderr.strip()}

    prepared = command('prepare-client-backfill.php', ['--source', str(source), '--target', str(target), '--output', str(manifest_path)])
    manifest = json.loads(manifest_path.read_text())
    manifest_hash = prepared['manifest_sha256']

    def execute(mode, database=target, success=True):
        return command('client-backfill-rehearsal.php', ['--manifest', str(manifest_path), '--manifest-sha256', manifest_hash,
                       '--source', str(source), '--target', str(database), '--mode', mode], database, success)

    before_validation = sha(target)
    validated = execute('validate')
    if sha(target) != before_validation:
        raise RuntimeError('Validation modified the target')
    preapply = run / 'pre-application.sqlite'
    backup(target, preapply)
    applied = execute('apply')
    after = snapshot(target, legacy=True, audit_id=manifest['audit_id'])
    if after != baseline:
        raise RuntimeError('Legacy rows changed')
    full_after = snapshot(target)
    repeated = execute('apply')
    if snapshot(target) != full_after:
        raise RuntimeError('Idempotent retry changed rows')
    wrong_target = execute('apply', original, success=False)
    recovered = run / 'recovered.sqlite'
    shutil.copyfile(preapply, recovered)
    restored_exactly = sha(preapply) == sha(recovered)
    recovery_validated = execute('validate', recovered)
    recovery_applied = execute('apply', recovered)
    if not restored_exactly or snapshot(recovered, legacy=True, audit_id=manifest['audit_id']) != baseline:
        raise RuntimeError('Recovery failed')
    if sha(original) != original_hash or sha(source) != manifest['source_sha256']:
        raise RuntimeError('Source changed; discard rehearsal results')
    result = {'original_sha256_before': original_hash, 'original_sha256_after': sha(original),
              'snapshot_sha256': sha(source), 'manifest_sha256': manifest_hash,
              'runtime_directory': str(run), 'effective_at_utc': manifest['effective_at_utc'],
              'validated': validated, 'validation_target_bytes_unchanged': True,
              'applied': applied, 'repeated': repeated, 'legacy_before': baseline, 'legacy_after': after,
              'foundation_counts': {key: full_after[key]['count'] for key in ['client_memberships', 'client_team_memberships',
                                    'role_assignments', 'coordinator_allocations']},
              'wrong_original_target_refused': wrong_target, 'restored_pre_application_bytes_equal': restored_exactly,
              'recovery_validated': recovery_validated, 'recovery_applied': recovery_applied,
              'recovery_legacy_preserved': True, 'original_unchanged': True}
    (evidence / 'sqlite-rehearsal-results.json').write_text(json.dumps(result, indent=2) + '\n')
    (evidence / 'preparation.json').write_text(json.dumps(prepared, indent=2) + '\n')
    print(json.dumps({'evidence': str(evidence), 'legacy_preserved': True, 'recovery_verified': True,
                      'original_unchanged': True, 'foundation_counts': result['foundation_counts']}))


if __name__ == '__main__':
    main()
