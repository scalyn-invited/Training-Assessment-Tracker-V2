<?php

namespace App\Http\Controllers;

use App\Models\LearningNotification;
use App\Services\Access;
use Illuminate\Http\Request;

class NotificationsController
{
    public function show(Request $request, Access $access)
    {
        $notices = LearningNotification::where('recipient_id', $request->user()->id)->whereIn('enrolment_id', $access->enrolments($request->user())->select('enrolments.id'))->latest()->paginate(20);

        return view('notifications', compact('notices'));
    }

    public function preference(Request $request)
    {
        $data = $request->validate(['training_email' => 'required|boolean']);
        $request->user()->update($data);

        return back()->with('status', 'Email preference saved. In-app updates remain available.');
    }
}
