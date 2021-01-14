$Action.Body

<% if $Action.IncludeSubmission && $Submission.Exists %>
	<h3>Submission</h3>
	<table border="1" cellspacing="0" cellpadding="2" style="width:100%; background-color:#FFFFFF;" class="submissions-table">
	<% loop $Submission.SubmissionFieldValues %>
		<tr>
			<th align="left" width="30%" valign="top">$Title:</th>
			<td width="70%" valign="top">
				<% if $FileID %>
					<% if $File.Exists %>
						<% if $Action.Top.MakeFileLinks %>
							<a href="$File.AbsoluteURL" target="_blank">$File.Name</a>
						<% else %>
							$File.Name
						<% end_if %>
					<% end_if %>
				<% else %>
					$Value
				<% end_if %>
			</td>
		</tr>
	<% end_loop %>
	<table>
<% end_if %>


<style type="text/css">
.submissions-table { border-color:#999999; }
.submissions-table th,
.submissions-table td { padding-left:8px; padding-right:8px; padding-top:6px; padding-bottom:6px; text-align:left; }
</style>