<?php

namespace IQnection\FormBuilder\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\File;
use SilverStripe\Forms;
use SilverStripe\ORM\FieldType;

class SubmissionFieldValue extends DataObject
{
	private static $table_name = 'FormBuilderSubmissionFieldValue';
	private static $singular_name = 'Field Value';
	private static $plural_name = 'Field Values';

	private static $db = [
		'SortOrder' => 'Decimal(6,3)',
		'Name' => 'Varchar(255)',
		'Label' => 'Varchar(255)',
		'Required' => 'Boolean',
		'Value' => 'Text',
		'RawValue' => 'Text'
	];

	private static $has_one = [
		'Submission' => Submission::class,
		'File' => File::class,
		'FormBuilderField' => Field::class
	];

	private static $summary_fields = [
		'Name' => 'Name',
		'Value' => 'Value'
	];

	private static $default_sort = 'SortOrder ASC';

	public function CanCreate($member = null, $context = [])
	{
		return false;
	}

	public function CanEdit($member = null, $context = [])
	{
		return false;
	}

	public function getTitle()
	{
		return $this->Label ? $this->Label : $this->Name;
	}

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'SortOrder',
			'RawValue',
			'SubmissionID',
			'FormBuilderFieldID',
			'File',
		]);
		$fields->replaceField('Value', $this->getReadonlyField());
		return $fields;
	}

	public function getReadonlyField()
	{
		if (!$label = $this->Label)
		{
			$label = $this->FormBuilderField()->Label;
		}
		if (!$name = $this->Name)
		{
			if (!$name = $this->FormBuilderField()->Name)
			{
				$name = $label;
			}
		}
		if (!$label && $name)
		{
			$label = $name;
		}
		$field = Forms\ReadonlyField::create($name,$label)->setValue($this->Value);
		if ($this->File()->Exists())
		{
			$field->setValue(FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class,'<a href="'.$this->File()->getAbsoluteURL().'" target="_blank">'.$this->File()->Name.'</a>'));
		}
		return $field;
	}

	public function DebugInfo() { }
}