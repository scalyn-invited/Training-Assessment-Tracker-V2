<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use App\Services\ApprovedFeed;
use App\Services\MockIdentity;
use App\Services\Tenancy\ClientContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SandboxApiController
{
    private function client(Request $request, User $user): void
    {
        $id = $request->header('X-Client-Id');
        abort_unless(is_string($id) && Str::isUuid($id), 422, 'X-Client-Id is required.');
        app(ClientContext::class)->select($user, $id);
    }

    public function changes(Request $request, MockIdentity $mock, ApprovedFeed $feed)
    {
        $user = $mock->verify($request->bearerToken() ?? '', 'training.changes.read');
        $this->client($request, $user);
        $input = $request->validate(['after' => 'nullable|integer|min:0', 'limit' => 'nullable|integer|between:1,100']);

        return response()->json($feed->read($user, (int) ($input['after'] ?? 0), (int) ($input['limit'] ?? 50)))->header('Cache-Control', 'no-store');
    }

    public function summary(Request $request, MockIdentity $mock, Access $access, ?string $memberId = null)
    {
        $user = $mock->verify($request->bearerToken() ?? '', $memberId ? 'training.member.read' : 'training.self.read');
        $this->client($request, $user);
        if ($memberId && ! $access->enrolments($user)->where('member_id', $memberId)->exists()) {
            abort(404);
        }
        // Synthetic and local-only records are excluded even in this mock delegated transport.
        $items = $access->externalEnrolments($user)->where('member_id', $memberId ?? $user->id)->get();

        return response()->json(['items' => $items->map(fn ($e) => ['enrolment_id' => $e->id]), 'next_cursor' => null, 'data_as_of' => now()->toISOString()]);
    }
}
