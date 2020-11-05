<?php

namespace IQnection\FormBuilder\Actions;

use IQnection\FormBuilder\Model\FieldAction;

class ToggleDisplayFieldAction extends FieldAction
{
	private static $table_name = 'FormBuilderToggleDisplayFieldAction';
	private static $singular_name = 'Change Field Display';
	
	public function getActionData()
	{
		$actionData = parent::getActionData();
		$actionData['action']['callback'] = ($this->Parent()->HideByDefault) ? 'actionShowField' : 'actionHideField';
		return $actionData;
	}
	
	public function singular_name()
	{
		if ($this->Parent()->Exists())
		{
			return $this->Parent()->HideByDefault ? 'Show Field' : 'Hide Field';
		}
		return parent::singular_name();
	}
}