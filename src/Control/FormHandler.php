<?php

namespace IQnection\FormBuilder\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FormRequestHandler;
use IQnection\FormBuilder\FormBuilder;
use IQnection\FormBuilder\Model\Submission;

class FormHandler extends Extension
{
	private static $allowed_actions = [
		'_formbuilderSubmit',
		'_confirm'
	];

	private static $url_segment = '_formbuilderSubmit';

	private static $url_handlers = [
		'_formbuilderSubmit/$FormBuilderID' => '_formbuilderSubmit'
	];

	public function FormActionUrl(FormBuilder $form, $controller = null)
	{
		if (!$controller)
		{
			$controller = $this->owner;
		}
		return Controller::join_links($controller->Link(),'_formbuilderSubmit',$form->ID);
	}

	public function handleForm($data, $form)
	{
		$formBuilder = $form->FormBuilder;
		$response = $this->owner->getResponse();
		$formBuilder->processFormData($data, $form, $this->owner);
		if ($this->owner->getResponse()->isFinished())
		{
			return $this->owner->getResponse();
		}

		$submission = $formBuilder->createSubmission($data, $form);
		$submission->PageID = $this->owner->ID;
		$submission->PageName = $this->owner->Breadcrumbs(20, true, false, true);
		$submission->write();

		// handle actions
		$result = $formBuilder->handleEvent('onFormSubmit', $form, $data, $submission);

		// clear the cached values
		$form->clearFormState();

		if ( ($result instanceof HTTPResponse) && ($result->isFinished()) )
		{
			return $result;
		}
		// redirect to confirmation page
		// provides an opportunity to add extra query params for tracking
		$redirectParams = [
			'f' => md5(md5($formBuilder->ID)),
			's' => md5(md5($submission->ID))
		];
		$this->owner->invokeWithExtensions('updateFormBuilderConfirmationUrlParams', $redirectParams);

		$redirectURL = Controller::join_links($this->owner->Link(),'_confirm','?'.http_build_query($redirectParams));

		return $this->owner->redirect($redirectURL);
	}

	public function _formbuilderSubmit($request)
	{
		if ($FormBuilder = FormBuilder::get()->byID($request->param('FormBuilderID')))
		{
			return $FormBuilder->generateForm($this->owner);
		}
	}

	public function _confirm()
	{
		if ( (!$s = $this->owner->getRequest()->getVar('s')) || (!$submission = Submission::get()->Where("MD5(MD5(MD5(`ID`))) = '".md5($s)."'")->First()) )
		{
			return $this->owner->redirectBack();
		}
		if ( (!$f = $this->owner->getRequest()->getVar('f')) || (!$formbuilder = FormBuilder::get()->Where("MD5(MD5(MD5(`ID`))) = '".md5($f)."'")->First()) )
		{
			return $this->owner->redirectBack();
		}
		return $this->owner->Customise([
			'FormBuilder' => $formbuilder,
			'Submission' => $submission
		])->renderWith(['Page_confirm_submission','Page']);
	}
}






