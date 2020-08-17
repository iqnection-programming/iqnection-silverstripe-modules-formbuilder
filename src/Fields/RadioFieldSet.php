<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms;

class RadioFieldSet extends Field
{
	private static $table_name = 'FormBuilderRadioFieldSet';
	private static $singular_name = 'Radio Buttons';
	
	private static $extensions = [
		\IQnection\FormBuilder\Extensions\SelectField::class
	];
	
	private static $db = [
		'HorizontalLayout' => 'Boolean'
	];
	
	private static $defaults = [
		'HorizontalLayout' => 0
	];
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Settings', $HorizontalLayoutField = Forms\OptionSetField::create('HorizontalLayout', 'Layout')
			->setSource([0 => 'Vertical', 1 => 'Horizontal']) );
		if (!$this->Exists())
		{
			$HorizontalLayoutField->setValue(0);
		}
		return $fields;
	}
	
	public function validate()
	{
		$result = parent::validate();
		if ($this->Options()->Filter('DefaultSelected',1)->Count() > 1)
		{
			$result->addError('You can only have one default value selected');
		}
		return $result;
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
	
	public function getBaseField($validator = null)
	{
		$selectedDefault = $this->Options()->Filter('DefaultSelected',1)->First();
		$field = Forms\OptionSetField::create($this->getFrontendFieldName());
		if ($selectedDefault)
		{
			$field->setValue($selectedDefault->ID);
		}
		$field->setSource($this->getFieldSourceArray());
		return $field;		
	}
	
	public function getOptionjQuerySelector($option, $valueSelector = false)
	{
		$selector = $this->getjQuerySelector();
		if ($valueSelector)
		{
			$selector .= '[value="'.$option->ID.'"]';
		}
		return $selector;
	}
}