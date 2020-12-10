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
use SilverStripe\Core\Flushable;
use IQnection\FormBuilder\Forms\GridField\SubmissionsExportButton;

class FormBuilder extends DataObject implements Flushable
{
	private static $table_name = 'FormBuilder';
	private static $singular_name = 'Form';
	private static $plural_name = 'Forms';
	private static $default_submit_text = 'Submit';

	private static $use_nospam = true;

	private static $extensions = [
		FieldGroupExtension::class
	];

	private static $db = [
		'Title' => 'Varchar(255)',
		'SubmitText' => 'Varchar(50)',
		'ConfirmationText' => 'HTMLText',
		'JsValidationCache' => 'Text'
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

	public static function flush()
	{
		if ($_GET['flush'] == 'forms')
		{
			foreach(FormBuilder::get() as $formBuilder)
			{
				$formBuilder->clearJsCache();
			}
		}
	}

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
				->setDescription('Copy this short code and paste on any page where you want to display this form<br />
<a href="'.FormBuilderPreview::singleton()->PreviewLink($this->ID).'" target="_blank">Preview Form</a>'));
		}
		if ($submissions_fg = $fields->dataFieldByName('Submissions'))
		{
			$submissions_fg->getConfig()->addComponent(new SubmissionsExportButton());
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
$fields->addFieldToTab('Root.FieldActions', Forms\LiteralField::create('_validation', '<div style="width:100%;overflow:scroll;"><pre><xmp>'.print_r(json_encode($this->getFrontEndJS(),JSON_PRETTY_PRINT),1).'</xmp></pre></div>'));

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

	public function clearJsCache()
	{
		if ( (!empty($this->JsValidationCache)) && ($this->Exists()) )
		{
			$this->JsValidationCache = null;
			$this->write();
		}
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

			$validator = Validator::create();
			$fields = Forms\FieldList::create($this->generateFormFields($validator));
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

			if ($controller->getRequest()->isPOST())
			{
				$this->_form->setSessionData($controller->getRequest()->postVars());
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
				$this->_form->setMessage(implode('<br />', $errors));
			}

			$controller->invokeWithExtensions('onBeforeFormBuilderRequirements', $this);
			$this->includeRequirements($controller);
			$controller->invokeWithExtensions('onAfterFormBuilderRequirements', $this);
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
		$cachedScript = json_decode($this->JsValidationCache,1);
		if ( (is_array($cachedScript)) && (count($cachedScript)) && (json_last_error() == JSON_ERROR_NONE) && (!Director::isDev()) )
		{
			return $cachedScript;
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
		$formLoadStateCondition = [[
			'selector' => '[data-form-builder-id="'.$this->ID.'"]',
			'state' => 'Loaded',
			'stateCallback' => 'stateOnFormLoad',
			'selections' => [],
		]];
		// collect all hidden fields and set with the form load action
		foreach($this->FieldsFlat() as $Field)
		{
			$fieldSelector = $Field->getjQuerySelector();
			foreach($Field->getOnLoadFieldActions($formLoadStateCondition) as $baseAction)
			{
				$scripts['fieldActions'][] = $baseAction;
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
						$scripts['fieldActions'][] = $baseAction;
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
		$this->JsValidationCache = json_encode($scripts);
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

	public function onBeforeDuplicate($original, $doWrite, $relations)
	{
		$this->Title = 'Copy of '.$original->Title;
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
				if ( ($submissionFieldValue = $formBuilderField->createSubmissionFieldValue($submittedValue)) && (!is_null($submissionFieldValue->Value)) )
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


