<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SecureProject</title>
    <link rel="stylesheet" href="/css/app.css">
    <script src="/js/app.js" defer></script>
</head>
<body>
<nav>
    <strong>SecureProject</strong>
    @auth
        <a href="{{ route('projects.index') }}">Projects</a>
        @if(auth()->user()->isOrgAdmin())
            <a href="{{ route('team.index') }}">Team</a>
        @endif
        <span class="spacer"></span>
        <span class="muted nav-user">{{ auth()->user()->email }} ({{ auth()->user()->organization->name }}, {{ auth()->user()->role }})</span>
        <form method="POST" action="{{ route('logout') }}" class="flush">
            @csrf
            <button type="submit">Log out</button>
        </form>
    @endauth
</nav>
<main>
    @if(session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
