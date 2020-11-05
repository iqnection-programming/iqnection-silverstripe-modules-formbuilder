<?php

namespace IQnection\FormBuilder\Control;

use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Security\Security;

class FormBuilderPreview extends \PageController
{
	private static $allowed_actions = [
		'preview'
	];
	
	private static $url_segment = '_form-builder-preview';
	
	public function index()
	{
		return $this->redirect('/');
	}
	
	public function Link($action = null)
	{
		return self::join_links('/',$this->Config()->get('url_segment'),'preview',$action);
	}
	
	public function preview()
	{
		if (!Security::getCurrentUser())
		{
			return Security::permissionFailure($this);
		}
		$formBuilder = FormBuilder::get()->byID($this->getRequest()->param('ID'));
		$this->Title = 'Preview: '.($formBuilder ? $formBuilder->Title : 'Not Found');
		$this->MetaTitle = 'Preview: '.($formBuilder ? $formBuilder->Title : 'Not Found');
		return $this->Customise(['FormBuilder' => $formBuilder])->renderWith(['FormBuilderPreview_preview','Page']);
	}
}