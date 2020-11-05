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
	
	public function singular_name()
	{
		if ($this->Parent()->Exists())
		{
			return $this->Parent()->HideByDefault ? 'Show Selection' : 'Hide Selection';
		}
		return parent::singular_name();
	}
}