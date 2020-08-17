<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class FileUploadField extends Field
{
	private static $table_name = 'FormBuilderFileUploadField';
	private static $singular_name = 'File Upload';	
	
	private static $extensions = [
		\IQnection\FormBuilder\Extensions\UploadField::class
	];

	public function getBaseField($validator = null)
	{
		$field = Forms\FileField::create($this->getFrontendFieldName());
		return $field;		
	}
}






