<?php

namespace IQnection\FormBuilder\Control;

use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use IQnection\FormBuilder\Model\Submission;
use IQnection\FormBuilder\Actions\SendEmailFormAction;

class FormBuilderPreview extends \PageController
{
	private static $extensions = [
		FormHandler::class
	];

	private static $allowed_actions = [
		'preview',
		'_confirm',
		'_resendsubmissions'
	];

	private static $url_segment = '_form-builder-preview';

	private static $url_handlers = [
		'preview/$FormBuilderID' => 'preview',
		'_formbuilderSubmit/$FormBuilderID' => '_formbuilderSubmit',
		'_resendsubmissions/$SubmissionID/$FormActionID' => '_resendsubmissions'
	];

	public function index()
	{
		return $this->preview();
		return $this->redirect('/');
	}

	public function Link($action = null)
	{
		return self::join_links('/',$this->Config()->get('url_segment'), $action);
	}

	public function PreviewLink($action = null)
	{
		return self::join_links('/',$this->Config()->get('url_segment'), 'preview', $action);
	}

	public function _resendsubmissions()
	{
		if (!Security::getCurrentUser())
		{
			return Security::permissionFailure($this);
		}
		if ( ($submission = Submission::get()->byId($this->getRequest()->param('SubmissionID'))) && ($emailAction = SendEmailFormAction::get()->byId($this->getRequest()->param('FormActionID'))) )
		{
			if (!$result = $emailAction->onFormSubmit($submission->FormBuilder()->generateForm($this, $submission->RawFormData()), $submission->RawFormData(), $submission))
			{
				print 'There was an error sending the email';
				die();
			}
		}
		print '<script>window.close();</script>';
		die();
	}

	protected function _currentFormBuilder()
	{
		if ( ($f = $this->owner->getRequest()->getVar('f')) && ($formbuilder = FormBuilder::get()->Where("MD5(MD5(MD5(`ID`))) = '".md5($f)."'")->First()) )
		{
			return $formbuilder;
		}
		$formBuilderID = $this->getRequest()->param('FormBuilderID');
		$formBuilder = FormBuilder::get()->byID($formBuilderID);
		return $formBuilder;
	}

	public function preview()
	{
		if (!Security::getCurrentUser())
		{
			return Security::permissionFailure($this);
		}
		$formBuilder = $this->_currentFormBuilder();
		$this->Title = 'Preview: '.($formBuilder ? $formBuilder->Title : 'Not Found');
		$this->MetaTitle = 'Preview: '.($formBuilder ? $formBuilder->Title : 'Not Found');
		return $this->Customise(['FormBuilder' => $formBuilder])->renderWith(['FormBuilderPreview_preview','Page']);
	}
}