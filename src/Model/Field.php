<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use IQnection\FormBuilder\FormBuilder;
use IQnection\FormBuilder\Model\FieldAction;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\ORM\FieldType;

class Field extends DataObject
{
	private static $table_name = 'FormBuilderField';
	private static $hide_ancestor = Field::class;
	
	private static $submission_value_class = SubmissionFieldValue::class;
	
	private static $db = [
		'Enable' => 'Boolean',
		'HideByDefault' => 'Boolean',
		'SortOrder' => 'Int',
		'Name' => 'Varchar(255)',
		'CssClasses' => 'Varchar(255)',
		'ShowInSubmissionsTable' => 'Boolean'
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
		'OwnerSelectionActions' => SelectFieldOptionAction::class.'.Children'
	];
	
	private static $defaults = [
		'Enable' => true
	];
	
	private static $summary_fields = [
		'Name' => 'Name',
		'FieldType' => 'Type',
		'Enable.Nice' => 'Displayed'
	];
	
	private static $default_sort = 'SortOrder ASC';
		
//	public function CanDelete($member = null, $context = []) { return false; }
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'SortOrder',
			'SubmissionValues',
			'FieldActions',
			'OwnerFieldActions',
			'OwnerSelectionActions'
		]);
//		$fields->insertBefore('Enable', Forms\HeaderField::create('_fieldType', 'Field Type: '.$this->singular_name(),1));
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
$fields->addFieldToTab('Root.Validation', Forms\LiteralField::create('_validation', '<div style="width:100%;overflow:scroll;"><pre><xmp>'.print_r(json_encode($actionsData, JSON_PRETTY_PRINT),1).'</xmp></pre></div>'));

		return $fields;
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
	
	public function validate()
	{
		$result = parent::validate();
		// preliminary check for duplicate field name
		$otherFields = Field::get()->Exclude('ID',$this->ID)->Filter('Name',$this->Name);
		if ($otherFields->Count())
		{
			// see if this form contains the field with the same name
			if ($otherField = $otherFields->Filter('ContainerID',$this->ContainerID)->First())
			{
				$result->addError('This field name is already used in this form on a '.$otherField->singular_name());
			}
		}
		return $result;
	}
	
	public function Explain()
	{
		$text = '<div style="padding-left:10px;"><strong>'.$this->Name.'</strong> ['.$this->singular_name();
		if ($this->HideByDefault)
		{
			$text .= '|Hidden';
		}
		$text .= ']';
		foreach($this->FieldActions() as $fieldAction)
		{
			$text .= $fieldAction->Explain();
		}
		$this->extend('updateExplanation', $text);
		$text .= '</div>';
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $text);
	}
	
	public function FieldType()
	{
		return $this->singular_name();
	}
	
	public function getBetterButtonsActions()
	{
		$actions = parent::getBetterButtonsActions();
		$actions->removeByName(['action_doSaveAndAdd']);
		return $actions;
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
		foreach($this->invokeWithExtensions('updateExtraCssClasses',$extraClasses) as $update)
		{
			$extraClasses = array_merge($extraClasses, $update);
		}
		$extraClasses = array_unique($extraClasses);
		return $as_string ? implode(' ',$extraClasses) : $extraClasses;
	}
	
	public function updateBaseField($field, $validator = null)
	{
		$extraClasses = $this->ExtraCssClasses();
		if (count($extraClasses))
		{
			$field->addExtraClass(implode(' ',$extraClasses));
		}
		$field->FormBuilderField = $this;
	}
	
	public function getBaseField(&$validator = null)
	{
		user_error('Method '.__FUNCTION__.' must be implemented on class '.$this->getClassName());
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
	
	public function prepareSubmittedValue($value)
	{
		$this->extend('updatePreparedSubmittedValue',$value);
		return $value;
	}
	
	public function createSubmissionFieldValue($value)
	{
		$rawValue = $value;
		$submissionFieldValue = Injector::inst()->create($this->Config()->get('submission_value_class'));
		$submissionFieldValue->FormBuilderFieldID = $this->ID;
		$submissionFieldValue->SortOrder = $this->SortOrder;
		$submissionFieldValue->Name = $this->Name;
		$submissionFieldValue->Label = $this->Label;
		$submissionFieldValue->Required = $this->Required;
		$submissionFieldValue->RawValue = serialize($rawValue);
		$submissionFieldValue->Value = $this->prepareSubmittedValue($rawValue);
		$this->extend('updateSubmissionFieldValue', $submissionFieldValue, $rawValue);
		return $submissionFieldValue;
	}
	
	public function processFormData(&$data, $form, $request, &$response)
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








