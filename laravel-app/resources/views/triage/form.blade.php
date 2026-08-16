@extends('layouts.app')

@section('title', 'New Triage Assessment')

@section('content')

<div class="bg-white rounded-card shadow p-6">

    <h2 class="font-heading text-lg font-semibold text-gray-900 mb-1">New Triage Assessment</h2>
    <p class="text-gray-500 text-sm mb-6">Enter the patient's triage-stage vitals. The suggested ATS category is a starting point, not a final answer.</p>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg p-4 mb-6">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if (session('predictionError'))
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg p-4 mb-6">
        {{ session('predictionError') }}
    </div>
    @endif

    <form method="POST" action="{{ route('triage.predict') }}" class="space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Age (years)</label>
                <input type="number" name="age" value="{{ old('age') }}"
                    min="{{ $ranges['age'][0] }}" max="{{ $ranges['age'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Sex</label>
                <select name="sex" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
                    <option value="">-- Select --</option>
                    @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sex') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Systolic BP (mmHg)</label>
                <input type="number" name="sbp" value="{{ old('sbp') }}"
                    min="{{ $ranges['sbp'][0] }}" max="{{ $ranges['sbp'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Diastolic BP (mmHg)</label>
                <input type="number" name="dbp" value="{{ old('dbp') }}"
                    min="{{ $ranges['dbp'][0] }}" max="{{ $ranges['dbp'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Heart Rate (bpm)</label>
                <input type="number" name="hr" value="{{ old('hr') }}"
                    min="{{ $ranges['hr'][0] }}" max="{{ $ranges['hr'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Respiratory Rate (breaths/min)</label>
                <input type="number" name="rr" value="{{ old('rr') }}"
                    min="{{ $ranges['rr'][0] }}" max="{{ $ranges['rr'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Temperature (°C)</label>
                <input type="number" step="0.1" name="temp" value="{{ old('temp') }}"
                    min="{{ $ranges['temp'][0] }}" max="{{ $ranges['temp'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Pain Score (0-10)</label>
                <input type="number" name="pain_score" value="{{ old('pain_score') }}"
                    min="{{ $ranges['pain_score'][0] }}" max="{{ $ranges['pain_score'][1] }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Chief Complaint</label>
            <textarea name="chief_complaint" rows="2" placeholder="e.g. Chest pain radiating to left arm, onset 30 minutes ago"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none" required>{{ old('chief_complaint') }}</textarea>
            <p class="text-xs text-gray-400 mt-1">Recorded for clinical context — not currently used by the prediction model.</p>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-navy hover:bg-navy-light text-white px-6 py-2.5 rounded-lg font-semibold transition">
                Get Suggested Triage Category
            </button>
        </div>

    </form>

</div>

@endsection
