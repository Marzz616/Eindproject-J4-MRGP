<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Inloggen</title>
	<style>
		* { box-sizing: border-box; }
		body {
			margin: 0;
			min-height: 100vh;
			display: grid;
			place-items: center;
			padding: 1.5rem;
			font-family: Arial, sans-serif;
			background: #f3f4f6;
			color: #1f2937;
		}
		.login-card {
			width: 100%;
			max-width: 420px;
			padding: 2rem;
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, .1);
		}
		h1 { margin: 0 0 .5rem; text-align: center; }
		.intro { margin: 0 0 1.5rem; text-align: center; color: #6b7280; }
		label { display: block; margin: 1rem 0 .4rem; font-weight: 600; }
		input[type="email"], input[type="password"] {
			width: 100%;
			padding: .75rem;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			font-size: 1rem;
		}
		input:focus { outline: 2px solid #2563eb; border-color: transparent; }
		.remember { display: flex; gap: .5rem; align-items: center; margin: 1rem 0; }
		button {
			width: 100%;
			padding: .8rem;
			border: 0;
			border-radius: 6px;
			background: #2563eb;
			color: white;
			font-size: 1rem;
			font-weight: 600;
			cursor: pointer;
		}
		button:hover { background: #1d4ed8; }
		.errors { margin-bottom: 1rem; color: #b91c1c; }
	</style>
</head>
<body>
	<main class="login-card">
		<h1>Inloggen</h1>
		<p class="intro">Log in op je account</p>

		@if ($errors->any())
			<div class="errors" role="alert">
				<ul>
					@foreach ($errors->all() as $error)
						<li>{{ $error }}</li>
					@endforeach
				</ul>
			</div>
		@endif

		<form method="POST" action="{{ url('/login') }}">
			@csrf

			<label for="email">E-mailadres</label>
			<input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">

			<label for="password">Wachtwoord</label>
			<input id="password" name="password" type="password" required autocomplete="current-password">

			<label class="remember">
				<input type="checkbox" name="remember" value="1">
				Onthoud mij
			</label>

			<button type="submit">Inloggen</button>
		</form>
	</main>
</body>
</html>
