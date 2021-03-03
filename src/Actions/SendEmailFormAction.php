<?php

namespace IQnection\FormBuilder\Actions;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\Fields\EmailField;
use SilverStripe\Forms;
use IQnection\FormBuilder\Model\FormAction;
use SilverStripe\Control\Email\Email;

class SendEmailFormAction extends FormAction
{
	private static $table_name = 'FormBuilderSendEmailFormAction';
	private static $singular_name = 'Send Email';
	private static $plural_name = 'Send Emails';

	private static $db = [
		'FromEmail' => 'Varchar(50)',
		'FromName' => 'Varchar(50)',
		'ReplyTo' => 'Varchar(50)',
		'To' => 'Varchar(255)',
		'CC' => 'Varchar(255)',
		'BCC' => 'Varchar(255)',
		'Subject' => 'Varchar(255)',
		'IncludeSubmission' => 'Boolean',
		'MakeFileLinks' => 'Boolean',
		'AttachUploadedFiles' => 'Boolean',
		'Body' => 'HTMLText',
	];

	private static $many_many = [
		'EmailFields' => EmailField::class,
		'ReplyToEmailFields' => EmailField::class
	];

	private static $form_builder_many_many_duplicates = [
		'EmailFields',
		'ReplyToEmailFields'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'EmailFields',
			'ReplyToEmailFields',
			'To',
			'ReplyTo'
		]);
		$fields->dataFieldByName('CC')->setDescription('Comma separate multiple email addresses');
		$fields->dataFieldByName('BCC')->setDescription('Comma separate multiple email addresses');
		$emailFields = $this->getAvailableEmailFields()->map('ID','Name');

		$fields->insertAfter('FromName', Forms\FieldGroup::create('Reply To', [
			Forms\TextField::create('ReplyTo','Enter Email')->setDescription('Comma separate multiple email addresses'),
			Forms\CheckboxSetField::create('ReplyToEmailFields','Available Email fields from the form')
				->setSource($emailFields)
		]));
		$fields->insertAfter('FromName', Forms\FieldGroup::create('Send To', [
			Forms\TextField::create('To','Enter Email')->setDescription("(Optional) Defaults to From address\nSeparate multiple email addresses with a comma"),
			Forms\CheckboxSetField::create('EmailFields','Available Email fields from the form')
				->setSource($emailFields)
		]));

		if ($MakeFileLinks = $fields->dataFieldByName('MakeFileLinks'))
		{
			$MakeFileLinks->setDescription('If enabled, links will be provided to uploaded files. You must be logged in to view these files');
		}
		if ($AttachUploadedFiles = $fields->dataFieldByName('AttachUploadedFiles'))
		{
			$AttachUploadedFiles->setDescription('Attaches submitted files to the email');
		}
		return $fields;
	}

	public function getTitle()
	{
		return "To: ".$this->To."\nSubject: ".$this->Subject;
	}

	public function getAvailableEmailFields()
	{
		$emailFieldIDs = [0];

		foreach($this->FormBuilder()->DataFields() as $dataField)
		{
			if ($dataField instanceof EmailField)
			{
				$emailFieldIDs[] = $dataField->ID;
			}
		}
		return EmailField::get()->Filter('ID',$emailFieldIDs);
	}

	public function validate()
	{
		$result = parent::validate();
		if (!$this->FromEmail)
		{
			$result->addFieldError('From','Please set a From address');
		}
		if (!$this->Subject)
		{
			$result->addFieldError('Subject','Please set a Subject');
		}
		if ( (!$this->To) && (!$this->EmailFields()->Count()) )
		{
			$result->addError('You must set a recipient, or select form email fields');
		}
		foreach(['To','ReplyTo','CC','BCC'] as $fieldName)
		{
			if ($this->{$fieldName})
			{
				$fieldValue = preg_replace('/\s/','',$this->{$fieldName});
				$this->{$fieldName} = $fieldValue;
				$emailCount = substr_count($fieldValue, '@');
				if ((substr_count($fieldValue, ',') + 1) != $emailCount)
				{
					$result->addFieldError($fieldName, 'Multiple email addresses must be separated with a comma');
				}
			}
		}
		return $result;
	}

	public function generateEmail($form, $data, $submission)
	{
		// test the conditions
		if (!$this->testConditions($data))
		{
			return null;
		}

		// Send the email
		$email = Email::create()
			->setFrom($this->FromEmail)
			->setSubject($this->Subject)
			->setData(['Action' => $this, 'Submission' => $submission])
			->setHTMLTemplate('SendEmailFormAction');
		if ($this->FromName)
		{
			$email->setFrom($this->FromEmail, $this->FromName);
		}
		if ($this->ReplyTo)
		{
			$email->addReplyTo($this->ReplyTo);
		}
		foreach($this->ReplyToEmailFields() as $emailField)
		{
			if ( ($submittedValue = $submission->SubmissionFieldValues()->Find('FormBuilderFieldID', $emailField->ID)) && (trim($submittedValue->Value)) )
			{
				$email->addReplyTo(trim($submittedValue->Value));
			}
		}
		if (trim($this->To))
		{
			foreach(explode(',',$this->To) as $to)
			{
				$email->addTo(trim($to));
			}
		}
		if (trim($this->CC))
		{
			foreach(explode(',',$this->CC) as $CC)
			{
				$email->addCC(trim($CC));
			}
		}
		if (trim($this->BCC))
		{
			foreach(explode(',',$this->BCC) as $BCC)
			{
				$email->addBCC(trim($BCC));
			}
		}
		foreach($this->EmailFields() as $emailField)
		{
			if ( ($submittedValue = $submission->SubmissionFieldValues()->Find('FormBuilderFieldID', $emailField->ID)) && (trim($submittedValue->Value)) )
			{
				$email->addTo(trim($submittedValue->Value));
			}
		}
		if ($this->AttachUploadedFiles)
		{
			foreach($submission->SubmissionFieldValues()->Exclude('FileID',0) as $uploadFieldValue)
			{
				$file = $uploadFieldValue->File();
				if ($file->Exists())
				{
					$email->addAttachmentFromData($file->getString(), $file->getFilename());
				}
			}
		}
		$this->extend('updateEmail', $email, $form, $data, $submission);
		return $email;
	}

	public function onFormSubmit($form, $data, $submission)
	{
		if ($email = $this->generateEmail($form, $data, $submission))
		{
			return $email->send();
		}
	}

	public function getRecipients($submission)
	{
		$recipients = [
			'To' => explode(',', $this->To),
			'CC' => explode(',', $this->CC),
			'BCC' => explode('BCC', $this->BCC)
		];
		foreach($this->EmailFields() as $emailField)
		{
			if ( ($submittedValue = $submission->SubmissionFieldValues()->Find('FormBuilderFieldID', $emailField->ID)) && (trim($submittedValue->Value)) )
			{
				$recipients['To'][] = trim($submittedValue->Value);
			}
		}
		$recipients['To'] = array_filter($recipients['To']);
		return $recipients;
	}
}














