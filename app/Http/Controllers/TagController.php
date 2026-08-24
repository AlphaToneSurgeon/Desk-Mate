<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        $request->user()->tags()->create([
            'name' => $request->input('name'),
            'color' => $request->input('color', '#5593f7'),
        ]);

        return back();
    }
}
