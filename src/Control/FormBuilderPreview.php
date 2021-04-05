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
		'_resendsubmissions',
		'_export',
		'_import'
	];

	private static $url_segment = '_form-builder-preview';

	private static $url_handlers = [
        'preview/$FormBuilderID' => 'preview',
        '_export/$FormBuilderID' => '_export',
		'_import/$FormBuilderID' => '_import',
		'_formbuilderSubmit/$FormBuilderID' => '_formbuilderSubmit',
		'_resendsubmissions/$SubmissionID/$FormActionID' => '_resendsubmissions'
	];

	public function index()
	{
		return $this->preview();
	}

	public function Link($action = null)
	{
		return self::join_links('/',$this->Config()->get('url_segment'), $action);
	}

	public function PreviewLink($action = null)
	{
		return self::join_links('/',$this->Config()->get('url_segment'), 'preview', $action);
	}

	public function _export($request)
	{
		if (!$formBuilder = $this->_currentFormBuilder())
		{
			return $this->httpError(404);
		}
		$exportData = $formBuilder->ExportConfig();

        return $this->getResponse()->addHeader('content-type','application/json')->setBody(json_encode($exportData));
	}

	public function _import($request)
	{
		$dataPath = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'contact-form.json';
        if (file_Exists($dataPath))
        {
            $data = json_decode(file_get_contents($dataPath), 1);
            if (!$fb = FormBuilder::get()->Find('Title', $data['Title']))
            {
                $fb = FormBuilder::create();
            }
            $fb->Importconfig($data);
        }
	}

	public function _resendsubmissions()
	{
		if (!Security::getCurrentUser())
		{
			return Security::permissionFailure($this);
		}
		if ( ($submission = Submission::get()->byId($this->getRequest()->param('SubmissionID'))) && ($emailAction = SendEmailFormAction::get()->byId($this->getRequest()->param('FormActionID'))) )
		{
			$email = $emailAction->generateEmail($submission->FormBuilder()->generateForm($this, $submission->RawPostData()), $submission->RawPostData(), $submission);
			if (!$email->send())
			{
				print 'There was an error sending the email';
				die();
			}
			// see if any recipients failed
			if ( ($failedRecipients = $email->getFailedRecipients()) && (count($failedRecipients)) )
			{
				foreach($failedRecipients as $failedRecipient)
				{
					print '<div>There was an error sending the email to '.$failedRecipient.'</div>';
				}
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
		if (!$formBuilder = $this->_currentFormBuilder())
		{
			return $this->redirect('/');
		}
		$this->Title = 'Preview: '.($formBuilder ? $formBuilder->Title : 'Not Found');
		$this->MetaTitle = 'Preview: '.($formBuilder ? $formBuilder->Title : 'Not Found');
		return $this->Customise(['FormBuilder' => $formBuilder])->renderWith(['FormBuilderPreview_preview','Page']);
	}
}