@extends('layouts.app')

@section('title', 'Pay with Stripe Elements')

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>Pick a product</h2>
        <p class="text-muted">
            <span class="badge bg-info text-dark">Combo 2b · Stripe Elements</span>
            Card details are entered on <em>this</em> domain in a Stripe-rendered Element styled to match your brand.
            Same backend as Combo 1b — only the frontend differs.
        </p>
    </div>
</div>

<div class="row g-3">
    @forelse ($products as $product)
        @php $price = $product->activePrices->first(); @endphp
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <span class="badge bg-secondary mb-2">{{ $product->type->value }}</span>
                    <h5>{{ $product->name }}</h5>
                    <p class="text-muted small">{{ $product->description }}</p>
                    <p class="display-6 mb-3">{{ $price->formatted_amount }}</p>
                    <a class="btn btn-info text-white w-100" href="{{ route('payments.elements.show', $product->slug) }}">
                        Pay with Elements
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="col">
            <div class="alert alert-warning">No products. Run <code>php artisan db:seed</code>.</div>
        </div>
    @endforelse
</div>
@endsection
