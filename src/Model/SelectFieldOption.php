<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\Extensions\Cacheable;
use IQnection\FormBuilder\Extensions\Exportable;
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
use IQnection\FormBuilder\FormBuilder;
use IQnection\FormBuilder\Extensions\Duplicable;

class SelectFieldOption extends DataObject
{
	private static $extensions = [
		Cacheable::class,
		Duplicable::class,
		Exportable::class
	];

	private static $table_name = 'FormBuilderSelectFieldOption';
	private static $singular_name = 'Option';
	private static $plural_name = 'Options';

	private static $db = [
		'SortOrder' => 'Int',
		'Value' => 'Text',
		'Label' => 'Varchar(255)',
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
		'OwnerSelectionActions' => SelectFieldOptionAction::class.'.Children',
		'OwnerFormActions' => FormAction::class.'.ConditionFieldSelections',
	];

	private static $summary_fields = [
		'ID' => 'ID',
	];

	private static $default_sort = 'SortOrder ASC';

	private static $export_ignore_fields = [
		'FieldID'
	];

	private static $import_unique_field = 'Value';

	private static $form_builder_has_many_duplicates = [
		'SelectionActions'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'SortOrder',
			'SelectionActions',
			'OwnerFieldActions',
			'OwnerSelectionActions',
			'OwnerFormActions',
			'FieldID',
		]);

		$fields->replaceField('Value', Forms\TextField::create('Value','Value'));
		$fields->replaceField('DefaultSelected', $fields->dataFieldByName('DefaultSelected')->performReadonlyTransformation());

		$fields->addFieldToTab('Root.Main', Forms\CheckboxField::create('HideByDefault','Hide this selection by default'));

		$fields->addFieldToTab('Root.Main', Forms\HeaderField::create('_displayText','Select and add an action, then set the conditions',2));
		$fields->addFieldToTab('Root.Main', Forms\GridField\GridField::create(
			'SelectionActions',
			'Selection Actions',
			$this->SelectionActions(),
			Forms\GridField\GridFieldConfig_RecordEditor::create(100)
				->addComponent($GridFieldAddNewMultiClass = new GridFieldAddNewMultiClass())
				->removeComponentsByType(Forms\GridField\GridFieldAddNewButton::class)
		));
		return $fields;
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		$this->FormBuilder()->clearAllCache();
	}

	public function getOnLoadFieldSelectionActions($onLoadCondition = null)
	{
		$actions = [];
		// create the action if the option is hidden on form load
		if ($this->HideByDefault)
		{
			$actions['fieldActions'][] = [
				'id' => $this->ID.'.1',
				'name' => $this->Field()->Name. ' Option: '.$this->Value,
				'action' => [
					'type' => 'Hidden on Load',
					'selector' => $this->getjQuerySelector(true),
					'callback' => 'actionHideFieldOption',
					'fieldType' => $this->Field()->singular_name(),
					'fieldSelector' => $this->Field()->getjQuerySelector(),
					'ownerId' => (int) $this->ID,
				],
				'conditions' => $onLoadCondition,
				'conditionsHash' => 'onFormLoad'
			];
		}
		$this->extend('updateOnLoadFieldSelectionActions', $actions);
		return $actions;
	}

	public function FormBuilder()
	{
		return $this->Field()->FormBuilder();
	}

	public function hasActions()
	{
		return (bool) $this->SelectionActions()->Count();
	}

	public function Explain()
	{
		$text = '<div>Option: <strong>'.$this->getOptionLabel().'</strong></div>';
		if ($this->HideByDefault)
		{
			$text .= '<em>(Default Hidden)</em>';
		}
		if ($this->SelectionActions()->Count())
		{
			$text .= '<ul>';
			foreach($this->SelectionActions() as $SelectionAction)
			{
				$text .= '<li>'.$SelectionAction->Explain().'</li>';
			}
			$text .= '</ul>';
		}
		$this->extend('updateExplanation', $text);
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