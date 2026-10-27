<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\Tenancy\ClientContext;
use Illuminate\Http\Request;

class ClientContextController
{
    public function show(Request $request, ClientContext $context)
    {
        return view('clients', ['clients' => $context->choices($request->user())]);
    }

    public function store(Request $request, ClientContext $context)
    {
        $data = $request->validate(['client_id' => 'required|uuid']);
        $request->session()->put('client_id', $context->select($request->user(), $data['client_id']));
        $request->session()->regenerateToken();
        Audit::record($request->user(), 'client.selected', $data['client_id']);

        return redirect()->route('dashboard');
    }
}
