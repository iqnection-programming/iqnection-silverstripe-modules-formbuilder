$Body

<% if $IncludeSubmission && $Submission.Exists %>
	<h3>Submission</h3>
	<% loop $Submission.SubmissionFieldValues %>
		<div><strong>$Title:</strong> 
			<% if $FileID %>
				<% if $File.Exists %>
					<% if $Top.MakeFileLinks %>
						<a href="$File.AbsoluteURL" target="_blank">$File.Name</a>
					<% else %>
						$File.Name
					<% end_if %>
				<% end_if %>
			<% else %>
				$Value
			<% end_if %>
		</div>
	<% end_loop %>
<% end_if %>

