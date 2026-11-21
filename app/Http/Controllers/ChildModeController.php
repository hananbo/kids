<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ChildModeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->forget('child_mode');
        $request->session()->regenerate(true);

        return Inertia::location(route('filament.parent.resources.child-profiles.index'));
    }
}
