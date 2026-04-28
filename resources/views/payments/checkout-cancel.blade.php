@extends('layouts.app')

@section('title', 'Checkout canceled')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="display-1 text-warning">!</div>
                <h3>Checkout canceled</h3>
                <p class="text-muted">No charge was made. Pick a product again any time.</p>
                <a href="{{ route('payments.checkout.index') }}" class="btn btn-primary">Back to products</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
            </div>
        </div>
    </div>
</div>
@endsection
