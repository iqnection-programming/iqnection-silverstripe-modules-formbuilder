<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms;
use IQnection\FormBuilder\Model\Field;
use SilverStripe\Core\ClassInfo;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use SwiftDevLabs\DuplicateDataObject\Forms\GridField\GridFieldDuplicateAction;
use IQnection\FormBuilder\Extensions\Cacheable;

class FieldGroupExtension extends DataExtension
{
	private static $extensions = [
		Cacheable::class
	];

	private static $has_many = [
		'Fields' => Field::class
	];

	private static $cascade_duplicates = [
		'Fields'
	];

	private static $cascade_caches = [
		'Fields'
	];

	private static $max_fields;
	private static $hide_field_types = [];

	public function updateCMSFields($fields)
	{
		$fields->removeByName([
			'Fields',
			'Actions'
		]);
		if (!$this->owner->Exists())
		{
			$fields->addFieldToTab('Root.FormFields', Forms\HeaderField::create('_fieldsWarning','You must save before adding fields'));
		}
		else
		{
			$fields->addFieldToTab('Root.FormFields', Forms\GridField\GridField::create(
				'Fields',
				'Fields',
				$this->owner->Fields(),
				Forms\GridField\GridFieldConfig_RecordEditor::create(100)
					->addComponent(new GridFieldOrderableRows('SortOrder'))
					->addComponent($GridFieldAddNewMultiClass = new GridFieldAddNewMultiClass())
					->removeComponentsByType(Forms\GridField\GridFieldAddNewButton::class)
					->addComponent(new GridFieldDuplicateAction())
			));
			$GridFieldAddNewMultiClass->setTitle('Add Field');
		}
		return $fields;
	}

	public function updateBetterButtonsActions($actions)
	{
		if (!$this->owner->Exists())
		{
			$actions->removeByName(['action_doSaveAndQuit','action_doSaveAndAdd']);
			$actions->fieldByName('action_save')->setTitle('Continue');
		}
	}

	public function updateHasActions(&$hasActions)
	{
		if (!$hasActions)
		{
			foreach($this->owner->Fields() as $field)
			{
				if ($hasActions = $field->hasActions())
				{
					return $hasActions;
				}
			}
		}
	}

	public function updateExplanation(&$text)
	{
		foreach($this->owner->Fields() as $field)
		{
			$text .= $field->Explain();
		}
	}

	public function CanAddField()
	{
		if ($max_fields = $this->owner->Config()->get('max_fields'))
		{
			return $this->owner->Fields()->Count() < $max_fields;
		}
		return true;
	}

	/**
	 * returns all data fields owned by this group or any child groups
	 */
	public function DataFields()
	{
		$fieldIDs = [0];
		foreach($this->owner->Fields() as $field)
		{
			if ($field->hasExtension(FieldGroupExtension::class))
			{
				$fieldIDs = array_merge($fieldIDs, $field->DataFields()->Column('ID'));
			}
			elseif (!$field->hasExtension(DatalessField::class))
			{
				$fieldIDs[] = $field->ID;
			}
		}
		$dataFields = Field::get()->byIDs($fieldIDs);
		return $dataFields;
	}

	/**
	 * returns all fields owned by this group or any child groups
	 */
	public function FieldsFlat()
	{
		$fieldIDs = [0];
		foreach($this->owner->Fields() as $field)
		{
			$fieldIDs[] = $field->ID;
			if ($field->hasExtension(FieldGroupExtension::class))
			{
				$fieldIDs = array_merge($fieldIDs, $field->FieldsFlat()->Column('ID'));
			}
		}
		$Fields = Field::get()->byIDs($fieldIDs);
		return $Fields;
	}

	public function generateFormFields($validator, $defaults)
	{
		$fields = [];
		foreach($this->owner->Fields()->Filter('Enable',1) as $field)
		{
			if ($baseField = $field->getBaseField($validator, $defaults))
			{
				$field->invokeWithExtensions('updateBaseField', $baseField, $validator, $defaults);
				$fields[] = $baseField;
			}
		}
		return $fields;
	}
}