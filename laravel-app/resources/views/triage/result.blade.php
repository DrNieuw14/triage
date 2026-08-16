@extends('layouts.app')

@section('title', 'Suggested Triage Category')

@php
    // Standard ATS colour/urgency convention — left as-is regardless of
    // app theme, since these colours carry real clinical meaning.
    $atsMeta = [
        1 => ['label' => 'Immediate', 'wait' => 'Immediate', 'color' => 'bg-red-600'],
        2 => ['label' => 'Emergency', 'wait' => 'within 10 minutes', 'color' => 'bg-orange-500'],
        3 => ['label' => 'Urgent', 'wait' => 'within 30 minutes', 'color' => 'bg-yellow-400 text-gray-900'],
        4 => ['label' => 'Semi-urgent', 'wait' => 'within 60 minutes', 'color' => 'bg-green-600'],
        5 => ['label' => 'Non-urgent', 'wait' => 'within 120 minutes', 'color' => 'bg-blue-600'],
    ][$atsCategory];
@endphp

@section('content')

<div class="bg-white rounded-card shadow p-6">

    <p class="text-gray-500 text-sm font-semibold uppercase tracking-wide mb-2">Suggested ATS Category</p>

    <div class="flex items-center gap-4 mb-2">
        <div class="w-16 h-16 rounded-full {{ $atsMeta['color'] }} text-white flex items-center justify-center text-3xl font-bold shadow">
            {{ $atsCategory }}
        </div>
        <div>
            <p class="font-heading text-xl font-semibold text-gray-900">{{ $atsMeta['label'] }}</p>
            <p class="text-gray-500 text-sm">Recommended assessment {{ $atsMeta['wait'] }}</p>
        </div>
    </div>

    <div class="mb-6">
        <p class="text-sm text-gray-500 mb-1">Model confidence</p>
        <div class="w-full bg-gray-100 rounded-full h-3">
            <div class="bg-navy h-3 rounded-full" style="width: {{ round($confidence * 100) }}%"></div>
        </div>
        <p class="text-sm text-gray-600 mt-1">{{ round($confidence * 100) }}%</p>
    </div>

    <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg p-4 mb-6">
        This is a <strong>suggestion only</strong>, generated from the vitals below. It is not an auto-assigned
        category and does not replace your own clinical judgement — assign the ATS category you believe is correct.
    </div>

    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Submitted Data</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-3 text-sm mb-2">
        <div><p class="text-gray-400">Age</p><p class="font-semibold text-gray-900">{{ $input['age'] }}</p></div>
        <div><p class="text-gray-400">Sex</p><p class="font-semibold text-gray-900">{{ ucfirst($input['sex']) }}</p></div>
        <div><p class="text-gray-400">Systolic BP</p><p class="font-semibold text-gray-900">{{ $input['sbp'] }} mmHg</p></div>
        <div><p class="text-gray-400">Diastolic BP</p><p class="font-semibold text-gray-900">{{ $input['dbp'] }} mmHg</p></div>
        <div><p class="text-gray-400">Heart Rate</p><p class="font-semibold text-gray-900">{{ $input['hr'] }} bpm</p></div>
        <div><p class="text-gray-400">Respiratory Rate</p><p class="font-semibold text-gray-900">{{ $input['rr'] }} /min</p></div>
        <div><p class="text-gray-400">Temperature</p><p class="font-semibold text-gray-900">{{ $input['temp'] }} °C</p></div>
        <div><p class="text-gray-400">Pain Score</p><p class="font-semibold text-gray-900">{{ $input['pain_score'] }} / 10</p></div>
    </div>
    <div class="text-sm mb-6">
        <p class="text-gray-400">Chief Complaint</p>
        <p class="font-semibold text-gray-900">{{ $input['chief_complaint'] }}</p>
    </div>

    <a href="{{ route('triage.form') }}" class="inline-block bg-navy hover:bg-navy-light text-white px-6 py-2.5 rounded-lg font-semibold transition">
        New Assessment
    </a>

</div>

@endsection
