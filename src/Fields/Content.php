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
		'Content' => "HTMLText",
		'TopPadding' => "Enum('None,Small,Medium,Large','Medium')",
		'BottomPadding' => "Enum('None,Small,Medium,Large','Medium')"
	];

	private static $defaults = [
		'TopPadding' => 'Medium',
		'BottomPadding' => 'Medium'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'TopPadding',
			'BottomPadding'
		]);

		$fields->dataFieldByName('Name')->setDescription('For interal use only');
		$fields->addFieldToTab('Root.Settings', Forms\FieldGroup::create('Spacing', [
			Forms\DropdownField::create('TopPadding','Top')->setSource($this->dbObject('TopPadding')->enumValues()),
			Forms\DropdownField::create('BottomPadding','Bottom')->setSource($this->dbObject('TopPadding')->enumValues())
		])->setDescription('Enter an integer for pixels'));
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

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$field = Forms\LiteralField::create($this->getFrontendFieldName(), $this->renderWith('Includes/FormBuilderContentField'));
		return $field;
	}

	public function updateExtraCssClasses(&$extraClasses)
	{
		$extraClasses[] = 'top-padding-'.strtolower($this->TopPadding);
		$extraClasses[] = 'bottom-padding-'.strtolower($this->BottomPadding);
		return $extraClasses;
	}
}




