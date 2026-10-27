"""Read-only ownership inventory. No Laravel bootstrap, migrations or backfill.

Only synthetic test SQLite sources are accepted. Output omits names, email,
identity subjects, credentials, assessment bodies and file storage keys.
"""
import argparse
import hashlib
import json
import sqlite3
from datetime import datetime, timezone
from pathlib import Path


def digest_file(path):
    with path.open('rb') as source:
        return hashlib.file_digest(source, 'sha256').hexdigest()


def analyse(snapshot):
    users = {row['id']: row for row in snapshot['users']}
    organisations = {row['id']: row for row in snapshot['organisations']}
    groups = {row['id']: row for row in snapshot['groups']}
    findings = []
    candidates = []

    def issue(code, table, record_id):
        findings.append({'code': code, 'table': table, 'record_id': record_id})

    for user in users.values():
        client = user['organisation_id']
        if client not in organisations:
            issue('missing_client_mapping', 'users', user['id'])
            continue
        if user['role'] not in ('admin', 'coordinator', 'member'):
            issue('unknown_legacy_role', 'users', user['id'])
        candidates.append({'user_id': user['id'], 'organisation_id': client,
                           'environment': user['environment'],
                           'membership_status': 'active' if user['active'] else 'ended',
                           'legacy_role': user['role'],
                           'role_disposition': 'client_admin_only_review' if user['role'] == 'admin'
                           else 'explicit_group_grants_review' if user['role'] == 'coordinator'
                           else 'client_member_review',
                           'sensitive_access': 'explicit_capability_review_required',
                           'source_mapping': 'deferred_no_external_identity_linking',
                           'apply': False})
    for group in groups.values():
        if group['organisation_id'] not in organisations:
            issue('orphan_client', 'groups', group['id'])
        if group.get('kind') not in ('team', 'cohort'):
            issue('group_classification_required', 'groups', group['id'])

    for table in ('memberships', 'coordinator_assignments'):
        seen = set()
        for row in snapshot[table]:
            key = (row['group_id'], row['user_id'])
            if key in seen:
                issue('duplicate_legacy_relationship', table, row['id'])
            seen.add(key)
            group, user = groups.get(row['group_id']), users.get(row['user_id'])
            if not group or not user:
                issue('orphan_relationship', table, row['id'])
                continue
            if any(row['organisation_id'] != value['organisation_id'] or row['environment'] != value['environment']
                   for value in (group, user)):
                issue('cross_client_or_environment_relationship', table, row['id'])
            if row['ends_at'] and row['ends_at'] <= row['starts_at']:
                issue('invalid_membership_interval', table, row['id'])
            if table == 'coordinator_assignments' and user['role'] != 'coordinator':
                issue('coordinator_role_mismatch', table, row['id'])

    primary = {}
    for row in snapshot['enrolments']:
        related = [users.get(row['member_id']), users.get(row['coordinator_id']), groups.get(row['group_id'])]
        if any(value is None for value in related):
            issue('orphan_enrolment', 'enrolments', row['id'])
            continue
        if any(row['organisation_id'] != value['organisation_id'] or row['environment'] != value['environment'] for value in related):
            issue('cross_client_or_environment_enrolment', 'enrolments', row['id'])
        if row['member_id'] == row['coordinator_id']:
            issue('self_review_relationship', 'enrolments', row['id'])
        key = (row['organisation_id'], row['environment'], row['member_id'])
        primary.setdefault(key, set()).add(row['coordinator_id'])
    for (client, environment, member), coordinators in primary.items():
        if len(coordinators) > 1:
            issue('conflicting_primary_coordinators_require_history_review', 'users', member)

    return {'write_operations': 0, 'backfill_authorised': False,
            'client_membership_candidates': candidates,
            'group_decisions': [{'group_id': group['id'], 'organisation_id': group['organisation_id'],
                                 'environment': group['environment'], 'origin': group['origin'],
                                 'kind': group.get('kind'), 'decision': 'human_classification_required'
                                 if group.get('kind') not in ('team', 'cohort') else 'review_existing_classification'}
                                for group in groups.values()],
            'primary_candidates': [{'organisation_id': client, 'environment': env, 'member_id': member,
                                    'coordinator_ids': sorted(coordinators), 'apply': False}
                                   for (client, env, member), coordinators in primary.items()],
            'findings': findings,
            'history_warning': 'Legacy unique group/user rows may have been reactivated in place. '
                               'Do not invent earlier membership periods from starts_at/ends_at.',
            'cutover_blockers': ['Approve each group type and any team/cohort separation.',
                                'Review grants, effective dates and sensitive capabilities; never promote legacy Admin globally.',
                                'Review primary allocations and departure/history treatment.',
                                'Validate a separate populated backfill rehearsal before any application database mutation.']}


def inspect(path):
    path = path.resolve(strict=True)
    before = digest_file(path)
    connection = sqlite3.connect(path.as_uri() + '?mode=ro', uri=True)
    connection.row_factory = sqlite3.Row
    try:
        connection.execute('PRAGMA query_only=ON')
        connection.execute('BEGIN')
        if connection.execute("SELECT COUNT(*) FROM users WHERE is_synthetic IS NOT 1 OR environment IS NOT 'test'").fetchone()[0]:
            raise ValueError('Refusing non-synthetic or non-test source; no personal-data report generated.')
        schema = connection.execute("SELECT name,sql FROM sqlite_master WHERE type='table' ORDER BY name").fetchall()
        names = {row['name'] for row in schema}
        columns = {'organisations': 'id,active', 'users': 'id,organisation_id,environment,role,active,is_synthetic,origin',
                   'groups': 'id,organisation_id,environment,origin,active',
                   'memberships': 'id,organisation_id,environment,group_id,user_id,starts_at,ends_at',
                   'coordinator_assignments': 'id,organisation_id,environment,group_id,user_id,starts_at,ends_at',
                   'enrolments': 'id,organisation_id,environment,member_id,coordinator_id,group_id,status'}
        if 'kind' in {row['name'] for row in connection.execute('PRAGMA table_info(groups)')}:
            columns['groups'] += ',kind'
        snapshot = {table: [dict(row) for row in connection.execute(f'SELECT {fields} FROM "{table}" ORDER BY id')]
                    for table, fields in columns.items()}
        table_evidence = {}
        for table in sorted(names):
            quoted = '"' + table.replace('"', '""') + '"'
            count = connection.execute(f'SELECT COUNT(*) FROM {quoted}').fetchone()[0]
            # Sort row hashes rather than raw data: no private record contents enter the output.
            hashes = sorted(hashlib.sha256(json.dumps(dict(row), sort_keys=True, default=str).encode()).hexdigest()
                            for row in connection.execute(f'SELECT * FROM {quoted}'))
            table_evidence[table] = {'count': count, 'rows_sha256': hashlib.sha256('\n'.join(hashes).encode()).hexdigest()}
        result = analyse(snapshot)
        result.update({'source_kind': 'existing_local_synthetic_sqlite', 'source_name': path.name,
                       'observed_at_utc': datetime.now(timezone.utc).isoformat(), 'sqlite_query_only': True,
                       'tables': table_evidence,
                       'schema_sha256': hashlib.sha256(json.dumps([dict(row) for row in schema], sort_keys=True).encode()).hexdigest()})
        connection.rollback()
    finally:
        connection.close()
    after = digest_file(path)
    if before != after:
        raise RuntimeError('Source file changed during inspection; discard this report and investigate concurrent writes.')
    result['source_sha256_before'] = before
    result['source_sha256_after'] = after
    result['source_unchanged'] = True
    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--database', required=True, type=Path)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    if args.output and args.output.resolve() == args.database.resolve():
        parser.error('Output cannot overwrite the source database.')
    report = inspect(args.database)
    encoded = json.dumps(report, indent=2) + '\n'
    if args.output:
        # Refuse replacing existing reports; a rerun needs a new evidence filename.
        with args.output.open('x', encoding='utf-8') as destination:
            destination.write(encoded)
        print(json.dumps({'report': str(args.output), 'source_unchanged': True,
                          'findings': len(report['findings']), 'candidates': len(report['client_membership_candidates'])}))
    else:
        print(encoded)
