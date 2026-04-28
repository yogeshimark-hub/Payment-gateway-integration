@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="row mb-3">
    <div class="col">
        <h2>Buy a product</h2>
        <p class="text-muted">
            <span class="badge bg-warning text-dark">Combo 2a · Stripe Checkout</span>
            You'll be redirected to a Stripe-hosted checkout page. Stripe handles card entry, Apple Pay, Google Pay, Link, and 3DS.
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

                    <form action="{{ route('payments.checkout.start') }}" method="POST">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="btn btn-warning w-100">
                            Buy with Stripe Checkout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col">
            <div class="alert alert-warning">
                No purchasable products. Run <code>php artisan db:seed</code> to load demo products.
            </div>
        </div>
    @endforelse
</div>

<div class="alert alert-info small mt-4 mb-0">
    Test card on the next page: <code>4242 4242 4242 4242</code> · any future date · any CVC · any zip.
</div>
@endsection
