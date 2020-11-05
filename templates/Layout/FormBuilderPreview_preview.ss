

<h1>Form Builder Preview</h1>

<% if $FormBuilder.Exists %>

	<h2>Form: $FormBuilder.Title</h2>

	<div class="form-builder-form">
		$FormBuilder
	</div>

<% else %>

	<strong>FORM COULD NOT BE FOUND.</strong>

<% end_if %>