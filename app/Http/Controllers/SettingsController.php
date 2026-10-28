<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Settings;

class SettingsController extends Controller
{
    public function __construct(private Settings $settings)
    {
    }

    /** Réglages publics : les règles de prêt ne sont pas confidentielles et servent à l'interface. */
    public function show()
    {
        return response()->json(['data' => $this->settings->all()]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->settings->update($request->validated());

        return response()->json(['data' => $this->settings->all()]);
    }
}
