@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-4">Sign In</h4>

                <form id="login-form" action="{{ route('login.submit') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" autofocus>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control">
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" id="remember" value="1" class="form-check-input">
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                <p class="text-center mt-3 mb-0">
                    No account? <a href="{{ route('register') }}">Register</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/auth-validation.js') }}"></script>
<script>
    $(function () {
        AuthValidation.bind('#login-form', ['email', 'password']);
    });
</script>
@endpush
