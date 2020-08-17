<?php

namespace IQnection\FormBuilder\Actions;

use IQnection\FormBuilder\Model\SelectFieldOptionAction;
use IQnection\FormBuilder\Model\SelectFieldOption;

class ToggleDisplaySelectionAction extends SelectFieldOptionAction
{
	private static $table_name = 'FormBuilderToggleDisplaySelectionAction';
	private static $singular_name = 'Change Selection Display';
	
	public function getActionData()
	{
		$actionData = parent::getActionData();
		$actionData['action']['callback'] = ($this->Parent()->HideByDefault) ? 'actionShowFieldOption' : 'actionHideFieldOption';
		return $actionData;
	}
}