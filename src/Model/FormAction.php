<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Forms;

class FormAction extends DataObject
{
	private static $table_name = 'FormBuilderFormAction';
	private static $singular_name = 'Action';
	private static $plural_name = 'Actions';
	private static $hide_ancestor = FormAction::class;

	private static $db = [
		'Name' => 'Varchar(255)',
		'Event' => 'Varchar(255)'
	];

	private static $has_one = [
		'FormBuilder' => FormBuilder::class
	];

	private static $summary_fields = [
		'Name' => 'Name',
		'Event' => 'Trigger',
		'singular_name' => 'Action'
	];

	private static $form_events = [
		'onFormSubmit' => 'On Form Submit'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->dataFieldByName('Name')->setDescription('For internal purposes only');
		$fields->replaceField('Event', Forms\DropdownField::create('Event','Event')
			->setSource($this->Config()->get('form_events')) );
		return $fields;
	}

	public function onFormSubmit($form, $data, $submission) { }

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		$this->FormBuilder()->clearJsCache();
	}
}