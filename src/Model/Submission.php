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
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'FormData',
			'PageID',
			'FormBuilderID',
			'SubmissionFieldValues'
		]);
		$fields->addFieldToTab('Root.Main', Forms\ReadonlyField::create('Created','Submitted') );
		$fields->addFieldToTab('Root.Main', Forms\ReadonlyField::create('_Page','Page')->setValue($this->Page()->Title) );
		foreach($this->SubmissionFieldValues() as $submissionValue)
		{
			$fields->addFieldToTab('Root.Main', $submissionValue->getReadonlyField());
		}
		return $fields;
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
		if ($formData = unserialize($this->FormData))
		{
			if (array_key_exists($field, $formData))
			{
				return $formData[$field];
			}
		}
		return parent::relField($field);
	}
}










