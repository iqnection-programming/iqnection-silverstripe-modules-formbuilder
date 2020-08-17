<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class TextareaField extends Field
{
	private static $table_name = 'FormBuilderTextareaField';
	private static $singular_name = 'Textarea';
	
	private static $extensions = [
		\IQnection\FormBuilder\Extensions\InputField::class
	];
	
	private static $db = [
		'Rows' => 'Int',
		'MaxCharacters' => 'Int',
	];
	
	/**
	 * Array of formats where the key is the name and the value is the method to call for validation
	 * the method must be callable in IQnection\FormBuilder\Fields\TextField class
	 */
	private static $defaults = [
		'Rows' => 4
	];
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'MaxCharacters'
		]);
		$fields->addFieldToTab('Root.Settings', Forms\FieldGroup::create('Input Size',[
			Forms\NumericField::create('MaxSize','Maximum Characters'),
		]));
		return $fields;
	}
	
	public function getBaseField($validator = null)
	{
		$field = Forms\TextareaField::create($this->getFrontendFieldName())->setRows($this->Rows);
		$this->invokeWithExtensions('updateBaseField', $field);
		return $field;		
	}
	
	public function validateFormValue($value)
	{
		$errors = parent::validateFormValue($value);
		if ( (intval($this->MaxCharacters) > 0) && (strlen($value) > intval($this->MaxCharacters)) )
		{
			$errors[] = 'Please limit your input to '.intval($this->MaxCharacters).' characters';
		}
		return $errors;
	}
	
	public function getFieldJsValidation()
	{
		$rules = parent::getFieldJsValidation();
		if ($this->MaxCharacters > 0)
		{
			$rules['maxlength'] = $this->MaxCharacters;
		}
		return $rules;
	}	
}