<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Assets\File;
use SilverStripe\Forms;
use IQnection\FormBuilder\Control\FormBuilderPreview;
use IQnection\FormBuilder\Actions\SendEmailFormAction;
use SilverStripe\ORM\FieldType;

class Submission extends DataObject
{
	private static $table_name = 'FormBuilderSubmission';

	private static $db = [
		'FormData' => 'Text',
		'PageName' => 'Varchar(255)',
	];

	private static $has_many = [
		'SubmissionFieldValues' => SubmissionFieldValue::class
	];

	private static $has_one = [
		'Page' => \Page::class,
		'FormBuilder' => FormBuilder::class
	];

	private static $summary_fields = [
		'ID' => 'ID',
		'Created.Nice' => 'Date',
	];

	private static $default_sort = 'Created DESC';

	private static $cascade_deletes = [
		'SubmissionFieldValues'
	];

	public function CanCreate($member = null, $context = [])
	{
		return false;
	}

	public function CanEdit($member = null, $context = [])
	{
		return false;
	}

	public function CanView($member = null, $context = [])
	{
		return true;
	}

	public function CanDelete($member = null, $context = [])
	{
		return true;
	}

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'FormData',
			'PageID',
			'FormBuilderID',
			'SubmissionFieldValues'
		]);

		$links = [];
		foreach($this->FormBuilder()->Actions() as $formAction)
		{
			if ( ($formAction instanceof SendEmailFormAction) && ($formAction->testConditions($this->RawPostData())) )
			{
				$url = FormBuilderPreview::singleton()->Link(FormBuilderPreview::join_links('_resendsubmissions', $this->ID, $formAction->ID));
				$recipients = $formAction->getRecipients($this);
				$links[] = '<p>Email Name: '.$formAction->Name.'<br />To: '.implode(', ',$recipients['To']).'<br />Subject: '.$formAction->Subject.'<br /><a href="'.$url.'" target="_blank">Send</a></p>';
			}
		}
		if (count($links))
		{
			$fields->addFieldToTab('Root.Main', Forms\ReadonlyField::create('_resendEmails', 'Resend Email')
				->setValue(FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, implode("", $links))));
		}

		$fields->addFieldToTab('Root.Main', Forms\ReadonlyField::create('Created','Submitted At') );
		if ( (!$this->PageName) && ($this->Page()->Exists()) )
		{
			$this->PageName = (string) $this->Page()->Breadcrumbs(20, true, false, true);
			$this->PageName = trim($this->PageName);
			$this->write();
		}
		$fields->addFieldToTab('Root.Main', Forms\HTMLReadonlyField::create('PageName','Page Name') );

		$rawFieldData = [];
		foreach($this->SubmissionFieldValues() as $submissionValue)
		{
			$fields->addFieldToTab('Root.Main', $submissionValue->getReadonlyField());
			$rawFieldData[] = $submissionValue->DebugInfo();
		}

if((defined('IS_IQ'))&&(IS_IQ)) {
		$fields->addFieldToTab('Root.Raw', Forms\LiteralField::create('_rawPost','<pre>'.print_r($this->RawPostData(), 1).'</pre>'));
		$rawFieldData = array_filter($rawFieldData);
		$fields->addFieldToTab('Root.Fields Debug', Forms\LiteralField::create('_fieldsDebug','<pre>'.print_r($rawFieldData, 1).'</pre>'));
}
		return $fields;
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();
		if ( ($this->Page()->Exists()) && (empty($this->PageName)) )
		{
			$this->PageName = (string) $this->Page()->Breadcrumbs(20, true, false, true);
			$this->PageName = trim($this->PageName);
			$this->forceChange(true);
		}
	}

	public function getTitle()
	{
		$title = '';
		if ($this->FormBuilder()->Exists())
		{
			$title .= $this->FormBuilder()->Title.' ';
		}
		$title .= 'Submission #'.$this->ID;
		return $title;
	}

	public function relField($field)
	{
		if ($formData = $this->RawFormData())
		{
			if (array_key_exists($field, $formData))
			{
				return $formData[$field];
			}
		}
		return parent::relField($field);
	}

	protected $_rawPostValues;
	public function RawPostData()
	{
		if (is_null($this->_rawPostValues))
		{
			$this->_rawPostValues = [];
			foreach($this->SubmissionFieldValues() as $submissionValue)
			{
				$this->_rawPostValues[$submissionValue->FormBuilderField()->getFrontendFieldName()] = unserialize($submissionValue->RawValue);
			}
		}
		return $this->_rawPostValues;
	}

	protected $_formData;
	public function RawFormData()
	{
		if (is_null($this->_formData))
		{
			$this->_formData = unserialize($this->FormData);
		}
		return $this->_formData;
	}
}










