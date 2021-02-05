<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Forms;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use IQnection\FormBuilder\Extensions\SelectField;
use SilverStripe\ORM\FieldType;
use IQnection\FormBuilder\Extensions\Duplicable;

class FormAction extends DataObject
{
	private static $table_name = 'FormBuilderFormAction';
	private static $singular_name = 'Action';
	private static $plural_name = 'Actions';
	private static $hide_ancestor = FormAction::class;

	private static $extensions = [
		Duplicable::class
	];

	private static $db = [
		'Name' => 'Varchar(255)',
		'Event' => 'Varchar(255)'
	];

	private static $has_one = [
		'FormBuilder' => FormBuilder::class
	];

	private static $many_many = [
		'ConditionFields' => Field::class,
		'ConditionFieldSelections' => SelectFieldOption::class
	];

	private static $many_many_extraFields = [
		'ConditionFields' => [
			'State' => "Enum('Has Value,Match,Is Empty','Has Value')",
		]
	];

	private static $summary_fields = [
		'Name' => 'Name',
		'Event' => 'Trigger',
		'singular_name' => 'Action'
	];

	private static $form_events = [
		'onFormSubmit' => 'On Form Submit'
	];

	private static $form_builder_many_many_duplicates = [
		'ConditionFields',
		'ConditionFieldSelections',
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'ConditionFields',
			'ConditionFieldSelections',
			'FormBuilderID'
		]);
		$fields->dataFieldByName('Name')->setDescription('For internal purposes only');
		$fields->replaceField('Event', Forms\DropdownField::create('Event','Event')
			->setSource($this->Config()->get('form_events')) );

		if (!$this->Exists())
		{
			$fields->addFieldToTab('Root.Conditions', Forms\HeaderField::create('_saveWarning','You must save before adding conditions'));
		}
		else
		{
			$fields->addFieldToTab('Root.Conditions', Forms\HeaderField::create('_mainTitle','Conditions',1) );
			$fields->addFieldToTab('Root.Conditions', Forms\HeaderField::create('_mainSubtitle','(All conditions must be met for action to apply)',2) );
			$fields->addFieldToTab('Root.Conditions', $fields_gf = Forms\GridField\GridField::create(
				'ConditionFields',
				'ConditionFields',
				$this->ConditionFields(),
				Forms\GridField\GridFieldConfig::create(100)
					->addComponent(new Forms\GridField\GridFieldButtonRow('before'))
					->addComponent(new Forms\GridField\GridFieldToolbarHeader())
					->addComponent(new GridFieldTitleHeader())
					->addComponent($editableColumns = new GridFieldEditableColumns())
					->addComponent(new Forms\GridField\GridFieldDeleteAction(true))
					->addComponent($searchButton = new GridFieldAddExistingSearchButton())
			));
			$searchButton->setTitle('Add Condition');
			$dataFields = $this->FormBuilder()->DataFields();
			$searchButton->setSearchList($dataFields);

			$editableColumns->setDisplayFields([
				'Name' => [
					'title' => 'Field',
					'field' => Forms\ReadonlyField::class
				],
				'State' => [
					'title' => 'State',
					'callback' => function($fieldRecord, $col, $grid) {
						if ($fieldRecord instanceof FormAction)
						{
							$states = ['Has Value' => 'Has Value', 'Match' => 'Match', 'Is Empty' => 'Is Empty'];
							return Forms\OptionsetField::create('State','Field State')
								->setSource($states);
						}
						if (!($fieldRecord instanceof \IQnection\FormBuilder\Model\Field))
						{
							$fieldRecord = $fieldRecord->Parent();
						}
						return $fieldRecord->ConditionOptionsField($this, '_ConditionFieldSelections');
					}
				]
			]);
		}

		return $fields;
	}

	public function getBetterButtonsActions()
	{
		$actions = parent::getBetterButtonsActions();
		$actions->removeByName(['action_doSaveAndAdd']);
		if (!$this->Exists())
		{
			$actions->fieldByName('action_save')->setTitle('Continue to Add Conditions');
		}
		return $actions;
	}

	public function ActionType()
	{
		return $this->singular_name();
	}

	public function onFormSubmit($form, $data, $submission) { }

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();
		$this->forceChange();
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		if (array_key_exists('_ConditionFieldSelections',$_REQUEST))
		{
			$linkedSelectionIDs = [];
			if (is_array($_REQUEST['_ConditionFieldSelections']))
			{
				foreach($_REQUEST['_ConditionFieldSelections'] as $fieldID => $selectionID)
				{
					if ($selection = SelectFieldOption::get()->byID($selectionID))
					{
						$this->ConditionFieldSelections()->add($selection);
						$linkedSelectionIDs[] = $selection->ID;
					}
				}
			}
			$remove = $this->ConditionFieldSelections();
			if (count($linkedSelectionIDs))
			{
				$remove = $remove->Exclude('ID',$linkedSelectionIDs);
			}
			if ($remove->Count())
			{
				$this->ConditionFieldSelections()->removeMany($remove->Column('ID'));
			}
		}
		$this->FormBuilder()->clearAllCache();
	}

	public function testConditions($submittedValues = [])
	{
		// defaults to true
		$result = true;
		// all conditions must be met
		foreach($this->ConditionFields() as $child)
		{
			if (!$this->testCondition($child->State, $child, $submittedValues))
			{
				$result = false;
				break;
			}
		}
		$this->extend('updateTestConditions', $result, $submittedValues);
		return $result;
	}

	protected function testCondition($state, $testField, $values = [])
	{
		$fieldName = $testField->getFrontendFieldName();
		$fieldValue = (array_key_exists($fieldName, $values)) ? $values[$fieldName] : null;
		switch($state)
		{
			case 'Has Value':
				if ( ($fieldValue !== '') && (!is_null($fieldValue)) )
				{
					return true;
				}
				break;
			case 'Is Empty':
				if ( ($fieldValue === '') || (is_null($fieldValue)) )
				{
					return true;
				}
				break;
			case 'Match':
				$selectionIds = $this->ConditionFieldSelections()->Column('ID');
				if (!is_array($fieldValue))
				{
					$fieldValue = [$fieldValue];
				}
				if ( (count($selectionIds)) && ($testField->hasExtension(SelectField::class)) )
				{
					$testOptions = $testField->Options()->byIds($selectionIds)->Column('ID');
					foreach($testOptions as $testOption)
					{
						if (in_array($testOption, $fieldValue))
						{
							return true;
						}
					}
				}
				break;
		}
		return false;
	}

	public function Explain()
	{
		$conditions = [];
		$text = '<div>Action: '.$this->singular_name().'</div>';
		foreach($this->ConditionFields() as $conditionField)
		{
			$condition = $conditionField->Name.' ';
			switch($conditionField->State)
			{
				default:
				case 'Has Value,Match,':
				case 'Is Empty':
					$condition .= ' '.$conditionField->State;
					break;
				case 'Match':
					$condition .= ' Has Selected: '.implode(', ',$conditionField->Options()->Filter('ID', $this->ConditionFieldSelections()->Column('ID'))->Column('Value'));
					break;
			}
			$conditions[] = $condition;
		}
		$this->extend('updateExplanation', $conditions);
		$text .= ' - Conditions: <ul><li>'.implode('</li><li>',$conditions).'</ul>';
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $text);
	}

	public function onBeforeDelete()
	{
		parent::onBeforeDelete();
		// remove relation links to the records aren't deleted
		$this->ConditionFields()->removeAll();
		$this->ConditionFieldSelections()->removeAll();
	}
}