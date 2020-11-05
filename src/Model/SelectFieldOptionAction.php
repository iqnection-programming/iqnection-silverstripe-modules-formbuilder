<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\Model\Field;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms;
use IQnection\FormBuilder\Extensions\SelectField;
use SilverStripe\ORM\FieldType;

class SelectFieldOptionAction extends DataObject
{
	private static $table_name = 'FormBuilderSelectFieldOptionAction';
	private static $singular_name = 'Action';
	private static $plural_name = 'Actions';
	private static $hide_ancestor = SelectFieldOptionAction::class;
	
	private static $db = [
	];
	
	private static $has_one = [
		'Parent' => SelectFieldOption::class
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
			$fields->addFieldToTab('Root.Main', Forms\HeaderField::create('_saveWarning','You must save first'));
		}
		else
		{
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
			$searchButton->setTitle('Add Field');
			$searchButton->setSearchList($this->Parent()->Field()->FormBuilder()->DataFields());
			
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
							// is the field hidden by default
							return Forms\SelectionGroup::create('State', [
								Forms\SelectionGroup_Item::create('Has Value', null, 'Any selection'),
								Forms\SelectionGroup_Item::create(
									'Match', 
									Forms\CheckboxSetField::create('_ChildSelections','Options')
										->setSource($source)
										->setDefaultItems($this->ChildSelections()->Column('ID')),
									'Specified selected (when the user chooses any below selected values, this action will be triggered)'),
								Forms\SelectionGroup_Item::create('Is Empty', null, 'No selection'),
							]);
						}
						$states = ['Has Value' => 'Has Value', 'Is Empty' => 'Is Empty'];
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
$fields->addFieldToTab('Root.Validation', Forms\LiteralField::create('_validation', '<div style="width:100%;overflow:scroll;"><pre><xmp>'.print_r($this->getActionData(),1).'</xmp></pre></div>'));
		return $fields;
	}
	
	public function FormBuilder()
	{
		return $this->Parent()->FormBuilder();
	}
	
	public function getTitle()
	{
		return $this->singular_name();
	}
	
	public function test()
	{
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
			$condition = '<strong>'.$conditionField->Name.'</strong>';
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
		$js = [
			'id' => $this->ID,
			'name' => $this->Parent()->Field()->Name. ' Option: '.$this->Parent()->Value,
			'action' => [
				'type' => $this->ActionType(),
				'selector' => $this->Parent()->getjQuerySelector(true),
				'callback' => null,
				'fieldType' => $this->Parent()->Field()->singular_name(),
				'fieldSelector' => $this->Parent()->Field()->getjQuerySelector(false),
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
						'selector' => $childFieldSelection->getjQuerySelector(),
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