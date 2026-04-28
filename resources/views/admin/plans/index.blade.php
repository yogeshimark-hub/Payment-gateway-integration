@extends('layouts.app')

@section('title', 'Admin · Plans')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>Plans</h2>
        <p class="text-muted mb-0">Manage subscription plans and one-time offerings.</p>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">+ New Plan</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Interval</th>
                    <th>Stripe Price ID</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td>
                            <strong>{{ $plan->name }}</strong><br>
                            <code class="small text-muted">{{ $plan->slug }}</code>
                        </td>
                        <td>
                            <span class="badge {{ $plan->billing_type->badgeClass() }}">
                                {{ $plan->billing_type->label() }}
                            </span>
                        </td>
                        <td>{{ $plan->formatted_amount }}</td>
                        <td>
                            @if ($plan->isRecurring())
                                {{ $plan->interval_count > 1 ? "every {$plan->interval_count}" : '' }}
                                {{ $plan->interval->value }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($plan->needsStripeSync())
                                <form action="{{ route('admin.plans.sync', $plan) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-warning">Sync now</button>
                                </form>
                            @else
                                <code class="small">{{ $plan->stripe_price_id }}</code>
                            @endif
                        </td>
                        <td>
                            @if ($plan->is_active)
                                <span class="badge bg-success">active</span>
                            @else
                                <span class="badge bg-secondary">inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">Edit</a>

                            <form action="{{ route('admin.plans.toggle', $plan) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary">
                                    {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete plan {{ $plan->name }}?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No plans yet. Click "+ New Plan".</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
