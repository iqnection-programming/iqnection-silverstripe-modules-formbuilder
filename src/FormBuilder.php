<?php

namespace IQnection\FormBuilder;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms;
use IQnection\FormBuilder\Extensions\FieldGroupExtension;
use IQnection\FormBuilder\Extensions\SelectField;
use IQnection\FormBuilder\Shortcode\ShortcodeParser;
use IQnection\FormBuilder\Control\FormHandler;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\Requirements;
use IQnection\FormBuilder\Forms\Validator;
use IQnection\FormBuilder\Model\FormAction;
use IQnection\FormBuilder\Model\Submission;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use SwiftDevLabs\DuplicateDataObject\Forms\GridField\GridFieldDuplicateAction;
use IQnection\FormBuilder\Control\FormBuilderPreview;
use IQnection\FormBuilder\Forms\GridField\SubmissionsExportButton;
use IQnection\FormBuilder\Extensions\Cacheable;
use IQnection\FormBuilder\Cache\Cache;
use SilverStripe\ORM\FieldType;

class FormBuilder extends DataObject
{
	private static $table_name = 'FormBuilder';
	private static $singular_name = 'Form';
	private static $plural_name = 'Forms';
	private static $default_submit_text = 'Submit';

	private static $use_nospam = true;

	private static $extensions = [
		FieldGroupExtension::class,
		Cacheable::class
	];

	private static $db = [
		'Title' => 'Varchar(255)',
		'SubmitText' => 'Varchar(50)',
		'ConfirmationText' => 'HTMLText',
	];

	private static $has_many = [
		'Actions' => FormAction::class,
		'Submissions' => Submission::class
	];

	private static $defaults = [
		'SubmitText' => 'Submit',
		'ConfirmationText' => 'Thank you for your submission'
	];

	private static $default_sort = 'Title ASC';

	private static $cascade_duplicates = [
		'Actions'
	];

	private static $cascade_caches = [
		'Actions'
	];

	protected $_form;
	public static $_original_objects = [];
	public static $_duplicated_objects = [];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName(['JsValidationCache']);
		$fields->dataFieldByName('Title')->setDescription('For internal purposes only');
		if ($this->Exists())
		{
			$fields->insertBefore('Title',Forms\TextField::create('Shortcode','Shortcode')
				->setValue(ShortcodeParser::generateShortcode($this))
				->setReadonly(true)
				->setAttribute('onclick', 'javascript:select(this);')
				->setDescription('Copy this short code and paste on any page where you want to display this form<br /><a href="'.FormBuilderPreview::singleton()->PreviewLink($this->ID).'" target="_blank">Preview Form</a>'));
		}
		if ($submissions_fg = $fields->dataFieldByName('Submissions'))
		{
			$submissions_fg->getConfig()->addComponent(new SubmissionsExportButton());
			$submissions_fg->getConfig()->addComponent(new Forms\GridField\GridFieldDeleteAction());
			$submissions_fg->getConfig()->removeComponentsByType(Forms\GridField\GridFieldAddExistingAutocompleter::class);
			if ($columns = $submissions_fg->getConfig()->getComponentsByType(Forms\GridField\GridFieldDataColumns::class)->First())
			{
				$displayFields = Submission::singleton()->summaryFields();
				foreach($this->Fields()->Filter('ShowInSubmissionsTable',1)->Column('Name') as $displayField)
				{
					$displayFields[$displayField] = $displayField;
				}
				$columns->setDisplayFields($displayFields);
			}
		}

		if (!$this->Exists())
		{
			$fields->addFieldToTab('Root.Form Actions', Forms\HeaderField::create('_actionsWarning','You must save before adding actions'));
		}
		else
		{
			$fields->addFieldToTab('Root.Form Actions', Forms\GridField\GridField::create(
				'Actions',
				'Actions',
				$this->Actions(),
				Forms\GridField\GridFieldConfig_RecordEditor::create(100)
					->addComponent($GridFieldAddNewMultiClass_Actions = new GridFieldAddNewMultiClass())
					->removeComponentsByType(Forms\GridField\GridFieldFilterHeader::class)
					->removeComponentsByType(Forms\GridField\GridFieldAddNewButton::class)
					->addComponent(new GridFieldDuplicateAction())
			));
			$GridFieldAddNewMultiClass_Actions->setTitle('Add Action');
		}

		$fieldAction_texts = [];
		foreach($this->Fields() as $formBuilderField)
		{
			if ($formBuilderField->hasActions())
			{
				$fieldAction_texts[] = $formBuilderField->Explain();
			}
		}
		if ($ConfirmationTextField = $fields->dataFieldByName('ConfirmationText'))
		{
			$ConfirmationTextField->addExtraClass('stacked');
		}
		$fields->addFieldToTab('Root.FieldActions', Forms\LiteralField::create('_explain', '<div style="width:100%;overflow:scroll;"><div style="padding-bottom:6px;border-bottom:1px solid #999;">'.implode('</div><div style="padding-bottom:6px;;border-bottom:1px solid #999;">',$fieldAction_texts).'</div></div>'));

		return $fields;
	}

	public function validate()
	{
		$result = parent::validate();
		if (!$this->Title)
		{
			$result->addFieldError('Title','Please add a title');
		}
		elseif (FormBuilder::get()->Exclude('ID',$this->ID)->Find('Title',$this->Title))
		{
			$result->addError('This form title is already used on another form. Please enter a unique title');
		}
		return $result;
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();
		if (!$this->SubmitText)
		{
			$this->SubmitText = $this->Config()->get('default_submit_text');
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		$this->clearAllCache();
	}

	public function onBeforeDuplicate($original, $doWrite, $relations)
	{
		$baseTitle = 'Copy of '.$original->Title;
		$this->Title = $baseTitle;
		$count = 0;
		while(FormBuilder::get()->Exclude('ID', $this->ID)->Filter('Title', $this->Title)->Count())
		{
			$count++;
			$this->Title = $baseTitle.' - '.$count;
		}
	}

	public function onAfterDuplicate($original, $doWrite, $relations)
	{
		foreach($this->Actions() as $formBuilderAction)
		{
			$formBuilderAction->invokeWithExtensions('onAfterDuplicate_FormBuilder');
		}

		foreach($this->FieldsFlat() as $formBuilderField)
		{
			$formBuilderField->invokeWithExtensions('onAfterDuplicate_FormBuilder');
			// copying a field prepends the field with "Copy of ", we need to remove that number
			$formBuilderField->Name = trim(preg_replace('/^Copy of/','',$formBuilderField->Name));
			$formBuilderField->write();
		}
	}

	public function clearAllCache()
	{
		$this->clearFormCache();
		$this->clearJsCache();
		return $this;
	}

	public function clearFormCache()
	{
		$result = Cache::delete($this->CacheName('form'));
		return $this;
	}

	public function clearJsCache()
	{
		Cache::delete($this->CacheName('formJs'));
		return $this;
	}

	public function getFormName()
	{
		$name = 'FormBuilder';
		$this->extend('updateFormName', $name);
		return $name;
	}

	public function getFormHTMLID()
	{
		$name = 'FormBuilderForm_'.$this->ID;
		$this->extend('updateFormName', $name);
		return $name;
	}

	public function generateForm($controller = null,$defaults = null)
	{
		if (is_null($this->_form))
		{
			if (!$controller)
			{
				$controller = Controller::curr();
			}

			if (!$defaults)
			{
				$defaults = $controller->getRequest()->requestVars();
			}

			$cacheName = $this->CacheName('form');
			$validator = $fields = false;
			try {
				if ( ($cachedForm = Cache::get($cacheName)) && (!Director::isDev()) )
				{
					$cachedForm = unserialize($cachedForm);
					$validator = $cachedForm['validator'];
					$fields = $cachedForm['fields'];
				}
			} catch (\Exception $e) {}
			if ( (!$validator) || (!$fields) )
			{
				$validator = Validator::create();
				$fields = Forms\FieldList::create($this->generateFormFields($validator, $defaults));
				$cachedForm = [];
				$cachedForm['validator'] = $validator;
				$cachedForm['fields'] = $fields;
				Cache::set($cacheName, serialize($cachedForm));
			}
			$actions = Forms\FieldList::create(
				Forms\FormAction::create('handleForm',$this->SubmitText ? $this->SubmitText : $this->Config()->get('default_submit_text'))
			);

			$this->_form = Forms\Form::create(
				$controller,
				$this->getFormName(),
				$fields,
				$actions,
				$validator
			)->setFormAction($controller->FormActionUrl($this, $controller))
				->setAttribute('data-form-builder', true)
				->setAttribute('data-form-builder-id', $this->ID)
				->addExtraClass('form-builder')
				->setHTMLID($this->getFormHTMLID());

			$this->_form->FormBuilder = $this;

			if ($controller->getRequest()->requestVar('form-builder-submitted'))
			{
				$this->_form->setSessionData($defaults);
			}
			else
			{
				$this->_form->restoreFormState();
			}
			if ( ($validationResult = $this->_form->getSessionValidationResult()) && ($validationErrors = $validationResult->getMessages()) )
			{
				$errors = [];
				foreach($validationErrors as $validationError)
				{
					$errors[] = $validationError['message'];
				}
				$this->_form->setMessage(FieldType\DBField::create_field(FieldType\DBHTMLText::class,implode('<br />', $errors)));
			}

			$controller->invokeWithExtensions('onBeforeFormBuilderRequirements', $this);
			$this->includeRequirements($controller);
			$controller->invokeWithExtensions('onAfterFormBuilderRequirements', $this);
			$fields->push(Forms\HiddenField::create('form-builder-submitted','')->setValue('1'));
		}

		$this->extend('updateForm', $this->_form);
		return $this->_form;
	}

	public function includeRequirements($controller)
	{
		Requirements::css('iqnection-modules/formbuilder:client/css/formbuilder.css');
		Requirements::customScript($this->getFrontEndCustomScript(),'formbuilder-'.$this->ID);
		Requirements::javascript('iqnection-modules/formbuilder:client/javascript/jquery.validate.nospam.js');
		Requirements::javascript('iqnection-modules/formbuilder:client/javascript/additional-methods.js');
		Requirements::javascript('iqnection-modules/formbuilder:client/javascript/formbuilder.js');
	}

	public function getFrontEndJS()
	{
		$cacheName = $this->CacheName('formJs');
		if ($cachedScript = Cache::get($cacheName))
		{
			try {
				$cachedScript = unserialize($cachedScript);
				if ( (is_array($cachedScript)) && (count($cachedScript)) && (!Director::isDev()) )
				{

					return $cachedScript;
				}
			} catch (\Exception $e) {}
		}
		$scripts = [
			'formId' => $this->getFormHTMLID(),
			'_id' => $this->ID,
			'fieldActions' => [],
			'validatorConfig' => [
				'rules' => [],
				'messages' => []
			],
			'selectFieldOptions' => []
		];
		if ( ($this->Config()->get('use_nospam')) && (class_exists('IQnection\\FormPage\\NoSpamController')) && (class_exists('IQnection\\FormUtilities\\FormUtilities')) )
		{
			$scripts['validatorConfig']['useNospam'] = true;
		}
		$formLoadStateCondition = [
			'selector' => '[data-form-builder-id="'.$this->ID.'"]',
			'state' => 'Loaded',
			'stateCallback' => 'stateOnFormLoad',
			'selections' => [],
		];
		// collect all hidden fields and set with the form load action
		foreach($this->FieldsFlat() as $Field)
		{
			$fieldSelector = $Field->getjQuerySelector();
			foreach($Field->getOnLoadFieldActions($formLoadStateCondition) as $baseAction)
			{
				if (isset($baseAction[0]))
				{
					$scripts['fieldActions'] = array_merge($scripts['fieldActions'], $baseAction);
				}
				else
				{
					$scripts['fieldActions'][] = $baseAction;
				}
			}
			foreach($Field->FieldActions() as $fieldAction)
			{
				$scripts['fieldActions'][] = $fieldAction->getActionData();
			}
			// collect all selectable values for storage
			if ($Field->hasExtension(SelectField::class))
			{
				foreach($Field->Options() as $fieldOption)
				{
					$fieldOptionSelector = $fieldOption->getjQuerySelector(true);
					$scripts['selectFieldOptions'][$fieldSelector][] = [
						'value' => $fieldOption->ID,
						'label' => $fieldOption->getOptionLabel()->Raw(),
						'hidden' => (bool) $fieldOption->HideByDefault,
						'selector' => $fieldOptionSelector
					];
					foreach($fieldOption->SelectionActions() as $fieldOptionAction)
					{
						$scripts['fieldActions'][] = $fieldOptionAction->getActionData();
					}
					foreach($fieldOption->getOnLoadFieldSelectionActions($formLoadStateCondition) as $baseAction)
					{
						if (isset($baseAction[0]))
						{
							$scripts['fieldActions'] = array_merge($scripts['fieldActions'], $baseAction);
						}
						else
						{
							$scripts['fieldActions'][] = $baseAction;
						}
					}
				}
			}
		}
		foreach($this->DataFields() as $dataField)
		{
			$fieldRules = $dataField->getFieldJsValidation();
			if (!empty($fieldRules))
			{
				$scripts['validatorConfig']['rules'][$dataField->getJavaScriptValidatorName()] = $fieldRules;
			}
			$fieldMessages = $dataField->getFieldJsMessages();
			if (!empty($fieldMessages))
			{
				$scripts['validatorConfig']['messages'][$dataField->getJavaScriptValidatorName()] = $fieldMessages;
			}
		}
		$this->extend('updateFrontEndJS', $scripts);
		try {

			Cache::set($cacheName, serialize($scripts));
		} catch (\Exception $e) {}
		if ($this->Exists())
		{
			$this->write();
		}
		return $scripts;
	}

	public function getFrontEndCustomScript()
	{
		return "window._formBuilderRules = window._formBuilderRules || [];
window._formBuilderRules.push(".json_encode($this->getFrontEndJS()).");";
	}

	public function forTemplate()
	{
		$controller = Controller::curr();
		return $this->generateForm($controller)->forTemplate();
	}

	public function FieldPlacements()
	{
		$placementIDs = [];
		foreach($this->Fields() as $field)
		{
			if ($field->hasExtension(FieldGroupExtension::class))
			{
				$placementIDs = $field->ID;
			}
		}
		return Field::get()->byIDs($placementIDs);
	}

	public function processFormData(&$data, &$form, &$controller)//&$request, &$response)
	{
		$this->extend('beforeProcessFormData', $data, $form, $controller);

		// test nospam
		if ( ($this->Config()->get('use_nospam')) && (class_exists('IQnection\\FormPage\\NoSpamController')) && (class_exists('IQnection\\FormUtilities\\FormUtilities')) )
		{
			if (!\IQnection\FormUtilities\FormUtilities::validateAjaxCode())
			{
				$form->sessionMessage('You Must JavaScript Enabled');
				$controller->redirectBack();
				return;
			}
		}

		// if we've made it this far, then validation has passed
		foreach($this->DataFields() as $dataField)
		{
			$dataField->invokeWithExtensions('processFormData',$data, $form, $controller);
		}
		$this->extend('afterProcessFormData', $data, $form, $controller);
	}

	public function createSubmission($data, $form)
	{
		$submission = Submission::create();
		$submission->FormBuilderID = $this->ID;
		$submission->write();
		// at this point we're assuming the submission has been validated
		// so now we're just saving the submission
		$submitCacheData = [];
		// don't save any values unless all values can be saved
		$submittedValues = [];
		foreach($this->DataFields() as $formBuilderField)
		{
			$submittedValue = (array_key_exists($formBuilderField->getFrontendFieldName(), $data)) ? $data[$formBuilderField->getFrontendFieldName()] : null;
			if ( (is_string($submittedValue)) && (!strlen($submittedValue)) )
			{
				$submittedValue = null;
			}
			$submitCacheData[$formBuilderField->Name] = null;
			try {
				if ( ($submissionFieldValue = $formBuilderField->createSubmissionFieldValue($submittedValue, $data)) && (!is_null($submissionFieldValue->Value)) )
				{
					$submissionFieldValue->SubmissionID = $submission->ID;
					$submittedValues[] = $submissionFieldValue;
					$submitCacheData[$formBuilderField->Name] = $submissionFieldValue->Value;
				}
			} catch (\Exception $e) {
				throw $e;
			}
		}
		// cache the submitted results
		$submission->FormData = serialize($submitCacheData);

		// if an exception wasn't thrown, the save each value
		foreach($submittedValues as $submittedValue)
		{
			$submittedValue->write();
		}

		return $submission;
	}

	public function handleEvent($event, $form, $data, $submission)
	{
		foreach($this->Actions()->Filter('Event', $event) as $action)
		{
			if ($action->hasMethod($event))
			{
				$result = $action->{$event}($form, $data, $submission);
				if ( ($result instanceof HTTPResponse) && ($result->isFinished()) )
				{
					return $result;
				}
			}
		}
		foreach($this->DataFields() as $dataField)
		{
			$dataField->invokeWithExtensions('handleEvent', $event, $form, $data, $submission);
		}
	}
}


