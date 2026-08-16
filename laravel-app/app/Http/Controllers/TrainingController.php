<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TrainingController extends Controller
{
    public function showLogin()
    {
        if (session('training_authenticated')) {
            return redirect()->route('train.index');
        }

        return view('train.login');
    }

    public function login(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        if ($request->input('password') !== config('services.training_page.password')) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put('training_authenticated', true);
        $request->session()->regenerate();

        return redirect()->route('train.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('training_authenticated');

        return redirect()->route('train.login');
    }

    public function index()
    {
        return view('train.index', ['metrics' => session('trainingMetrics')]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'dataset' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'algorithm' => ['required', 'in:rf,xgboost'],
        ]);

        $file = $request->file('dataset');

        try {
            $response = Http::timeout(120)
                ->withHeaders(['X-Train-Secret' => config('services.triage_api.train_secret')])
                ->attach('dataset', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post(rtrim(config('services.triage_api.url'), '/') . '/train', [
                    'algorithm' => $request->input('algorithm'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return back()->with('trainingError', 'The training service is not reachable right now.');
        }

        if (! $response->successful()) {
            $message = $response->json('error') ?? 'Training failed for an unknown reason.';

            return back()->with('trainingError', $message);
        }

        return redirect()->route('train.index')->with('trainingMetrics', $response->json());
    }
}
