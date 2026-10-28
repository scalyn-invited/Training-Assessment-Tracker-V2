<?php

namespace Tests\Integration;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentReview;
use App\Services\InitialAssessments;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class AssessmentConcurrencyTest extends TestCase
{
    use LearningFixtures;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Support/client-qualification-guard.php';
        assertClientQualificationDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as [$p]) {
            if ($p->isRunning()) {
                $p->stop(1);
            }
        }
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function draft(): AssessmentAssignment
    {
        $s = app(InitialAssessments::class);
        $d = $s->definition($this->person(), ['title' => 'Concurrent synthetic assessment', 'questions' => [['id' => 'q1', 'prompt' => 'Explain your checks.']],
            'rubric' => ['criteria' => [['id' => 'c1', 'category' => 'Checks', 'label' => 'Evidence', 'guidance' => 'Verify the stated checks.', 'weight' => 1]]]]);
        $s->publish($this->person(), $d, 1);
        $a = $s->assign($this->person(), $d->fresh(), $this->enrolment());

        return $s->save($this->person('member-a'), $a, 1, ['responses' => ['q1' => 'Synthetic repeatable checks.']]);
    }

    private function start(array $args): array
    {
        $db = config('database.connections.mariadb');
        $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb', 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_URL' => false,
            'TRAINING_ENVIRONMENT' => 'test', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $input = new InputStream;
        $process = new Process([PHP_BINARY, 'tests/Support/assessment-concurrency-worker.php', ...$args], base_path(), $env, $input, 45);
        $process->start();
        $this->workers[] = [$process, $input];

        return [$process, $input];
    }

    private function race(array $args): array
    {
        $workers = [$this->start($args), $this->start($args)];
        foreach ($workers as [$p]) {
            $deadline = microtime(true) + 30;
            while (! str_contains($p->getOutput(), 'READY') && $p->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $p->getOutput(), $p->getErrorOutput());
        }
        foreach ($workers as [, $input]) {
            $input->write("GO\n");
            $input->close();
        }
        $codes = [];
        foreach ($workers as [$p]) {
            $p->wait();
            $codes[] = $p->getExitCode();
            $this->assertContains($p->getExitCode(), [0, 2], $p->getErrorOutput());
        }
        sort($codes);

        return $codes;
    }

    public function test_concurrent_submission_replays_one_immutable_receipt(): void
    {
        $a = $this->draft();
        $this->assertSame([0, 0], $this->race(['submit', $this->person('member-a')->id, $a->id, (string) $a->version, (string) Str::uuid()]));
        $this->assertSame(1, AssessmentAttempt::count());
    }

    public function test_concurrent_reviews_commit_one_decision_and_conflict_the_other(): void
    {
        $a = $this->draft();
        $t = app(InitialAssessments::class)->submit($this->person('member-a'), $a, $a->version, (string) Str::uuid());
        $this->assertSame([0, 2], $this->race(['review', $this->person()->id, $t->id, (string) $a->fresh()->version]));
        $this->assertSame(1, AssessmentReview::count());
    }
}
