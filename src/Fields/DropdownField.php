<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use IQnection\FormBuilder\Extensions\SelectField;

class DropdownField extends Field
{
	private static $table_name = 'FormBuilderDropdownField';
	private static $singular_name = 'Dropdown';

	private static $extensions = [
		SelectField::class
	];

	private static $db = [
		'EmptyString' => 'Varchar(50)',
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->dataFieldByName('EmptyString')
			->setAttribute('placeholder','ex. --- Select ---')
			->setDescription('This will display when no value is selected.');
		return $fields;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$field = Forms\DropdownField::create($this->getFrontendFieldName());
		if ($this->EmptyString)
		{
			$field->setEmptyString($this->EmptyString);
		}
		$field->setSource($this->getFieldSourceArray());
		if ($defaultSelection = $this->Options()->Find('DefaultSelected',1))
		{
			$field->setValue($defaultSelection->ID);
		}
		return $field;
	}

	public function validate()
	{
		$result = parent::validate();
		if ($this->owner->Options()->Filter('DefaultSelected',1)->Count() > 1)
		{
			$result->addError('You may only select one default value');
		}
		return $result;
	}

	public function getOptionjQuerySelector($option)
	{
		return $this->getjQuerySelector().' option[value="'.$option->ID.'"]';
	}
}











