<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use IQnection\FormBuilder\FormBuilder;
use IQnection\FormBuilder\Model\FieldAction;
use IQnection\FormBuilder\Model\FormAction;
use IQnection\FormBuilder\Fields\FieldGroup;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\ORM\FieldType;
use IQnection\FormBuilder\Actions\ToggleDisplayFieldAction;
use IQnection\FormBuilder\Extensions\Duplicable;

class Field extends DataObject
{
	private static $table_name = 'FormBuilderField';
	private static $hide_ancestor = Field::class;

	private static $submission_value_class = SubmissionFieldValue::class;

	private static $extensions = [
		Duplicable::class
	];

	private static $db = [
		'Enable' => 'Boolean',
		'HideByDefault' => 'Boolean',
		'SortOrder' => 'Int',
		'Name' => 'Varchar(255)',
		'CssClasses' => 'Varchar(255)',
		'ShowInSubmissionsTable' => 'Boolean',
	];

	private static $has_one = [
		'Container' => DataObject::class
	];

	private static $has_many = [
		'SubmissionValues' => SubmissionFieldValue::class,
		'FieldActions' => FieldAction::class.'.Parent'
	];

	private static $belongs_many_many = [
		'OwnerFieldActions' => FieldAction::class.'.Children',
		'OwnerSelectionActions' => SelectFieldOptionAction::class.'.Children',
		'OwnerFormActions' => FormAction::class.'.ConditionFields',
	];

	private static $defaults = [
		'Enable' => true
	];

	private static $summary_fields = [
		'getGridFieldName' => 'Name',
		'FieldType' => 'Type',
		'EnableDisplay' => 'Enabled',
		'ShowInSubmissionsTableDisplay' => 'Submissions Table'
	];

	private static $default_sort = 'SortOrder ASC';

	private static $form_builder_has_many_duplicates = [
		'FieldActions'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'SortOrder',
			'SubmissionValues',
			'FieldActions',
			'OwnerFieldActions',
			'OwnerSelectionActions',
			'OwnerFormActions'
		]);

		$fields->unshift( Forms\HeaderField::create('_fieldType', 'Field Type: '.$this->singular_name(),1));
		$fields->dataFieldByName('Name')->setDescription('This is will be used in notifications, and as the column title on exports')
			->setTitle('Field Name');
		$fields->addFieldToTab('Root.Settings', Forms\CheckboxField::create('Enable','Activate this field'));
		$fields->addFieldToTab('Root.Settings', Forms\CheckboxField::create('ShowInSubmissionsTable','Display this field in the submissions table') );
		$fields->addFieldToTab('Root.Settings', $fields->dataFieldByName('CssClasses')->setDescription('For Developer Use') );
		$fields->addFieldToTab('Root.Settings', Forms\CheckboxField::create('HideByDefault','Hide this field by default'));


		$allowedFieldActions = $this->getAllowedFieldActions();
		if (count($allowedFieldActions))
		{
			if (!$this->Exists())
			{
				$fields->addFieldToTab('Root.Actions', Forms\HeaderField::create('_displayText','You can add actions after you save',2));
			}
			else
			{
				$fields->addFieldToTab('Root.Actions', Forms\HeaderField::create('_displayText','Select and add an action, then set the conditions',2));
				$fields->addFieldToTab('Root.Actions', Forms\GridField\GridField::create(
					'FieldActions',
					'Field Actions',
					$this->FieldActions(),
					Forms\GridField\GridFieldConfig_RecordEditor::create(100)
						->addComponent($GridFieldAddNewMultiClass = new GridFieldAddNewMultiClass())
						->removeComponentsByType(Forms\GridField\GridFieldAddNewButton::class)
				));
				$GridFieldAddNewMultiClass->setTitle('Add Action');
				$GridFieldAddNewMultiClass->setClasses($allowedFieldActions);
			}
		}

		$actionsData = [];
		foreach($this->FieldActions() as $fieldAction)
		{
			$actionsData[] = $fieldAction->getActionData();
		}

		return $fields;
	}

	public function onBeforeDuplicate($original, $doWrite, $relations)
	{
		$this->Name = $name = 'Copy of '.$original->Name;
	}

	public function ConditionOptionsField(&$fieldAction, $fieldName = null)
	{
		$field = Forms\SelectionGroup::create('State', []);
		$this->extend('updateConditionOptions', $field, $fieldAction, $fieldName);
		return $field;
	}

	public function EnableDisplay()
	{
		return $this->dbObject('Enable')->Nice();
	}

	public function ShowInSubmissionsTableDisplay()
	{
		return $this->dbObject('ShowInSubmissionsTable')->Nice();
	}

	public function getGridFieldName()
	{
		$name = $this->Name;
		$this->extend('updateGridFieldName', $name);
		return $name;
	}

	public function getAllowedFieldActions()
	{
		$allowedActions = [];
		foreach(ClassInfo::subclassesFor(FieldAction::class) as $fieldActionClass)
		{
			if (singleton($fieldActionClass)->isFieldTypeAllowed($this))
			{
				$allowedActions[] = $fieldActionClass;
			}
		}
		$this->extend('updateAllowedActions', $allowedActions);
		return $allowedActions;
	}

	public function isHidden($formData)
	{
		$hidden = $this->HideByDefault;
		// if this field has actions, check to see if the field is hidden based on conditions
		foreach($this->FieldActions() as $fieldAction)
		{
			if ( ($fieldAction instanceof ToggleDisplayFieldAction) && ($fieldAction->testConditions($formData)) )
			{
				// conditions are true, field is toggles
				$hidden = !$hidden;
			}
		}
		return $hidden;
	}

	public function updateFrontEndValidator(&$validator, $formData = [])
	{
		if ($isRequired = $this->Required)
		{
			// is the field in a FieldGroup, and is the field group hidden
			if ( ($this->Container() instanceof FieldGroup) && ($this->Container()->isHidden($formData)) )
			{
				return;
			}

			// if this field is NOT hidden, set requirement
			if (!$this->isHidden($formData))
			{
				$validator->addRequiredField($this->getFrontendFieldName());
			}
		}
	}

	public function getOnLoadFieldActions($onLoadCondition = null)
	{
		$actions = [];
		$fieldSelector = $this->getjQuerySelector();
		if ($this->HideByDefault)
		{
			$actions[] = [
				'id' => $this->ID.'.1',
				'name' => 'Field: '.$this->Name,
				'action' => [
					'type' => 'Hidden on Load',
					'selector' => $fieldSelector,
					'fieldType' => $this->singular_name(),
					'callback' => 'actionHideField',
				],
				'conditions' => [$onLoadCondition],
				'conditionsHash' => 'onFormLoad'
			];
		}
		$this->extend('updateOnLoadFieldActions', $actions);
		return $actions;
	}

	public function validate()
	{
		$result = parent::validate();
		if (!trim($this->Name))
		{
			$result->addFieldError('Name','A Field Name is required');
		}
		// preliminary check for duplicate field name
		$otherFields = Field::get()->Exclude('ID',$this->ID)->Filter('Name',$this->Name);
		if ($otherFields->Count())
		{
			// see if this form contains the field with the same name
			if ($otherField = $otherFields->Filter('ContainerID',$this->ContainerID)->First())
			{
				$result->addError('Field name "'.$this->Title.'" is already used in this form on a '.$otherField->singular_name());
			}
		}
		return $result;
	}

	public function hasActions()
	{
		if ($this->FieldActions()->Count())
		{
			return true;
		}
		$this->extend('updateHasActions', $hasActions);
		return $hasActions;
	}

	public function Explain()
	{
		$text = '<div><strong>'.$this->Name.'</strong><div><div>'.$this->singular_name().'</div>';
		if ($this->HideByDefault)
		{
			$text .= '<em>(Default Hidden)</em>';
		}
		if ($this->FieldActions()->Count())
		{
			$text .= '<ul>';
			foreach($this->FieldActions() as $fieldAction)
			{
				$text .= '<li>'.$fieldAction->Explain().'</li>';
			}
			$text .= '</ul>';
		}
		$this->extend('updateExplanation', $text);
		$text .= '<hr>';
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $text);
	}

	public function FieldType()
	{
		$fieldType = $this->singular_name();
		$this->extend('updateFieldTypeName', $fieldType);
		return $fieldType;
	}

	public function getBetterButtonsActions()
	{
		$actions = parent::getBetterButtonsActions();
		$actions->removeByName(['action_doSaveAndAdd']);
		return $actions;
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		$this->FormBuilder()->clearAllCache();
	}

	public function FormBuilder()
	{
		if ( ($parent = $this->Container()) && ($parent->Exists()) )
		{
			if ($parent instanceof FormBuilder)
			{
				return $parent;
			}
			if ($parent->hasMethod('FormBuilder'))
			{
				return $parent->FormBuilder();
			}
		}
		return FormBuilder::singleton();
	}

	public function getBetterButtonsUtils()
    {
        $buttons = parent::getBetterButtonsUtils();
		$buttons->removeByName([
			'action_doNew'
		]);
        return $buttons;
    }

	public function getFrontendFieldName()
	{
		$name = 'FormBuilderField_'.$this->ID;
		$this->extend('updateFrontendFieldName',$name);
		return $name;
	}

	public function getFrontendFieldID()
	{
		$htmlid = $this->FormBuilder()->getFormHTMLID().'_'.$this->getFrontendFieldName();
		return $htmlid;
	}

	public function ExtraCssClasses($as_string = false)
	{
		$extraClasses = explode(',',$this->CssClasses);
		$extraClasses[] = 'form-builder-'.strtolower(Convert::raw2htmlid(ClassInfo::shortName($this->getClassName())));
		$extraClasses[] = 'form-builder-field-'.$this->ID;
		foreach($this->invokeWithExtensions('updateExtraCssClasses',$extraClasses) as $update)
		{
			$extraClasses = array_merge($extraClasses, $update);
		}
		$extraClasses = array_unique($extraClasses);
		return $as_string ? implode(' ',$extraClasses) : $extraClasses;
	}

	public function updateBaseField($field, &$validator = null, $defaults = null)
	{
		$extraClasses = $this->ExtraCssClasses();
		if (count($extraClasses))
		{
			$field->addExtraClass(implode(' ',$extraClasses));
		}
		$field->setAttribute('data-form-builder-name', $this->Name);
		$field->FormBuilderField = $this;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$field = false;
		$this->extend('updateBaseField', $field, $validator, $defaults);
		if (!$field)
		{
			user_error('Method '.__FUNCTION__.' must be implemented on class '.$this->getClassName());
		}
		return $field;
	}

	public function scaffoldSearchFields($params = null)
	{
		return Forms\FieldList::create();
	}

	protected $_fieldTypes = [];
	public function getFieldTypes()
	{
		if (is_null($this->_fieldTypes))
		{
			$this->_fieldTypes = [];
			foreach(ClassInfo::subclassesFor(Field::class, false) as $subClass)
			{
				$this->_fieldTypes[$subClass] = $subClass::singleton()->singular_name();
			}
		}
		return $this->_fieldTypes;
	}

	public function validateFormValue($value)
	{
		$errors = [];
		return $errors;
	}

	public function prepareSubmittedValue($value, $formData = [])
	{
		$this->extend('updatePreparedSubmittedValue',$value, $formData);
		return $value;
	}

	public function createSubmissionFieldValue($value, $formData = [])
	{
		$rawValue = $value;
		$submissionFieldValue = Injector::inst()->create($this->Config()->get('submission_value_class'));
		$submissionFieldValue->FormBuilderFieldID = $this->ID;
		$sortOrder = $this->SortOrder;
		if ( ($this->Container()->Exists()) && ($this->Container() instanceof Field) )
		{
			$sortOrder = ceil($this->Container()->SortOrder) + ($sortOrder * 0.1);
		}
		$submissionFieldValue->SortOrder = $sortOrder;
		$submissionFieldValue->Name = $this->Name;
		$submissionFieldValue->Label = $this->Label;
		$submissionFieldValue->Required = $this->Required;
		$submissionFieldValue->RawValue = serialize($rawValue);
		$submissionFieldValue->Value = $this->prepareSubmittedValue($rawValue, $formData);
		$this->extend('updateSubmissionFieldValue', $submissionFieldValue, $rawValue, $formData);
		return $submissionFieldValue;
	}

	public function processFormData(&$data, &$form, &$controller)
	{ }

	public function handleEvent($event, $form, $data, $submission)
	{ }

	/**
	 * builds an array of rules and/or messages to provide to jQuery.validate for building validation
	 * values of "rule" and "message" should be formated with keys and values
	 * that are acceptable to pass to jQuery.validate as a JSON object
	 * @returns array
	 */
	public function getFieldJsValidation()
	{
		$rules = [];
		$this->extend('updateFieldJsValidation', $rules);
		return $rules;
	}

	/**
	 * builds an array of messages to provide to jQuery.validate for building validation
	 * values of "messages" should be formated with keys and values
	 * that are acceptable to pass to jQuery.validate as a JSON object
	 * @returns array
	 */
	public function getFieldJsMessages()
	{
		$messages = [];
		$this->extend('updateFieldJsMessages', $messages);
		return $messages;
	}

	public function getJavaScriptValidatorName()
	{
		return $this->getFrontendFieldName();
	}

	public function getjQuerySelector()
	{
		$selector = '[name="'.$this->getFrontendFieldName().'"]';
		$this->extend('updatejQuerySelector', $selector);
		return $selector;
	}
}









