<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\Extensions\Cacheable;
use IQnection\FormBuilder\Extensions\SelectField;
use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use SilverStripe\ORM\FieldType;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

class SelectFieldOption extends DataObject
{
	private static $extensions = [
		Cacheable::class
	];
	
	private static $table_name = 'FormBuilderSelectFieldOption';
	private static $singular_name = 'Option';
	private static $plural_name = 'Options';
	
	private static $db = [
		'SortOrder' => 'Int',
		'Label' => 'Varchar(255)',
		'Value' => 'Text',
		'DefaultSelected' => 'Boolean',
		'HideByDefault' => 'Boolean'
	];
	
	private static $has_one = [
		'Field' => Field::class
	];
	
	private static $has_many = [
		'SelectionActions' => SelectFieldOptionAction::class.'.Parent'
	];
	
	private static $belongs_many_many = [
		'OwnerFieldActions' => FieldAction::class.'.Children',
		'OwnerSelectionActions' => SelectFieldOptionAction::class.'.Children'
	];
	
	private static $summary_fields = [
		'ID' => 'ID',
	];
	
	private static $default_sort = 'SortOrder ASC';
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'SortOrder',
			'SelectionActions',
			'OwnerFieldActions',
			'OwnerSelectionActions'
		]);
		$fields->replaceField('Label', $fields->dataFieldByName('Label')->performReadonlyTransformation());
		$fields->replaceField('Value', $fields->dataFieldByName('Value')->performReadonlyTransformation());
		$fields->replaceField('DefaultSelected', $fields->dataFieldByName('DefaultSelected')->performReadonlyTransformation());
		
		$fields->addFieldToTab('Root.Main', Forms\CheckboxField::create('HideByDefault','Hide this selection by default'));
		
		$fields->addFieldToTab('Root.Display', Forms\HeaderField::create('_displayText','Select and add an action, then set the conditions',2));
		$fields->addFieldToTab('Root.Display', Forms\GridField\GridField::create(
			'SelectionActions',
			'Selection Actions',
			$this->SelectionActions(),
			Forms\GridField\GridFieldConfig_RecordEditor::create(100)
				->addComponent($GridFieldAddNewMultiClass = new GridFieldAddNewMultiClass())
				->removeComponentsByType(Forms\GridField\GridFieldAddNewButton::class)
		));
		return $fields;
	}
	
	public function Explain()
	{
		$text = '<div style="padding-left:10px;">Option: '.$this->getOptionLabel();
		if ($this->HideByDefault)
		{
			$text .= '|Hidden';
		}
		foreach($this->SelectionActions() as $SelectionAction)
		{
			$text .= $SelectionAction->Explain();
		}
		$this->extend('updateExplanation', $text);
		$text .= '</div>';
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $text);
	}
	
	public function searchableFields()
	{
		$fieldIDs = [];
		foreach($this->Field()->FormBuilder()->DataFields() as $field)
		{
			if ($field->hasExtension(SelectField::class))
			{
				$fieldIDs[] = $field->ID;
			}
		}
		$fields = [
			'FieldID' => [
				'filter' => 'ExactMatchFilter',
				'title' => 'Field',
				'field' => Forms\DropdownField::create('FieldID')
					->setSource(Field::get()->Filter('ID',$fieldIDs)->map('ID','Name'))
			]
		];
		return $fields;
	}
	
	public function getOptionLabel()
	{
		$label = (is_null($this->Label)) ? $this->Value : $this->Label;
		$this->extend('updateOptionLabel', $label);
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $label);
	}
	
	public function getTitle()
	{
		return $this->Field()->Name.': '.$this->Value;
	}
	
	public function validate()
	{
		$result = parent::validate();
		if (!$this->Value)
		{
			$result->addError('You must set a value');
		}
		return $result;
	}
	
	public function getjQuerySelector($valueSelector = false)
	{
		// allow the parent field to generate the selector
		return $this->Field()->getOptionjQuerySelector($this,$valueSelector);
	}
}