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
		'Subject' => 'Varchar(255)',
		'IncludeSubmission' => 'Boolean',
		'MakeFileLinks' => 'Boolean',
		'Body' => 'HTMLText',
	];
	
	private static $many_many = [
		'EmailFields' => EmailField::class
	];
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'EmailFields'
		]);
		$fields->insertAfter('To', Forms\CheckboxSetField::create('EmailFields','Available Email fields from the form')
			->setSource($this->getAvailableEmailFields()->map('ID','Name')));
		$fields->dataFieldByName('ReplyTo')->setDescription('(Optional) Defaults to From address');
		
		if ($MakeFileLinks = $fields->dataFieldByName('MakeFileLinks'))
		{
			$MakeFileLinks->setDescription('If enabled, links will be provided to uploaded files. You must be logged in to view these files');
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
			$result->addFieldError('From','Please set a message Subject');
		}
		if ( (!$this->To) && (!$this->EmailFields()->Count()) )
		{
			$result->addError('You must set a recipient, or select form email fields');
		}
		return $result;
	}
	
	public function onFormSubmit($form, $data, $submission) 
	{ 
		// Send the email
		$email = Email::create()
			->setFrom($this->FromEmail)
			->setSubject($this->Subject)
			->setData($this)
			->setHTMLTemplate('SendEmailFormAction');
		if ($this->FromName)
		{
			$email->setFrom($this->FromEmail, $this->FromName);
		}
		if ($this->ReplyTo)
		{
			$email->setReplyTo($this->ReplyTo);
		}
		if (trim($this->To))
		{
			$email->addTo(trim($this->To));
		}
		foreach($this->EmailFields() as $emailField)
		{
			if ( ($submittedValue = $submission->SubmissionFieldValues()->Find('FormBuilderFieldID', $emailField->ID)) && (trim($submittedValue->Value)) )
			{
				$email->addTo(trim($submittedValue->Value));
			}
		}
		return $email->send();
	}
}














