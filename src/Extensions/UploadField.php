<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\FolderNameFilter;
use SilverStripe\ORM\FieldType;
use SilverStripe\Assets\Upload;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\Image;
use SilverStripe\Forms;
use SilverStripe\Control\Controller;

class UploadField extends DataExtension
{
	private static $submissions_folder = 'form-submissions';

	private static $valid_upload_categories = [
		'archive' => array(
            'gz', 'jar', 'rar', 'tar', 'tgz', 'zip', 'zipx',
        ),
        'audio' => array(
            'avr', 'm4a', 'mid', 'midi', 'mp3', 'ogg', 'wav', 'wma',
        ),
        'document' => array(
            'csv', 'doc', 'docx', 'dotm', 'dotx', 'kml', 'pages', 'pdf',
            'potm', 'potx', 'pps', 'ppt', 'pptx', 'rtf', 'txt', 'xls', 'xlsx', 'xltm', 'xltx',
        ),
        'image' => array(
            'gif', 'jpeg', 'jpg', 'png'
        ),
        'video' => array(
            'avi', 'm1v', 'm2v', 'm4v', 'mov', 'mp4', 'mpeg', 'mpg', 'ogv', 'webm', 'wmv',
        ),
	];

	private static $db = [
		'AllowedExtensions' => 'Text',
		'MaxFileSize' => 'Varchar(255)',
		'Label' => 'Varchar(255)',
		'Required' => 'Boolean',
		'Description' => 'Varchar(255)',
	];

	public function updateCMSFields($fields)
	{
		$fields->removeByName([
			'Placeholder',
			'AllowedExtensions',
			'ShowInSubmissionsTable',
		]);
		$fields->dataFieldByName('Label')->setDescription('(Optional) Defaults to the field name. This will display as the field label on the form');
		$fields->dataFieldByName('Required')->setTitle('This is a Required Field');
		$fields->dataFieldByName('Description')->setDescription('(Optional) Small text to display under the field as a description');
		$extensionsList = [];
		foreach($this->owner->Config()->get('valid_upload_categories') as $typeCategory => $extensions)
		{
			$extensionsList[$typeCategory] = FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class,'<strong>'.ucwords($typeCategory).':</strong> '.implode(', ',$extensions));
		}
		$defaultAllowedExtensionCategories = [];
		if ($db_allowedExtensions = @json_decode($this->owner->AllowedExtensions,1))
		{
			if (is_array($db_allowedExtensions['Categories']))
			{
				$defaultAllowedExtensionCategories = $db_allowedExtensions['Categories'];
			}
		}
		$fields->addFieldToTab('Root.Main', Forms\CheckboxSetField::create('_AllowedExtensions[Categories]','Allowed File Extensions')
			->setDefaultItems($defaultAllowedExtensionCategories)
			->setSource($extensionsList));

		$fields->addFieldToTab('Root.Main', Forms\TextField::create('_AllowedExtensions[Custom]','Custom/Additional Extensions')
			->setDescription('Separate with a comma'));
		$fields->addFieldToTab('Root.Main', Forms\TextField::create('MaxFileSize', 'Max File Size')
			->setDescription('Server Max: '.ini_get('upload_max_filesize')));
	}

	public function onBeforeWrite()
	{
		if (isset($_REQUEST['_AllowedExtensions']))
		{
			$this->owner->AllowedExtensions = json_encode($_REQUEST['_AllowedExtensions']);
		}
	}

	public function getAllowedUploadExtensions()
	{
		$allowedExtensions = [];
		try {
			$fileExtensionCategories = $this->owner->Config()->get('valid_upload_categories');
			if ($db_allowedExtensions = @json_decode($this->owner->AllowedExtensions,1))
			{
				if (is_array($db_allowedExtensions['Categories']))
				{
					foreach($db_allowedExtensions['Categories'] as $extensionCat)
					{
						$allowedExtensions = array_merge($allowedExtensions, $fileExtensionCategories[$extensionCat]);
					}
				}
				$allowedExtensions = array_merge($allowedExtensions, explode(',',preg_replace('/\S/','',$db_allowedExtensions['Custom'])));
				$allowedExtensions = array_unique($allowedExtensions);
				sort($allowedExtensions);
			}
		} catch (\Exception $e) { }
		return $allowedExtensions;
	}

	public function updatePreparedSubmittedValue(&$value)
	{
		// since this is an array, and we need access to the SubmissionFieldValue to save the file
		// we'll just set the object value to null so it safely sets the field
		$value = null;
	}

	public function updateBaseField(&$field, &$validator)
	{
		$allowedExtensions = $this->owner->getAllowedUploadExtensions();
		$field->setAllowedExtensions($allowedExtensions);
		$field->setFolderName($this->owner->Config()->get('submissions_folder').'/'.preg_replace('/[\/]/',' ',$this->owner->FormBuilder()->Title));
		if ($this->owner->Description)
		{
			$field->setDescription($this->owner->Description);
		}
		$label = ($this->owner->Label) ? $this->owner->Label : $this->owner->Name;
		$field->setTitle(FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, $label));
		if ($this->owner->Required)
		{
			$field->addExtraClass('required');
		}
	}

	public function updateSubmissionFieldValue($submissionFieldValue)
	{
		$fileArray = unserialize($submissionFieldValue->RawValue);
		if ( (!is_array($fileArray)) || (!isset($fileArray['size'])) || (!$fileArray['tmp_name']) )
		{
			return;
		}
		// what type of file is this?
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$type = $finfo->file($fileArray['tmp_name']);
		if (preg_match('/image/',$type))
		{
			$file = Image::create();
		}
		else
		{
			$file = File::create();
		}
		$upload = Upload::create();
		$upload->setReplaceFile(false);
		$upload->setDefaultVisibility(AssetStore::VISIBILITY_PUBLIC);
		$filter = FolderNameFilter::create();
		$destinationPath = $filter->filter($this->owner->Config()->get('submissions_folder'));
		$rootFolder = Folder::find_or_make($destinationPath);
		// if the folder was just created, set the permissions so only logged in users can access
		if ($rootFolder->Created == $rootFolder->LastEdited)
		{
			$rootFolder->CanViewType = 'LoggedInUsers';
			$rootFolder->CanEditType = 'LoggedInUsers';
			$rootFolder->write();
		}

		$formSubmissionsFolderName = $filter->filter(preg_replace('/[\/]/',' ',$this->owner->FormBuilder()->Title));
		$destinationPath = File::join_paths($rootFolder->getFilename(),$thisFormSubmissionsFolderName);
		$destinationFolder = Folder::find_or_make($destinationPath);
		if ($upload->loadIntoFile($fileArray, $file, $destinationFolder->getFilename()))
		{
			$file->protectFile();
			if ($file instanceof Image)
			{
				AssetAdmin::create()->generateThumbnails($file);
			}
			$submissionFieldValue->FileID = $file->ID;
			$submissionFieldValue->Value = $file->getAbsoluteURL();
		}
	}

	public function updateFieldJsValidation(&$js)
	{
		if ($this->owner->Required)
		{
			$js['required'] = true;
		}
		$allowedExtensions = $this->owner->getAllowedUploadExtensions();
		$allowedExtensions = array_filter($allowedExtensions);
		if ( (is_array($allowedExtensions)) && (count($allowedExtensions)) )
		{
			$js['extension'] = implode('|',$allowedExtensions);
		}
	}
}