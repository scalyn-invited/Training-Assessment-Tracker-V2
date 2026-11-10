<?php

namespace App\Http\Controllers;

use App\Services\Installation\InstallationDatabase;
use App\Services\Installation\InstallationState;
use App\Services\Installation\Installer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InstallationController
{
    public function handle(Request $request)
    {
        $store = app(InstallationState::class);
        $state = $store->read();
        $errors = [];
        $step = $request->session()->get('setup.step', 'requirements');
        $authorised = $state['mode'] === 'local' && empty($state['upgrade'])
            || isset($state['session_owner']) && hash_equals($state['session_owner'], (string) $request->session()->get('setup.owner', ''));
        if (! $authorised) {
            $step = 'token';
        }
        if ($request->isMethod('post')) {
            $rate = 'installation:'.hash('sha256', (string) $request->server('REMOTE_ADDR'));
            abort_if(RateLimiter::tooManyAttempts($rate, 120), 429, 'Too many setup requests. Wait one minute.');
            RateLimiter::hit($rate, 60);
            $action = $request->string('action')->toString();
            try {
                if ($action === 'token') {
                    abort_if(RateLimiter::tooManyAttempts($rate.':token', 5), 429, 'Too many token attempts. Wait one minute.');
                    RateLimiter::hit($rate.':token', 60);
                    $store->locked(function () use ($request, $store) {
                        $current = $store->read();
                        if (! isset($current['token_hash']) || ($current['token_expires'] ?? 0) < time()
                            || ! hash_equals($current['token_hash'], hash('sha256', (string) $request->input('setup_token')))) {
                            throw new RuntimeException('The setup token is invalid or expired. Generate a new token from the server console.');
                        }
                        $request->session()->regenerate();
                        $owner = bin2hex(random_bytes(32));
                        $request->session()->put('setup.owner', $owner);
                        $current['session_owner'] = $owner;
                        unset($current['token_hash'], $current['token_expires']);
                        $store->write($current);
                    });
                    $step = 'requirements';
                } else {
                    abort_unless($authorised, 403);
                    if ($action === 'requirements') {
                        if (in_array(false, app(Installer::class)->requirements(), true)) {
                            throw new RuntimeException('Resolve the failed requirements, then check again.');
                        }
                        $step = 'database';
                    } elseif ($action === 'database') {
                        $data = $request->only(['host', 'port', 'database', 'username', 'db_password']);
                        $data['db_password'] ??= '';
                        $request->session()->put('setup.database_fields', array_diff_key($data, ['db_password' => true]));
                        $validator = Validator::make($data, ['host' => 'required|string|max:253|regex:/\A[a-zA-Z0-9_.:\-]+\z/D',
                            'port' => 'required|integer|min:1|max:65535', 'database' => 'required|string|max:64|regex:/\A[a-zA-Z0-9_\-]+\z/D',
                            'username' => 'required|string|max:128', 'db_password' => 'present|string|max:1024|not_regex:/[\r\n\x00]/']);
                        if ($validator->fails()) {
                            $errors = $validator->errors()->all();
                            $step = 'database';
                        } else {
                            $details = app(InstallationDatabase::class)->inspect($data, $state);
                            $request->session()->put('setup.database', $data + $details);
                            $step = 'administrator';
                        }
                    } elseif ($action === 'administrator') {
                        abort_unless($request->session()->has('setup.database'), 409);
                        $data = $request->only(['name', 'email', 'password', 'password_confirmation', 'demo_password', 'demo_password_confirmation']);
                        $data['email'] = strtolower(trim($data['email'] ?? ''));
                        $request->session()->put('setup.admin_fields', array_intersect_key($data, ['name' => true, 'email' => true]));
                        $rules = ['name' => 'required|string|max:120', 'email' => 'required|email|max:200|not_regex:/@example\.invalid$/i',
                            'password' => 'required|string|min:15|max:72|confirmed'];
                        if ($state['mode'] === 'local') {
                            $rules['demo_password'] = 'required|string|min:15|max:72|confirmed';
                        }
                        $validator = Validator::make($data, $rules);
                        $validator->after(function ($validator) use ($data) {
                            foreach (['password', 'demo_password'] as $key) {
                                if (isset($data[$key]) && strlen($data[$key]) > 72) {
                                    $validator->errors()->add($key, 'Passwords must be at most 72 bytes.');
                                }
                            }
                        });
                        if ($validator->fails()) {
                            $errors = $validator->errors()->all();
                            $step = 'administrator';
                        } else {
                            $request->session()->put('setup.administrator', ['name' => $data['name'], 'email' => $data['email'],
                                'fingerprint' => hash_hmac('sha256', json_encode([$data['name'], $data['email'], $data['password'], $data['demo_password'] ?? ''], JSON_THROW_ON_ERROR), $state['key']),
                                'password_hash' => Hash::make($data['password']),
                                'demo_hash' => $state['mode'] === 'local' ? Hash::make($data['demo_password']) : null]);
                            $step = 'review';
                        }
                    } elseif ($action === 'install') {
                        abort_unless($request->session()->has(['setup.database', 'setup.administrator']), 409);
                        if (function_exists('set_time_limit')) {
                            set_time_limit(120);
                        }
                        $url = $state['mode'] === 'local' ? $request->getSchemeAndHttpHost() : rtrim(config('app.url'), '/');
                        if ($state['mode'] === 'hosted' && (! str_starts_with($url, 'https://') || parse_url($url, PHP_URL_HOST) !== $request->getHost())) {
                            throw new RuntimeException('The operator must configure APP_URL to this HTTPS hostname before installation.');
                        }
                        $stage = app(Installer::class)->advance($request->session()->get('setup.database'), $request->session()->get('setup.administrator'), $url);
                        $request->session()->put('setup.stage', $stage);
                        $step = $stage === 'complete' ? 'complete' : 'progress';
                        if ($step === 'complete') {
                            $request->session()->invalidate();
                        }
                    } elseif ($action === 'back' && $state['status'] === 'pending') {
                        $step = match ($step) {
                            'review' => 'administrator', 'administrator' => 'database', default => 'requirements'
                        };
                    } else {
                        abort(409);
                    }
                }
            } catch (RuntimeException $error) {
                $this->recordFailure($error);
                // Database and framework exceptions may contain credentials or SQL; only our own messages are public.
                $errors[] = get_class($error) === RuntimeException::class ? $error->getMessage()
                    : 'Setup could not complete this stage. Check server permissions and database availability, then retry. Existing records were not reset.';
            } catch (\Throwable $error) {
                if ($error instanceof HttpExceptionInterface) {
                    throw $error;
                }
                $this->recordFailure($error);
                $errors[] = 'Setup could not complete this stage. Check server permissions and database availability, then retry. Existing records were not reset.';
            }
        }
        $request->session()->put('setup.step', $step);

        return response()->view('installation.setup', ['step' => $step, 'mode' => $state['mode'], 'problems' => $errors,
            'requirements' => app(Installer::class)->requirements(),
            'database' => $request->session()->get('setup.database_fields', ['host' => '127.0.0.1', 'port' => '3306']),
            'administrator' => $request->session()->get('setup.admin_fields', []),
            'stage' => $request->session()->get('setup.stage'), 'details' => $request->session()->get('setup.database', [])]);
    }

    private function recordFailure(\Throwable $error): void
    {
        logger()->warning('Installation stage failed; no request values or exception text retained.', [
            'exception_class' => get_class($error), 'source_file' => basename($error->getFile()), 'source_line' => $error->getLine(),
        ]);
    }
}
