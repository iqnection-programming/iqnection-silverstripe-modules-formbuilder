<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Assets\File;
use SilverStripe\Forms;

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
		$fields->addFieldToTab('Root.Main', Forms\ReadonlyField::create('Created','Submitted At') );
		$fields->addFieldToTab('Root.Main', Forms\ReadonlyField::create('PageName','Page Name') );

		foreach($this->SubmissionFieldValues() as $submissionValue)
		{
			$fields->addFieldToTab('Root.Main', $submissionValue->getReadonlyField());
		}
		$fields->addFieldToTab('Root.Cache', Forms\LiteralField::create('cachedSubmission', '<div style="max-width:100%;"><pre><xmp>'.print_r(unserialize($this->FormData),1).'</xmp></pre></div>'));
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










