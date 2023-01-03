<div class="warning">
	<div class="govuk-width-container">
		<div class="govuk-warning-text">
			<span class="govuk-warning-text__icon">!</span>
			<strong class="govuk-warning-text__text">
				<span class="govuk-warning-text__assistive">Important</span>
				Please DO NOT make any changes to this site. This is a TEST environment used for demo purposes only. Any
				changes made here will not be reflected on the LIVE site viewed by the public. <a
					href="{{ preg_replace('/\b(?:((?:https?):\/\/)|www\.)(api\.staging\.)?([-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|])/', '${1}admin.${3}', config('app.url')) }}">Click
					HERE</a> to access the LIVE environment.
			</strong>
		</div>
	</div>
</div>