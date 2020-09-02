<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use SilverStripe\Core\Convert;

class TextField extends Field
{
	private static $table_name = 'FormBuilderTextField';
	private static $singular_name = 'Text';
	
	private static $extensions = [
		\IQnection\FormBuilder\Extensions\InputField::class
	];
	
	private static $db = [
		'MinSize' => 'Int',
		'MaxSize' => 'Int',
		'Format' => 'Varchar(255)',
	];
	
	/**
	 * Array of formats where the key is the name and the value is the method to call for validation
	 * the method must be callable in IQnection\FormBuilder\Fields\TextField class
	 */
	private static $formats = [
		'validatePhone' => 'Phone Number',
		'validateNumber' => 'Number',
		'validateDecimal' => 'Decimal',
		'validateInteger' => 'Integer',
	];
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'MinSize',
			'MaxSize'
		]);
		$fields->addFieldToTab('Root.Settings', Forms\FieldGroup::create('Input Size',[
			Forms\NumericField::create('MinSize','Minimum Size'),
			Forms\NumericField::create('MaxSize','Maximum Size'),
		]));
		$fields->addFieldToTab('Root.Settings', Forms\DropdownField::create('Format', 'Validation Format')
			->setSource($this->Config()->get('formats'))
			->setEmptyString('-- Select --')
			->setDescription('(Optional)'));
		return $fields;
	}
	
	public function validateFormValue($value)
	{
		$errors = parent::validateFormValue($value);
		$formats = $this->Config()->get('formats');
		if ( (array_key_exists($this->Format, $formats)) && ($this->hasMethod($formats[$this->Format])) )
		{
			$formatErrors = $this->invokeWithExtensions($formats[$this->Format], $value);
			if (is_array($formatErrors))
			{
				$errors = array_merge($errors, $formatErrors);
			}
		}
		return $errors;
	}
	
	public function getBaseField(&$validator = null)
	{
		$field = Forms\TextField::create($this->getFrontendFieldName());
		return $field;		
	}
	
	public function validatePhone($value)
	{
		$numbers = preg_replace('/[\(\)\-\s]/','',$value);
		if (preg_match('/[^0-9]/',$numbers))
		{
			$errors[] = 'Please enter a valid phone number';
		}
		elseif (strlen($numbers) < 10)
		{
			$errors[] = 'Please enter a valid phone number';
		}
		return $errors;
	}
	
	public function validateNumber($value)
	{
		if (!is_numeric($numbers))
		{
			$errors[] = 'Please enter a valid number';
		}
		return $errors;
	}
	
	public function validateDecimal($value)
	{
		if (!is_numeric($numbers))
		{
			$errors[] = 'Please enter a valid decimal number';
		}
		return $errors;
	}
	
	public function validateInteger($value)
	{
		if (!is_int($numbers))
		{
			$errors[] = 'Please enter a valid whole number';
		}
		return $errors;
	}
	
	public function getFieldJsValidation()
	{
		$rules = parent::getFieldJsValidation();
		if ($this->MinSize > 0)
		{
			$rules['minlength'] = $this->MinSize;
		}
		if ($this->MaxSize > 0)
		{
			$rules['maxlength'] = $this->MaxSize;
		}
		switch($this->Format)
		{
			case 'validatePhone':
				$rules['phoneUS'] = true;
				break;
			case 'validateNumber':
			case 'validateDecimal':
				$rules['number'] = true;
				break;
			case 'validateInteger':
				$rules['digits'] = true;
				break;
		}
		return $rules;
	}
}














