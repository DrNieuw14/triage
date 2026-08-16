<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriageController extends Controller
{
    // Kept in one place so the Blade form's min/max attributes and this
    // validation never drift apart. The Python API enforces the same
    // seven-field ranges independently — it shouldn't trust this app blindly
    // either, since it's a general endpoint on localhost during development.
    private const RANGES = [
        'age' => [0, 120],
        'sbp' => [40, 300],
        'dbp' => [20, 200],
        'hr' => [20, 250],
        'rr' => [4, 60],
        'temp' => [30, 43],
        'pain_score' => [0, 10],
    ];

    public function create()
    {
        return view('triage.form', ['ranges' => self::RANGES]);
    }

    public function predict(Request $request)
    {
        $validated = $request->validate([
            'age' => ['required', 'integer', 'between:' . self::RANGES['age'][0] . ',' . self::RANGES['age'][1]],
            'sex' => ['required', 'in:male,female,other'],
            'sbp' => ['required', 'integer', 'between:' . self::RANGES['sbp'][0] . ',' . self::RANGES['sbp'][1]],
            'dbp' => ['required', 'integer', 'between:' . self::RANGES['dbp'][0] . ',' . self::RANGES['dbp'][1]],
            'hr' => ['required', 'integer', 'between:' . self::RANGES['hr'][0] . ',' . self::RANGES['hr'][1]],
            'rr' => ['required', 'integer', 'between:' . self::RANGES['rr'][0] . ',' . self::RANGES['rr'][1]],
            'temp' => ['required', 'numeric', 'between:' . self::RANGES['temp'][0] . ',' . self::RANGES['temp'][1]],
            'pain_score' => ['required', 'integer', 'between:' . self::RANGES['pain_score'][0] . ',' . self::RANGES['pain_score'][1]],
            'chief_complaint' => ['required', 'string', 'max:255'],
        ]);

        $modelInput = collect($validated)->only(['age', 'sbp', 'dbp', 'hr', 'rr', 'temp', 'pain_score'])->all();

        try {
            $response = Http::timeout(5)
                ->post(rtrim(config('services.triage_api.url'), '/') . '/predict', $modelInput);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Triage API unreachable: ' . $e->getMessage());

            return back()->withInput()->with(
                'predictionError',
                'The prediction service is not reachable right now. Please try again, or assign the ATS category using your own clinical judgement.'
            );
        }

        if (! $response->successful()) {
            Log::error('Triage API returned an error: ' . $response->body());

            return back()->withInput()->with(
                'predictionError',
                'The prediction service could not process this input. Please assign the ATS category using your own clinical judgement.'
            );
        }

        $result = $response->json();

        $prediction = Prediction::create([
            'age' => $validated['age'],
            'sex' => $validated['sex'],
            'sbp' => $validated['sbp'],
            'dbp' => $validated['dbp'],
            'hr' => $validated['hr'],
            'rr' => $validated['rr'],
            'temp' => $validated['temp'],
            'pain_score' => $validated['pain_score'],
            'chief_complaint' => $validated['chief_complaint'],
            'ats_category' => $result['ats_category'],
            'confidence' => $result['confidence'],
        ]);

        return view('triage.result', [
            'input' => $validated,
            'atsCategory' => $result['ats_category'],
            'confidence' => $result['confidence'],
            'prediction' => $prediction,
        ]);
    }
}
