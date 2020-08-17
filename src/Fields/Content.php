<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class Content extends Field
{
	private static $table_name = 'FormBuilderContent';
	private static $singular_name = 'Content';
	
	private static $extensions = [
		\IQnection\FormBuilder\Extensions\DatalessField::class
	];
	
	private static $db = [
		'Content' => "HTMLText"
	];
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->dataFieldByName('Name')->setDescription('For interal use only');
		return $fields;
	}

	public function validate()
	{
		$result = parent::validate();
		
		if (!$this->Content)
		{
			$result->addError('Please add content');
		}
		return $result;
	}
	
	public function getBaseField($validator = null)
	{
		$field = Forms\LiteralField::create($this->getFrontendFieldName(), $this->renderWith('Includes/FormBuilderContentField'));
		return $field;
	}
}