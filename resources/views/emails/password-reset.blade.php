@extends('layouts.app')

@section('title', 'Password Reset')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Reset Password') }}</div>

                <div class="card-body">
                    <p>{{ __('Hello') }} {{ $user->name }},</p>
                    
                    <p>{{ __('You are receiving this email because we received a password reset request for your account.') }}</p>
                    
                    <p>{{ __('Your reset token is:') }} <strong>{{ $token }}</strong></p>
                    
                    <p>{{ __('This password reset token will expire in :count minutes.', ['count' => $expiry]) }}</p>
                    
                    <p>{{ __('If you did not request a password reset, no further action is required.') }}</p>
                    
                    <p>{{ __('Regards,') }}<br>
                    {{ config('app.name') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

