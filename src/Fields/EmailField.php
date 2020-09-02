<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class EmailField extends Field
{
	private static $table_name = 'FormBuilderEmailField';
	private static $singular_name = 'Email';
	
	private static $extensions = [
		\IQnection\FormBuilder\Extensions\InputField::class
	];
	
	public function getBaseField(&$validator = null)
	{
		$field = Forms\EmailField::create($this->getFrontendFieldName());
		return $field;		
	}
	
	public function getFieldJsValidation()
	{
		$rules = parent::getFieldJsValidation();
		$rules['email'] = true;
		return $rules;
	}
}