<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class CheckboxGroupField extends Field
{
	private static $table_name = 'FormBuilderCheckboxGroupField';
	private static $singular_name = 'Checkbox Group';

	private static $extensions = [
		\IQnection\FormBuilder\Extensions\SelectField::class
	];

	private static $db = [
		'HorizontalLayout' => 'Boolean',
		'MinSelections' => 'Int',
		'MaxSelections' => 'Int',
	];

	private static $defaults = [
		'MinSelections' => 0,
		'MaxSelections' => 0,
	];

	public function validate()
	{
		$result = parent::validate();
		if ($this->MinSelections > $this->MaxSelections)
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

	public function ExtraCssClasses($as_string = false)
	{
		$classes = parent::ExtraCssClasses($as_string);
		if ($this->HorizontalLayout)
		{
			$classes[] = 'horizontal';
		}
		return $classes;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$defaults = $this->Options()->Filter('DefaultSelected',1);
		$field = Forms\CheckboxSetField::create($this->getFrontendFieldName());
		if ($defaults->Count())
		{
			$field->setDefaultItems($defaults->Column('ID'));
		}
		$field->setSource($this->getFieldSourceArray());
		return $field;
	}

	public function getJavaScriptValidatorName()
	{
		$name = '[name^="'.$this->getFrontendFieldName().'["]';
		return $name;
	}

	public function getjQuerySelector()
	{
		$selector = '[name^="'.$this->getFrontendFieldName().'["]';
		$this->extend('updatejQuerySelector', $selector);
		return $selector;
	}

	public function getOptionjQuerySelector($option)
	{
		return $this->getjQuerySelector().'[value="'.$option->ID.'"]';
	}
}











