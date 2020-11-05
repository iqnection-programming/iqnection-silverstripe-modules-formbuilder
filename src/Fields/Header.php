<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use SilverStripe\ORM\FieldType;

class Header extends Field
{
	private static $table_name = 'FormBuilderHeader';
	private static $singular_name = 'Header';

	private static $extensions = [
		\IQnection\FormBuilder\Extensions\DatalessField::class
	];

	private static $db = [
		'Type' => "Enum('H1,H2,H3,H4,H5,H6','H3')",
		'Content' => 'Varchar(255)',
	];

	private static $defaults = [
		'Type' => 'H3'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->dataFieldByName('Name')->setAttribute('placeholder','My Header');
		$types = [];
		foreach($this->dbObject('Type')->enumValues() as $type)
		{
			$types[$type] = preg_replace('/^H/','Heading ',$type);
		}
		$fields->dataFieldByName('Type')->setSource($types);
		return $fields;
	}

	public function getGridFieldName()
	{
		$text = '<h1 style="margin:0;">'.$this->getField('Name').'</h1>';
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $text);
	}

	public function validate()
	{
		$result = parent::validate();

//		if (!$this->Content)
//		{
//			$result->addError('Please add heading text');
//		}
		return $result;
	}

	public function getBaseField(&$validator = null)
	{
		$Content = !empty($this->owner->Content) ? $this->owner->Content : $this->owner->Name;
		$field = Forms\HeaderField::create($this->getFrontendFieldName(), $Content, substr($this->Type, -1, 1));
		return $field;
	}
}