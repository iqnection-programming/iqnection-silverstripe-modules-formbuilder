<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use IQnection\FormBuilder\Model\SelectFieldOption;
use SilverStripe\Forms;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use IQnection\FormBuilder\Extensions\Cacheable;
use SilverStripe\Core\Injector\Injector;
use IQnection\FormUtilities\FormUtilities;
use SilverStripe\ORM\FieldType;

class SelectField extends DataExtension
{	
	private static $db = [
		'Label' => 'Varchar(255)',
		'Required' => 'Boolean',
		'Description' => 'Varchar(255)',
	];
	
	private static $has_many = [
		'Options' => SelectFieldOption::class
	];
	
	private static $cascade_duplicates = [
		'Options'
	];
	
	private static $cascade_caches = [
		'Options'
	];
	
	private static $prepopulate = [
		'prepopulateStates' => 'US States',
		'prepopulateCaProvinces' => 'Canada Provinces',
		'prepopulateCountries' => 'Countries'
	];
	
	private static $select_options_class = \IQnection\FormBuilder\Model\SelectFieldOption::class;
	
	public function updateCMSFields($fields)
	{
		$fields->dataFieldByName('Label')->setDescription('(Optional) Defaults to the field name. This will display as the field label on the form');
		$fields->dataFieldByName('Required')->setTitle('This is a Required Field');
		$fields->dataFieldByName('Description')->setDescription('(Optional) Small text to display under the field as a description');
		
		$fields->addFieldToTab('Root.Options', Forms\DropdownField::create('_prepopulate','Add Predefined Values')
			->setSource($this->owner->Config()->get('prepopulate'))
			->setEmptyString(' ')
			->setValue(false) );
		$fields->addFieldToTab('Root.Options', $options_gf = Forms\GridField\GridField::create(
			'Options',
			'Options',
			$this->owner->Options(),
			Forms\GridField\GridFieldConfig::create(100)
				->addComponent(new Forms\GridField\GridFieldButtonRow('before'))
				->addComponent(new Forms\GridField\GridFieldToolbarHeader())
				->addComponent(new Forms\GridField\GridFieldDetailForm())
				->addComponent(new GridFieldTitleHeader())
				->addComponent($editableColumns = new GridFieldEditableColumns())
				->addComponent(new Forms\GridField\GridFieldDeleteAction())
				->addComponent(new Forms\GridField\GridFieldEditButton())
				->addComponent(new GridFieldAddNewInlineButton())
				->addComponent(new GridFieldOrderableRows('SortOrder'))
				->removeComponentsByType(Forms\GridField\GridFieldAddNewButton::class)
				->removeComponentsByType(Forms\GridField\GridFieldAddExistingAutocompleter::class)
		));
		$options_gf->setModelClass($this->owner->Config()->get('select_options_class'));
		$editableColumns->setDisplayFields([
			'ID' => [
				'title' => 'ID',
				'field' => Forms\ReadonlyField::class
			],
			'DefaultSelected' => [
				'title' => 'Selected by Default',
				'field' => Forms\CheckboxField::class
			],
			'Value' => [
				'title' => 'Value',
				'field' => Forms\TextField::class
			],
			'Label' => [
				'title' => 'Label',
				'field' => Forms\TextField::class
			],
		]);
		return $fields;
	}
	
	public function onBeforeWrite()
	{
		$this->prepopulateCall = $_REQUEST['_prepopulate'];
		$_REQUEST['_prepopulate'] = null;
	}
	
	public function onAfterWrite()
	{
		if (isset($this->prepopulateCall))
		{
			if ( (array_key_exists($this->prepopulateCall,$this->owner->Config()->get('prepopulate'))) && ($this->owner->hasMethod($this->prepopulateCall)) )
			{
				$this->owner->{$this->prepopulateCall}();
			}
		}
	}
	
	public function updateExplanation(&$text)
	{
		foreach($this->owner->Options() as $Option)
		{
			$text .= $Option->Explain();
		}
	}
	
	public function updateStatusOptionsField(&$field)
	{
		$options = [];
		foreach($this->owner->Options() as $option)
		{
			$options[$option->ID] = '"'.$option->getOptionLabel().'" is selected';
		}
		$field = Forms\CheckboxSetField::create('FieldStatus','Status')
			->setSource($options);
		return $field;
	}
	
	public function prepopulateStates()
	{
		$values = FormUtilities::GetStates();
		return $this->owner->prepopulateValues($values);
	}
	
	public function prepopulateCaProvinces()
	{
		$values = FormUtilities::GetCanadianProvinces();
		return $this->owner->prepopulateValues($values);
	}
	
	public function prepopulateCountries()
	{
		$values = FormUtilities::GetCountries();
		return $this->owner->prepopulateValues($values);
	}
	
	public function prepopulateValues($values)
	{
		$currentOptions = $this->owner->Options();
		$count = $currentOptions->Count();
		foreach($values as $value => $label)
		{
			if (!$currentOptions->FilterAny(['Value' => $value, 'Label' => $label])->Count())
			{
				$count++;
				$option = Injector::inst()->create($this->owner->Config()->get('select_options_class'), [
					'Value' => $value, 
					'Label' => $label, 
					'SortOrder' => $count,
					'FieldID' => $this->owner->ID
				]);
				$option->write();
			}
		}
		return $this;
	}
	
//	public function updateBetterButtonsActions($actions)
//	{
//		if (!$this->owner->Exists())
//		{
//			$actions->removeByName(['action_doSaveAndQuit','action_doSaveAndAdd']);
//			$actions->fieldByName('action_save')->setTitle('Continue');
//		}
//	}
	
	public function getFieldSourceArray()
	{
		$source = [];
		foreach($this->owner->Options() as $option)
		{
			$source[$option->ID] = $option->getOptionLabel();
		}
		$this->owner->extend('updateFieldSourceArray', $source);
		return $source;
	}
	
	public function updateBaseField(&$field, &$validator)
	{
		if ($this->owner->Required)
		{
			$validator->addRequiredField($this->owner->getFrontendFieldName());
			$field->addExtraClass('required');
		}
		$label = ($this->owner->Label) ? $this->owner->Label : $this->owner->Name;
		$field->setTitle(FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $label));
		if ($this->owner->Description)
		{
			$field->setDescription($this->owner->Description);
		}
	}
	
	public function updatePreparedSubmittedValue(&$value)
	{
		if (!is_array($value))
		{
			$value = [$value];
		}
		$actualValues = [];
		foreach($value as $selectionID)
		{
			if ($selectedValue = $this->owner->Options()->byID($selectionID))
			{
				$actualValues[] = $selectedValue->Value;
			}
		}
		$value = implode(', ',$actualValues);
	}
	
	public function isMultiple()
	{
		return false;
	}
	
	public function updateFieldJsValidation(&$js)
	{
		if ($this->owner->Required)
		{
			$js['required'] = true;
		}
	}
	
	public function updateActionsJs(&$js)
	{
		foreach($this->owner->Options() as $option)
		{
			$ActionsJs = $option->getActionsJs();
			if (!empty($ActionsJs))
			{
				foreach($ActionsJs as $ActionJs)
				{
					$js[] = $ActionJs;
				}
			}
		}
	}
	
	public function getOptionjQuerySelector($option, $valueSelector = false)
	{
		return $this->owner->getjQuerySelector();
	}
}