<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class CheckboxField extends Field
{
	private static $table_name = 'FormBuilderCheckboxField';
	private static $singular_name = 'Checkbox';

	private static $extensions = [
		\IQnection\FormBuilder\Extensions\InputField::class
	];

	private static $db = [
		'CheckedValue' => 'Varchar(255)',
		'UncheckedValue' => 'Varchar(255)',
		'DefaultChecked' => 'Boolean'
	];

	private static $defaults = [
		'CheckedValue' => 'Yes',
		'UncheckedValue' => 'No'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName(['Placeholder']);
		return $fields;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$field = Forms\CheckboxField::create($this->getFrontendFieldName());
		if ($this->DefaultChecked)
		{
			$field->setValue(true);
		}
		return $field;
	}

	public function prepareSubmittedValue($value, $formData = [])
	{
		$value = $value	? $this->CheckedValue : $this->UncheckedValue;
		return $value;
	}
}











