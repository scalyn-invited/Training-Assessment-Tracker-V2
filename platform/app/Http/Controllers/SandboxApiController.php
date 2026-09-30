<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\MockIdentity;
use Illuminate\Http\Request;

class SandboxApiController
{
    public function summary(Request $request, MockIdentity $mock, Access $access, ?string $memberId = null)
    {
        $user = $mock->verify($request->bearerToken() ?? '', $memberId ? 'training.member.read' : 'training.self.read');
        if ($memberId && ! $access->enrolments($user)->where('member_id', $memberId)->exists()) {
            abort(404);
        }
        // Synthetic and local-only records are excluded even in this mock delegated transport.
        $items = $access->externalEnrolments($user)->where('member_id', $memberId ?? $user->id)->get();

        return response()->json(['items' => $items->map(fn ($e) => ['enrolment_id' => $e->id]), 'next_cursor' => null, 'data_as_of' => now()->toISOString()]);
    }
}
