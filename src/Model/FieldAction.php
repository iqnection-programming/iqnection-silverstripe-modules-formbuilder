<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\Model\Field;
use IQnection\FormBuilder\Fields\CheckboxField;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms;
use IQnection\FormBuilder\Extensions\SelectField;
use SilverStripe\ORM\FieldType;

class FieldAction extends DataObject
{
	private static $table_name = 'FormBuilderFieldAction';
	private static $singular_name = 'Action';
	private static $plural_name = 'Actions';
	private static $hide_ancestor = FieldAction::class;

	private static $db = [
	];

	private static $has_one = [
		'Parent' => Field::class
	];

	private static $many_many = [
		'Children' => Field::class,
		'ChildSelections' => SelectFieldOption::class
	];

	private static $many_many_extraFields = [
		'Children' => [
			'State' => "Enum('Has Value,Match,Is Empty','Has Value')",
		]
	];

	private static $summary_fields = [
		'ActionType' => 'Type',
		'Explain' => 'Conditions'
	];

	private static $casting = [
		'Explain' => 'HTMLFragment'
	];

	private static $allowed_field_types;

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'ParentID',
			'Children',
			'ChildSelections'
		]);
		$fields->unshift( Forms\HeaderField::create('_actionType', 'Action: '.$this->singular_name(),1));
		if (!$this->Exists())
		{
			$fields->addFieldToTab('Root.Main', Forms\HeaderField::create('_saveWarning','You must save before adding conditions'));
		}
		else
		{
			$fields->addFieldToTab('Root.Main', Forms\HeaderField::create('_mainTitle','Conditions',1) );
			$fields->addFieldToTab('Root.Main', Forms\HeaderField::create('_mainSubtitle','(All conditions must be met for action to apply)',2) );
			$fields->addFieldToTab('Root.Main', $fields_gf = Forms\GridField\GridField::create(
				'Children',
				'Children',
				$this->Children(),
				Forms\GridField\GridFieldConfig::create(100)
					->addComponent(new Forms\GridField\GridFieldButtonRow('before'))
					->addComponent(new Forms\GridField\GridFieldToolbarHeader())
					->addComponent(new GridFieldTitleHeader())
					->addComponent($editableColumns = new GridFieldEditableColumns())
					->addComponent(new Forms\GridField\GridFieldDeleteAction())
					->addComponent($searchButton = new GridFieldAddExistingSearchButton())
			));
			$searchButton->setTitle('Add Condition');
			$dataFields = $this->Parent()->FormBuilder()->DataFields();
			if ($dataFields->Count())
			{
				$dataFields = $dataFields->Exclude('ID',$this->Parent()->ID);
			}
			$searchButton->setSearchList($dataFields);

			$editableColumns->setDisplayFields([
				'Name' => [
					'title' => 'Field',
					'field' => Forms\ReadonlyField::class
				],
				'State' => [
					'title' => 'Field',
					'callback' => function($record, $col, $grid) {
						if ($record->hasExtension(SelectField::class))
						{
							$source = [];
							foreach($record->Options() as $option)
							{
								$source[$option->ID] = $option->getOptionLabel();
							}
							return Forms\SelectionGroup::create('State', [
								Forms\SelectionGroup_Item::create('Has Value', null, 'Any selected'),
								Forms\SelectionGroup_Item::create(
									'Match',
									Forms\CheckboxSetField::create('_ChildSelections','Options')
										->setSource($source)
										->setDefaultItems($this->ChildSelections()->Column('ID')),
									'Specified selected (when the user chooses any below selected values, this action will be triggered)'),
								Forms\SelectionGroup_Item::create('Is Empty', null, 'Non selected'),
							]);
						}
						$states = ['Has Value' => 'Has Value', 'Is Empty' => 'Is Empty'];
						if ($record instanceof CheckboxField)
						{
							$states = ['Has Value' => 'Is Checked', 'Is Empty' => 'Not Checked'];
						}
						return Forms\OptionsetField::create('State','Field State')
							->setSource($states);
					}
				]
			]);

//			$watchedFieldIDs = $this->Children()->Column('ID');
//			if (count($watchedFieldIDs))
//			{
//				foreach(Field::get()->byIDs($watchedFieldIDs) as $dataField)
//				{
//					if ($dataField->hasExtension(SelectField::class))
//					{
//						$fields->addFieldToTab('Root.Field Selections', Forms\CheckboxsetField::create('_ChildSelections['.$dataField->ID.']',$dataField->Name)
//							->setSource($dataField->Options()->map('ID','getOptionLabel'))
//							->setDefaultItems($this->ChildSelections()->Column('ID')));
//					}
//				}
//			}
		}
$fields->addFieldToTab('Root.Validation', Forms\LiteralField::create('_validation', '<div style="width:100%;overflow:scroll;"><pre><xmp>'.print_r(json_encode($this->getActionData(), JSON_PRETTY_PRINT),1).'</xmp></pre></div>'));
		return $fields;
	}

	public function isFieldTypeAllowed($fieldType)
	{
		if (is_object($fieldType))
		{
			$fieldType = get_class($fieldType);
		}
		$allowedTypes = $this->Config()->get('allowed_field_types');
		if (is_array($allowedTypes))
		{
			if (in_array($fieldType, $allowedTypes))
			{
				return true;
			}
			// see if an extension was declared
			foreach($allowedTypes as $allowedType)
			{
				if ( (class_exists($allowedType)) && ($fieldType::has_extension($allowedType)) )
				{
					return true;
				}
			}
			return false;
		}
		return true;
	}

	public function FormBuilder()
	{
		return $this->Parent()->FormBuilder();
	}

	public function getTitle()
	{
		return $this->singular_name();
	}

	public function testConditions($submittedValues = [])
	{
		$result = true;
		// all conditions must be met

		foreach($this->Children() as $child)
		{
			if (!$this->testCondition($child->State, $child, $submittedValues))
			{
				$result = false;
				break;
			}
		}
		$this->extend('updateTestResult', $result, $submittedValues);
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
				$selectionIds = $this->ChildSelections()->Column('ID');
				if ( (is_array($fieldValue)) && (count($selectionIds)) && ($testField->hasExtension(SelectField::class)) )
				{
					foreach($testField->Options()->byIds($selectionIds)->Column('ID') as $testOption)
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

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		if (array_key_exists('_ChildSelections',$_REQUEST))
		{
			$linkedSelectionIDs = [];
			if (is_array($_REQUEST['_ChildSelections']))
			{
				foreach($_REQUEST['_ChildSelections'] as $fieldID => $selectionID)
				{
					if ($selection = SelectFieldOption::get()->byID($selectionID))
					{
						$this->ChildSelections()->add($selection);
						$linkedSelectionIDs[] = $selection->ID;
					}
				}
			}
			$remove = $this->ChildSelections();
			if (count($linkedSelectionIDs))
			{
				$remove = $remove->Exclude('ID',$linkedSelectionIDs);
			}
			if ($remove->Count())
			{
				$this->ChildSelections()->removeMany($remove->Column('ID'));
			}
		}
		$this->FormBuilder()->clearJsCache();
	}

	public function getBetterButtonsActions()
	{
		$actions = parent::getBetterButtonsActions();
		$actions->removeByName(['action_doSaveAndAdd']);
		if (!$this->Exists())
		{
			$actions->removeByName(['action_doSaveAndQuit']);
			$actions->fieldByName('action_save')->setTitle('Continue');
		}
		return $actions;
	}

	public function Explain()
	{
		$conditions = [];
		$text = '<div>Action: '.$this->singular_name().'</div>';
		foreach($this->Children() as $conditionField)
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
					$condition .= ' Has Selected: '.implode(', ',$conditionField->Options()->Filter('ID', $this->ChildSelections()->Column('ID'))->Column('Value'));
					break;
			}
			$conditions[] = $condition;
		}
		$this->extend('updateExplanation', $conditions);
		$text .= ' - Conditions: <ul><li>'.implode('</li><li>',$conditions).'</ul>';
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $text);
	}

	public function getActionData()
	{
		$js= [
			'id' => $this->ID,
			'name' => 'Field: '.$this->Parent()->Name,
			'action' => [
				'type' => $this->ActionType(),
				'selector' => $this->Parent()->getjQuerySelector(),
				'fieldType' => $this->Parent()->singular_name(),
				'callback' => null,
			],
			'conditions' => [],
			'conditionsHash' => null
		];
		foreach($this->Children() as $child)
		{
			$fieldJs = [
				'selector' => $child->getjQuerySelector(),
				'state' => $child->State,
				'stateCallback' => 'state'.preg_replace('/[^a-zA-Z]/','',$child->State),
				'selections' => [],
			];
			if ($child->hasExtension(SelectField::class))
			{
				foreach($this->ChildSelections()->Filter('FieldID',$child->ID) as $childFieldSelection)
				{
					$fieldJs['selections'][] = [
						'selector' => $child->getOptionjQuerySelector($childFieldSelection),
						'value' => $childFieldSelection->ID,
						'label' => (string) $childFieldSelection->getOptionLabel()
					];
				}
			}
			$js['conditions'][] = $fieldJs;
			$js['conditionsHash'] = md5(json_encode($fieldJs));
		}
		return $js;
	}

	public function ActionType()
	{
		return $this->singular_name();
	}
}