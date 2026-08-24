<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Requests\Settings\AssistantUpdateRequest;

class AssistantController extends Controller
{
    public function edit(Request $request): Response
{
    return Inertia::render('settings/Assistant');
}

public function update(AssistantUpdateRequest $request): RedirectResponse
{
    $request->user()->fill($request->validated())->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Instructions mises à jour.')]);

    return to_route('assistant.edit');
}

}
