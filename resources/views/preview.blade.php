<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invitation') }}</title>
</head>
<body>
    <div style="max-width: 600px; margin: 50px auto; padding: 20px; font-family: sans-serif;">
        <h1>{{ __('You\'ve Been Invited') }}</h1>

        @if($invitation->inviter)
            <p>{{ __('Invited by: :name', ['name' => $invitation->inviter->name ?? __('Someone')]) }}</p>
        @endif

        @if($invitation->subject)
            <p>{{ __('To: :subject', ['subject' => $invitation->subject->name ?? '']) }}</p>
        @endif

        @if($invitation->isExpired())
            <p style="color: red;">{{ __('This invitation has expired.') }}</p>
        @elseif($invitation->isAccepted())
            <p style="color: green;">{{ __('This invitation has already been accepted.') }}</p>
        @elseif($invitation->isCancelled())
            <p style="color: red;">{{ __('This invitation has been cancelled.') }}</p>
        @elseif($invitation->isDeclined())
            <p style="color: gray;">{{ __('This invitation has been declined.') }}</p>
        @elseif($user)
            <form method="POST" action="{{ route(config('invitation.route_names.accept', 'invitations.accept'), ['token' => $token]) }}" style="display: inline;">
                @csrf
                <button type="submit">{{ __('Accept Invitation') }}</button>
            </form>
            <form method="POST" action="{{ route(config('invitation.route_names.decline', 'invitations.decline'), ['token' => $token]) }}" style="display: inline; margin-left: 10px;">
                @csrf
                <button type="submit" style="background: none; border: 1px solid gray; cursor: pointer;">{{ __('Decline') }}</button>
            </form>
        @else
            <p>
                @if(Route::has('login'))
                    <a href="{{ route('login', ['invitation_token' => $token]) }}">{{ __('Log in to accept this invitation') }}</a>
                @else
                    <a href="#">{{ __('Log in to accept this invitation') }}</a>
                @endif
            </p>
        @endif

        @if($invitation->expires_at && $invitation->isPending())
            <p style="color: gray; font-size: 0.9em;">{{ __('Expires on :date', ['date' => $invitation->expires_at->toFormattedDateString()]) }}</p>
        @endif

        @if($errors->any())
            <div style="color: red; margin-top: 20px;">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if(session('status'))
            <div style="color: green; margin-top: 20px;">
                <p>{{ session('status') }}</p>
            </div>
        @endif
    </div>
</body>
</html>
