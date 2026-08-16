@extends('layouts.app')

@section('title', 'Model Training')

@section('content')

<div class="bg-white rounded-card shadow p-6 mb-6">

    <div class="flex items-start justify-between mb-1">
        <h2 class="font-heading text-lg font-semibold text-gray-900">Model Training</h2>
        <form method="POST" action="{{ route('train.logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-400 hover:text-gray-600">Log out</button>
        </form>
    </div>
    <p class="text-gray-500 text-sm mb-6">
        Upload a CSV with columns <code class="bg-gray-100 px-1 rounded">age, sbp, dbp, hr, rr, temp, pain_score, ats_category</code>
        (<code class="bg-gray-100 px-1 rounded">ats_category</code> is 1-5, extra columns are ignored). This
        <strong>replaces the live prediction model immediately</strong> — the currently running API starts using
        the new model as soon as training finishes, no restart needed.
    </p>

    @if (session('trainingError'))
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg p-4 mb-6">
        {{ session('trainingError') }}
    </div>
    @endif

    <form method="POST" action="{{ route('train.upload') }}" enctype="multipart/form-data" class="flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Dataset (CSV)</label>
            <input type="file" name="dataset" accept=".csv,text/csv" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-navy file:text-white file:font-semibold">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Algorithm</label>
            <select name="algorithm" class="border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-navy focus:border-navy outline-none">
                <option value="rf">Random Forest</option>
                <option value="xgboost">XGBoost</option>
            </select>
        </div>
        <button type="submit" class="bg-navy hover:bg-navy-light text-white px-6 py-2.5 rounded-lg font-semibold transition whitespace-nowrap">
            Upload &amp; Retrain
        </button>
    </form>

    <p class="text-xs text-gray-400 mt-3">
        On the KTAS demo dataset, Random Forest outperforms XGBoost on every metric that matters here (43.7% vs
        40.5% overall accuracy, 32.6% vs 18.6% ATS-2 recall) — likely because the dataset is small (~1200 rows) and
        boosted trees tend to need more data than bagged ones to pull ahead. Random Forest is the default and the
        currently shipped model; XGBoost is offered for comparison, not because it's known to be better here.
    </p>

</div>

@if ($metrics)
<div class="bg-white rounded-card shadow p-6">

    <h3 class="font-heading text-lg font-semibold text-gray-900 mb-1">
        Training Result
        <span class="text-sm font-normal text-gray-400">— {{ $metrics['algorithm'] === 'xgboost' ? 'XGBoost' : 'Random Forest' }}</span>
    </h3>
    <p class="text-gray-500 text-sm mb-6">
        {{ $metrics['rows_used'] }} row(s) used
        @if ($metrics['dropped_missing'])
        ({{ $metrics['dropped_missing'] }} dropped for missing required values)
        @endif
        — model saved to <code class="bg-gray-100 px-1 rounded">{{ $metrics['model_path'] }}</code>
    </p>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Overall accuracy</p>
            <p class="text-2xl font-bold text-gray-900">{{ round($metrics['accuracy'] * 100) }}%</p>
        </div>
        @foreach ([1, 2] as $cls)
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">ATS {{ $cls }} recall</p>
            @if (isset($metrics['high_acuity_recall'][$cls]))
            <p class="text-2xl font-bold text-gray-900">{{ round($metrics['high_acuity_recall'][$cls]['recall'] * 100) }}%</p>
            <p class="text-xs text-gray-400">n={{ $metrics['high_acuity_recall'][$cls]['support'] }} in test set{{ $metrics['high_acuity_recall'][$cls]['support'] < 10 ? ' — too few to trust' : '' }}</p>
            @else
            <p class="text-2xl font-bold text-gray-400">—</p>
            <p class="text-xs text-gray-400">not present in test split</p>
            @endif
        </div>
        @endforeach
    </div>

    @if ((($metrics['high_acuity_recall'][1]['support'] ?? 0) < 10) || (($metrics['high_acuity_recall'][2]['support'] ?? 0) < 10))
    <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg p-4 mb-6">
        High-acuity (ATS 1-2) sample size is small in this dataset's test split — treat those recall numbers as
        rough signal, not a reliable estimate. This is the same failure mode the KTAS demo dataset has (see
        README) — under-triage is hardest to measure precisely when there are few high-acuity examples to test against.
    </div>
    @endif

    <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2">Per-class metrics (test set)</h4>
    <div class="overflow-x-auto">
        <table class="w-full text-sm mb-2">
            <thead>
                <tr class="text-left text-gray-400 border-b">
                    <th class="py-2 pr-4">ATS</th>
                    <th class="py-2 pr-4">Precision</th>
                    <th class="py-2 pr-4">Recall</th>
                    <th class="py-2 pr-4">F1</th>
                    <th class="py-2 pr-4">Support</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($metrics['per_class'] as $cls => $row)
                <tr class="{{ in_array($cls, ['1', '2']) ? 'bg-amber-50/50 font-semibold' : '' }}">
                    <td class="py-2 pr-4">{{ $cls }}</td>
                    <td class="py-2 pr-4">{{ $row['precision'] }}</td>
                    <td class="py-2 pr-4">{{ $row['recall'] }}</td>
                    <td class="py-2 pr-4">{{ $row['f1'] }}</td>
                    <td class="py-2 pr-4">{{ $row['support'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2 mt-4">Class distribution (full dataset, after cleaning)</h4>
    <div class="flex gap-4 flex-wrap text-sm">
        @foreach ($metrics['class_distribution'] as $cls => $count)
        <div><span class="text-gray-400">ATS {{ $cls }}:</span> <span class="font-semibold">{{ $count }}</span></div>
        @endforeach
    </div>

</div>
@endif

@endsection
