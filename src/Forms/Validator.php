<?php

namespace IQnection\FormBuilder\Forms;

use SilverStripe\Forms\RequiredFields;
use IQnection\FormBuilder\Actions\ToggleDisplayFieldAction;

class Validator extends RequiredFields
{
	public function php($data)
	{
		$form = $this->form;
		$formFields = $form->Fields()->dataFields();
		foreach($formFields as $formField)
		{
			if ($formBuilderField = $formField->FormBuilderField)
			{
				if ($isRequired = $formBuilderField->Required)
				{
					$hidden = $formBuilderField->HideByDefault;
					// if this field has actions, check to see if the field is hidden based on conditions
					if ( ($formBuilderField->FieldActions()->Count()) && (!$hidden) )
					{
						foreach($formBuilderField->FieldActions() as $fieldAction)
						{
							if ( ($fieldAction instanceof ToggleDisplayFieldAction) && ($fieldAction->testConditions($data)) )
							{
								// conditions are true, field is toggles
								$hidden = !$hidden;
							}
						}
					}
					if ($hidden)
					{
						// remove from required
						$this->removeRequiredField($formBuilderField->getFrontendFieldName());
					}
				}
			
				$fieldName = $formBuilderField->getFrontendFieldName();
				$value = (array_key_exists($fieldName,$data)) ? $data[$fieldName] : null;
				$errors = $formBuilderField->validateFormValue($value);
				$formBuilderField->extend('updateValidateFormValue',$value, $errors);
				if ( (is_array($errors)) && (count($errors)) )
				{
					foreach($errors as $error)
					{
						$this->validationError($fieldName, $error);
					}
					$valid = false;
				}
			}
		}
		$parentValid = parent::php($data);
		return $valid && $parentValid;
	}
}