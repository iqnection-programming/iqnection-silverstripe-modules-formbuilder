<?php

namespace IQnection\FormBuilder\Control;

use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Security\Security;

class FormBuilderPreview extends \PageController
{
	private static $extensions = [
		FormHandler::class
	];

	private static $allowed_actions = [
		'preview',
		'_confirm'
	];

	private static $url_segment = '_form-builder-preview';

	private static $url_handlers = [
		'preview/$FormBuilderID' => 'preview',
		'_formbuilderSubmit/$FormBuilderID' => '_formbuilderSubmit'
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