<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'theme_preference' => $data['theme'],
        ])->save();

        return response()->json([
            'ok' => true,
            'theme' => $data['theme'],
        ]);
    }
}

