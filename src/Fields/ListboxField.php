<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use IQnection\FormBuilder\Extensions\SelectField;

class ListboxField extends Field
{
	private static $table_name = 'FormBuilderListboxField';
	private static $singular_name = 'Listbox';

	private static $extensions = [
		SelectField::class
	];

	private static $db = [
		'MinSelections' => 'Int',
		'MaxSelections' => 'Int',
	];

	private static $defaults = [
		'MinSelections' => 0,
		'MaxSelections' => 0,
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'MinSelections',
			'MaxSelections',
		]);
		$fields->addFieldToTab('Root.Settings', Forms\FieldGroup::create('Requirements', [
			Forms\NumericField::create('MinSelections','Minimum Allowed Selections'),
			Forms\NumericField::create('MaxSelections','Maximum Allowed Selections'),
		])->setDescription('Set to 0 to disable
			<br />Settings these values does NOT make this a required field'));
		return $fields;
	}

	public function validate()
	{
		$result = parent::validate();
		if ( ($this->MinSelections > $this->MaxSelections) && ($this->MaxSelections < 0) )
		{
			$result->addError('Minimum Selections cannot be more than Maximum Selections');
		}
		return $result;
	}

	public function validateFormValue($value)
	{
		$errors = parent::validateFormValue($value);
		// is this a multiple selection
		if ( ($this->MaxSelections > 0) && ( (!is_array($value)) || (count($value) > $this->MaxSelections) ) )
		{
			$errros[] = 'Please limit your selections to '.$this->MaxSelections.' options';
		}
		if ( ($this->MinSelections > 0) && ( (!is_array($value)) || (count($value) < $this->MinSelections) ) )
		{
			$errros[] = 'Please select a minimum of '.$this->MinSelections.' options';
		}
		return $errors;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$field = Forms\ListboxField::create($this->getFrontendFieldName());
		$source = $this->getFieldSourceArray();
		if ( (!$this->MinSelections) || ($this->EmptyString) )
		{
			$source = array_reverse($source, true);
			$source[0] = $this->EmptyString;
			$source = array_reverse($source, true);
		}
		$field->setSource($source);
		$defaultSelections = $this->Options()->Filter('DefaultSelected',1);
		if ($defaultSelections->Count())
		{
			$field->setDefaultItems($defaultSelection->Column('ID'));
		}
		return $field;
	}

	public function getJavaScriptValidatorName()
	{
		$name = parent::getJavaScriptValidatorName();
		$name .= '[]';
		return $name;
	}

	public function getFieldJsValidation()
	{
		$rules = parent::getFieldJsValidation();
		if ($this->MinSelections > 0)
		{
			$rules['minSelections'] = $this->MinSelections;
		}
		if ($this->MaxSelections > 0)
		{
			$rules['maxSelections'] = $this->MaxSelections;
		}
		return $rules;
	}

	public function getOptionjQuerySelector($option)
	{
		return $this->getjQuerySelector().' option[value="'.$option->Value.'"]';
	}
}











