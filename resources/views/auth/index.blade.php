@extends('layout')

@section('content')
<div class="govuk-grid-row">
	<div class="govuk-grid-column-two-thirds">
		<h1 class="govuk-heading-xl">@lang('app.api_name', ['appname' => config('app.name', 'Laravel')])</h1>

		<p class="govuk-body">@lang('app.click_here_go') <a class="govuk-link govuk-link--no-visited-state"
				href="{{ backend_uri() }}">@lang('app.admin_portal')</a>.</p>

		<p class="govuk-body">@lang('app.click_here_go') <a class="govuk-link govuk-link--no-visited-state"
				href="{{ route('docs.index') }}">@lang('app.api_docs')</a>.</p>

		@guest
		<p class="govuk-body">@lang('app.click_here_to') <a class="govuk-link govuk-link--no-visited-state"
				href="{{ route('login') }}">@lang('auth.login')</a>.</p>
		@else
		<p class="govuk-body">@lang('app.click_here_to') <a
				class="govuk-link govuk-link--no-visited-state gov-link--logout" href="{{ route('logout') }}"
				onclick="event.preventDefault(); document.getElementById('logout-form').submit()">@lang('auth.logout')</a>.
		</p>
		<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display: none;">
			@csrf
		</form>
		@endguest
	</div>
</div>
@endsection