# SOCRA Form Builder Training

## Creating a New Form

Login to the SilverStripe CMS and navigate to the Form Builder area. Click the Add New Form button.

**Title:** This does not show to the public, its for SOCRA to quickly identify the forms in the list
**Submit Text:** set the submit button text
**Confirmation Text:** the confirmation message displayed after a successful form submission

## Managing Form Fields

Its recommended to add all fields before creating actions. Select the field type you want to create, click Add Field. All fields share some common settings…

**Field Name:** This will be the label used in notifications, and as the column heading in the submissions table.
**Label:** (Defaults to Field Name) When set, this will be the label displayed on the form
**Description:** Additional text displayed under the field on the form. Good for instructions
**Required:** Makes the field required
**Settings / Placeholder:** Text to display in an empty input field. This is a ghost value, not an actual value
**Settings / Activate this field:** When unchecked, the field will not used on the form.
**Settings / Display in Submissions Table:** Enable the field to show in the table of submissions
**Settings / Hide this field by default:** When checked, the field will be hidden on the form. Actions can be used to display the field.

### Credit Card Payment Field

Supplies all payment fields needed for credit card processing with Authorize.net.

**Allowed Card Types:** Set the cards to restrict what the user can use to pay with
**Transaction Type:** Auth only will require the capture to be made manually in Authorize.net
**Amount - Fixed Amount:** Set the amount, or base amount, of the charge. The user cannot directly change the amount charged to their card. Actions can be created to make adjustments to this amount.
**Amount - User Defined:** Provides an open field where the user can enter the charge amount. Good for donation forms

### Checkbox

**Check Value:** the value used when the box is checked
**Unchecked Value:** the value used when the box is not checked
**Default Checked:** set the box checked when the form loads

### Checkbox Group

A group of checkboxes where the user can select more than one option. You can set a minimum or maximum amount of allowed selections.

**Horizontal Layout:** Display the check boxes horizontally, defaults to a vertical list
**Min / Max Selections:** Set the minimum and/or maximum allowed selections
**Options:** See Select Field Options

### Select Field Options

Add the options available on the form. There is a list of predefined values that can be loaded. Adding predefined values will append the existing list, not replace. When adding predefined values, select the values and save the field, when the page refreshes, the values will be shown. Removing available options must be done manually. Values can be sorted by drag and drop.

**Selected by default:** When checked, this value will be selected when the form loads
**Value:** Similar to Field Name, this is the value saved with the submission and sent in notifications.
**Label:** (defaults to Value) Display a different label on the form.
_ex. For a long label, you might want to set the Value to something short and simple._

### Content

Add editorial content to the form using the common WYSIWYG editor.

**Settings / Spacing:** Set the space before and after the content. This is useful when adding a lot of text to be coupled with a specific field. Set the spacing to None to show the content closer to the field

### Dropdown

Standard dropdown select field

**Empty String:** set a value to display in the dropdown when no selection is made. This value does not validate as a selection made.

### Email

A standard text input field that includes email format validation. This field type is also used as an option when creating submission notifications.

### Field Group

Allows you to group up to 3 fields in a horizontal layout. The label for each field is moved below the input. Adding fields to the group is performed just like adding fields to the form.

### File Upload

Provides a file upload fields. All uploaded files are placed in the Files area, in the &quot;form-submissions&quot; folder. A subfolder is created with the same name as the form it was submitted from.

**Allowed File Extensions:** Predefined lists of file extensions to restrict which file types the user can upload
**Custom/Additional Extensions:** Add or set specific file extensions to the restrictions allowance
 Max File Size: Set the maximum file size allowed to upload. The Server Max cannot be exceeded regardless of the value set here.

### Header

Add preformatted header text to the form. This is useful for separating sections of fields.

### Listbox

This is a select field that allows users to select more than one value. Like a merge of a dropdown and checkbox group.

**Minimum/Maximum Allowed Selections:** Require a minimum and/or maximum amount of selections to be made

### Money

A simple text input field that validates to a decimal value. Extra validation can be used to restrict the input values.

**Minimum/Maximum:** Require a minimum or maximum amount to be entered

### Radio Buttons

Allows the user to select one of many options. Displayed like a checkbox group.

**Layout:** Display the options in a horizontal or vertical layout.

### Text

Standard text input

**Settings / Input Size:** Set a minimum or maximum size of the input. This is character length, not a numerical limit
**Validation Format:** Set a required format for the input (ex. Phone number, numbers only, etc.)

### Textarea

Standard large text input. Provides multi-line capabilities for larger amounts of content.

**Rows:** Set how tall you would like the text area to be. One row is equal to one line of text.
**Settings / Input Size:** Set a maximum length of characters allowed to be entered

### Select Field Options

Select fields are fields where the user can select one or more from a list of options (dropdown, radio group, checkbox group, listbox). Select field options also have options and actions. After the options are created and saved (ID will have a value), you can edit the option to set these configurations. Options can be hidden by default, and have available the same actions provided on fields.

## Field Actions

Field actions provide the ability to make the form more dynamic. These will be used to show or hide fields, based on conditions you create. Build conditions by selecting various fields and setting the requirements that must be met for the action to take place. For example, you may want the State dropdown to only show when the Country selected is United States. The State field would be set to Hide By Default. An action would created for the State field to show the field when the value of Country is &quot;United States&quot;. Hiding the field first and only showing when United States is selected will be easier that selecting all countries you don&#39;t want it to show for, but its possible to configure that was as well.

When all conditions of an action are met, it is triggered. More than one action can be triggered, even when conditions are different, as long as they&#39;re met.

## Action Conditions

Select one or more fields to monitor. Based on the field type, you can choose to trigger the action when the user selects or enters any value, does not select or has not entered any value, or only when specific values are selected. When a multi-option field is being monitored, you&#39;ll be provided with the list of all options to choose which specific options might trigger the action.

### Change Field Display

Make the current field displayed or hidden. If the Hide By Default box is checked, this action will show the field. If unchecked, this action will hide the field.

### Change Selection Display

Only available on select field options. Will show or hide the option based on the Hide By Default setting.

### Adjust Charge Amount (Only available on payment fields)

Set a positive or negative amount the charge should be adjusted, when conditions are met.

## Form Actions

Form actions are triggered when the form is loaded or the user submits the form. There are no conditions to monitor, its a simple action that will be triggered on every occurrence.

Currently, form actions are only be triggered when the form is successfully submitted.

### Send Email

**Name:** For internal purposes, to track actions when more than one is created.
**Event:** The form event, currently only On Form Submit is allowed
**From Email:** The email address used as the from email, this should always be set with an email on the same domain as the website, to reduce the risk of the email being flagged as spam.
**From Name:** the name to display along with the email address in mail clients
**Reply to:** An email address that will be used when the user chooses to reply to this email. This defaults to the from address
**To:** manually set an email address where the email will be sent. Comma separate multiple emails
**CC:** set email addresses to CC the email to. Comma separate multiple emails
**BCC:** set email addresses to BCC the email to. Comma separate multiple emails
**Available Email fields from the form:** A list of checkboxes will be provided to select which field from the form should be used to send the email to. Useful for sending autoresponders. Only Email fields are provided
**Subject:** Set the email subject
**Include Submission:** When checked, the submission values will be placed at the end of the email content
**Make file links:** When checked, any uploaded files will be provided as links in the email. Uploaded files can only be viewed by logged in CMS users, so this should not be checked when sending autoresponders to public users
**Body:** Provide the body of the email content