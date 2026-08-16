@extends('layouts.app')

@section('title', 'Model Training — Login')

@section('content')

<div class="bg-white rounded-card shadow p-6 max-w-sm mx-auto">

    <h2 class="font-heading text-lg font-semibold text-gray-900 mb-1">Model Training</h2>
    <p class="text-gray-500 text-sm mb-6">This page can replace the prediction model — enter the training password to continue.</p>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg p-3 mb-4">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('train.login.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
            <input type="password" name="password" autofocus
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-navy focus:border-navy outline-none">
        </div>
        <button type="submit" class="w-full bg-navy hover:bg-navy-light text-white px-6 py-2.5 rounded-lg font-semibold transition">
            Continue
        </button>
    </form>

    <a href="{{ route('triage.form') }}" class="block text-center text-sm text-gray-400 hover:text-gray-600 mt-4">← Back to triage form</a>

</div>

@endsection
