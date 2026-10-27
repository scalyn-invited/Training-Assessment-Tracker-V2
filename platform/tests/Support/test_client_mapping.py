import copy
import importlib.util
import pathlib
import sqlite3
import tempfile
import unittest

path = pathlib.Path(__file__).resolve().parents[2] / 'scripts/client-mapping-dry-run.py'
spec = importlib.util.spec_from_file_location('mapping', path)
mapping = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mapping)


class ClientMappingTest(unittest.TestCase):
    def snapshot(self):
        return {'organisations': [{'id': 'a', 'active': 1}],
                'users': [{'id': 'm', 'organisation_id': 'a', 'environment': 'test', 'role': 'member', 'active': 1},
                          {'id': 'c', 'organisation_id': 'a', 'environment': 'test', 'role': 'coordinator', 'active': 1}],
                'groups': [{'id': 'g', 'organisation_id': 'a', 'environment': 'test', 'origin': 'manual'}],
                'memberships': [{'id': 'membership', 'organisation_id': 'a', 'environment': 'test',
                                 'group_id': 'g', 'user_id': 'm', 'starts_at': '2026-01-01', 'ends_at': None}],
                'coordinator_assignments': [],
                'enrolments': [{'id': 'enrolment', 'organisation_id': 'a', 'environment': 'test',
                                'member_id': 'm', 'coordinator_id': 'c', 'group_id': 'g'}]}

    def test_does_not_guess_group_kind_or_apply_candidates(self):
        source = self.snapshot()
        before = copy.deepcopy(source)
        result = mapping.analyse(source)
        self.assertEqual(before, source)
        self.assertEqual('group_classification_required', result['findings'][0]['code'])
        self.assertFalse(result['backfill_authorised'])
        self.assertTrue(all(not row['apply'] for row in result['client_membership_candidates']))

    def test_cross_client_orphan_and_invalid_interval_are_reported(self):
        source = self.snapshot()
        source['memberships'][0]['organisation_id'] = 'b'
        source['memberships'][0]['ends_at'] = '2025-01-01'
        source['enrolments'][0]['coordinator_id'] = 'missing'
        codes = {row['code'] for row in mapping.analyse(source)['findings']}
        self.assertTrue({'cross_client_or_environment_relationship', 'invalid_membership_interval', 'orphan_enrolment'} <= codes)

    def test_conflicting_primaries_and_legacy_admin_never_imply_global_grant(self):
        source = self.snapshot()
        source['users'].append({'id': 'd', 'organisation_id': 'a', 'environment': 'test', 'role': 'admin', 'active': 1})
        source['enrolments'].append(dict(source['enrolments'][0], id='second', coordinator_id='d'))
        result = mapping.analyse(source)
        self.assertIn('conflicting_primary_coordinators_require_history_review', {row['code'] for row in result['findings']})
        self.assertEqual('client_admin_only_review', result['client_membership_candidates'][-1]['role_disposition'])

    def test_non_synthetic_source_is_refused_without_mutation(self):
        runtime = path.parent.parent / '.runtime'
        runtime.mkdir(exist_ok=True)
        with tempfile.TemporaryDirectory(dir=runtime) as directory:
            database = pathlib.Path(directory) / 'source.sqlite'
            connection = sqlite3.connect(database)
            connection.execute('CREATE TABLE users (is_synthetic INTEGER, environment TEXT)')
            connection.execute("INSERT INTO users VALUES (0, 'test')")
            connection.commit()
            connection.close()
            before = mapping.digest_file(database)
            with self.assertRaisesRegex(ValueError, 'Refusing non-synthetic'):
                mapping.inspect(database)
            self.assertEqual(before, mapping.digest_file(database))


if __name__ == '__main__':
    unittest.main()
