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
				$formBuilderField->invokeWithExtensions('updateFrontEndValidator', $this, $data);

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