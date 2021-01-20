<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType;
use SilverStripe\Forms;
use IQnection\FormBuilder\Fields\CheckboxField;

class InputField extends DataExtension
{
	private static $db = [
		'Placeholder' => 'Varchar(255)',
		'Label' => 'Varchar(255)',
		'Required' => 'Boolean',
		'Description' => 'Varchar(255)',
	];

	public function updateCMSFields($fields)
	{
		$fields->addFieldToTab('Root.Settings', $fields->dataFieldByName('Placeholder')
			->setDescription('Displays when the field is empty, does not produce a value') );
		$fields->dataFieldByName('Label')->setDescription('(Optional) Defaults to the field name. This will display as the field label on the form');
		$fields->dataFieldByName('Required')->setTitle('This is a Required Field');
		$fields->dataFieldByName('Description')->setDescription('(Optional) Small text to display under the field as a description');
		return $fields;
	}

	public function updateConditionOptions(&$field, $fieldAction = null, $fieldName = null)
	{
		if ($this->owner instanceof CheckboxField)
		{
			$field->push(Forms\SelectionGroup_Item::create('Has Value', null, 'Is Checked'));
			$field->push(Forms\SelectionGroup_Item::create('Is Empty', null, 'Non Checked'));
		}
		else
		{
			$field->push(Forms\SelectionGroup_Item::create('Has Value', null, 'Has Value'));
			$field->push(Forms\SelectionGroup_Item::create('Is Empty', null, 'No Value'));
		}
	}

	public function updateBaseField(&$field, &$validator)
	{
		if ($this->owner->Placeholder)
		{
			$field->setAttribute('placeholder',$this->owner->Placeholder);
		}
		if ($this->owner->Required)
		{
			$field->addExtraClass('required');
		}
		$label = ($this->owner->Label) ? $this->owner->Label : $this->owner->Name;
		$field->setTitle(FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $label));
		if ($this->owner->Description)
		{
			$field->setDescription($this->owner->Description);
		}
	}

	public function updateFieldJsValidation(&$js)
	{
		if ($this->owner->Required)
		{
			$js['required'] = true;
		}
	}
}










