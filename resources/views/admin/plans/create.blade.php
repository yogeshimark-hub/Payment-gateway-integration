@extends('layouts.app')

@section('title', 'Admin · New Plan')

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>New plan</h2>
        <p class="text-muted">
            <a href="{{ route('admin.plans.index') }}" class="small">← back to plans</a>
        </p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('admin.plans.store') }}" method="POST" novalidate>
            @csrf
            @include('admin.plans._form')

            <hr>
            <button type="submit" class="btn btn-primary">Create plan</button>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
