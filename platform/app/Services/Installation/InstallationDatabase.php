<?php

namespace App\Services\Installation;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class InstallationDatabase
{
    public function connect(array $input): PDO
    {
        // A strict host grammar prevents PDO DSN option injection.
        if (! preg_match('/\A[a-zA-Z0-9_.:\-]+\z/D', $input['host'])
            || ! preg_match('/\A[a-zA-Z0-9_\-]+\z/D', $input['database'])) {
            throw new RuntimeException('Use a valid host and a database name containing letters, numbers, underscores or hyphens.');
        }
        try {
            return new PDO('mysql:host='.$input['host'].';port='.(int) $input['port'].';dbname='.$input['database'].';charset=utf8mb4',
                $input['username'], $input['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (\Throwable) {
            throw new RuntimeException('Cannot connect. Check the server, existing database, username and password in phpMyAdmin.');
        }
    }

    public function inspect(array $input, array $state): array
    {
        $pdo = $this->connect($input);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $engine = stripos($version, 'MariaDB') !== false ? 'mariadb' : 'mysql';
        preg_match('/(\d+\.\d+\.\d+)/', $version, $match);
        if (! isset($match[1]) || version_compare($match[1], $engine === 'mariadb' ? '10.4.0' : '8.0.0', '<')) {
            throw new RuntimeException('Use MariaDB 10.4 or newer, or MySQL 8.0 or newer. Compatibility must be qualified for your server version.');
        }
        if ($state['mode'] === 'local' && $engine !== 'mariadb') {
            throw new RuntimeException('The shared local development setup requires MariaDB.');
        }
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables) {
            if (! in_array('training_installations', $tables, true)) {
                throw new RuntimeException('This database is not empty. Create a separate empty database in phpMyAdmin.');
            }
            $owner = $pdo->query('SELECT * FROM training_installations')->fetchAll(PDO::FETCH_ASSOC);
            if (count($owner) !== 1 || ! hash_equals($state['id'], $owner[0]['installation_id'])
                || ($owner[0]['stage'] === 'complete' && $state['status'] !== 'installing')) {
                throw new RuntimeException('This database belongs to another or completed installation. It will not be changed.');
            }
        }

        return ['engine' => $engine, 'version' => $version];
    }

    public function activate(array $input, string $engine): void
    {
        config(['database.default' => 'installation', 'database.connections.installation' => [
            'driver' => $engine, 'host' => $input['host'], 'port' => (int) $input['port'],
            'database' => $input['database'], 'username' => $input['username'], 'password' => $input['db_password'],
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ]]);
        DB::purge('installation');
    }

    public function claim(array $input, array $state): void
    {
        $this->inspect($input, $state);
        $pdo = $this->connect($input);
        // The ownership receipt precedes migrations so interrupted DDL is recoverable.
        if (! $pdo->query('SHOW TABLES')->fetchAll()) {
            $pdo->exec('CREATE TABLE training_installations (installation_id VARCHAR(64) PRIMARY KEY, stage VARCHAR(32) NOT NULL, fixture_version VARCHAR(64) NULL, fixture_state LONGTEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL) ENGINE=InnoDB');
            $statement = $pdo->prepare('INSERT INTO training_installations (installation_id, stage, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $statement->execute([$state['id'], 'claimed']);
        }
    }
}
