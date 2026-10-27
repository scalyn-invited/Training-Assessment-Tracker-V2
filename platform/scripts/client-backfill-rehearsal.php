<?php

// No .env database fallback: require an explicit target before Laravel bootstraps.
use Illuminate\Contracts\Console\Kernel;

$options = getopt('', ['manifest:', 'manifest-sha256:', 'source:', 'target:', 'mode:']);
foreach (['manifest', 'manifest-sha256', 'source', 'target', 'mode'] as $key) {
    if (! isset($options[$key]) || ! is_string($options[$key])) {
        fwrite(STDERR, "Required: --manifest --manifest-sha256 --source --target --mode=validate|apply\n");
        exit(2);
    }
}
if (! in_array($options['mode'], ['validate', 'apply'], true) || getenv('APP_ENV') !== 'testing'
    || ! in_array(getenv('DB_CONNECTION'), ['sqlite', 'mariadb'], true) || getenv('DB_DATABASE') !== $options['target']
    || getenv('TRAINING_ENVIRONMENT') !== 'test' || getenv('DB_URL')) {
    fwrite(STDERR, "Refusing implicit target, URL override, mode or non-test environment.\n");
    exit(2);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/support/ClientBackfill.php';
try {
    $manifest = json_decode(file_get_contents($options['manifest']), true, flags: JSON_THROW_ON_ERROR);
    $source = realpath($options['source']);
    if (! $source || $source === realpath($options['target'])) {
        throw new RuntimeException('Source must exist and differ from target.');
    }
    $before = hash_file('sha256', $source);
    $result = ClientBackfill::run($manifest, $options['manifest-sha256'], $before, $options['target'], $options['mode'] === 'apply');
    if (! hash_equals($before, hash_file('sha256', $source))) {
        throw new RuntimeException('Source changed concurrently; discard the disposable rehearsal and recapture.');
    }
    echo json_encode($result + ['source_unchanged' => true], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(2);
}
