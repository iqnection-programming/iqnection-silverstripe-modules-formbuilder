<?php

namespace IQnection\FormBuilder\Admin;

use IQnection\FormBuilder\FormBuilder;
use SwiftDevLabs\DuplicateDataObject\Forms\GridField\GridFieldDuplicateAction;
use SilverStripe\Forms;

class ModelAdmin extends \SilverStripe\Admin\ModelAdmin
{
	private static $managed_models = [
		FormBuilder::class => [
			'title' => 'Forms'
		]
	];
	
	private static $menu_title = 'Form Builder';
	private static $url_segment = 'form-builder';
	private static $menu_icon_class = 'font-icon-block-form';
	
	public $showImportForm = false;
	public $showSearchForm = false;
	
	public function getEditForm($id = null, $fields = null)
    {
		$form = parent::getEditForm($id, $fields);
		if ($this->modelClass == FormBuilder::class)
		{
			if ($gField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass)))
			{
				$gField->getConfig()
					->removeComponentsByType(Forms\GridField\GridFieldPrintButton::class)
					->removeComponentsByType(Forms\GridField\GridFieldExportButton::class)
					->addComponent(new GridFieldDuplicateAction());
			}
		}
		return $form;
	}
}