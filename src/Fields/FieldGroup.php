<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Extensions\FieldGroupExtension;
use IQnection\FormBuilder\Model\Field;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use SilverStripe\Forms;
use SilverStripe\ORM\FieldType;

class FieldGroup extends Field
{
	private static $table_name = 'FormBuilderFieldGroup';
	private static $singular_name = 'Field Group';
	private static $plural_name = 'Field Groups';
	private static $max_fields = 3;

	private static $extensions = [
		FieldGroupExtension::class,
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'ShowInSubmissionsTable',
		]);
		if ($nameField = $fields->fieldByName('Name'))
		{
			$nameField->setName('Label')
				->setDescription('This will be the field group title');
		}
		if ($fieldsField_gf = $fields->dataFieldByName('Fields'))
		{
			$fields->insertBefore('Fields', Forms\HeaderField::create('_limit','Add up to 3 fields',1));
			if (!$this->CanAddField())
			{
				$fieldsField_gf->getConfig()->removeComponentsByType(GridFieldAddNewButton::class);
				$fieldsField_gf->getConfig()->removeComponentsByType(GridFieldAddNewMultiClass::class);
			}
			elseif ($GridFieldAddNewMultiClass = $fieldsField_gf->getConfig()->getComponentsByType(GridFieldAddNewMultiClass::class)->First())
			{
				$classes = [];
				foreach($GridFieldAddNewMultiClass->getClasses($fieldsField_gf) as $cleanClassName => $label)
				{
					$rawClassName = str_replace('-', '\\', $cleanClassName);
					if ($rawClassName != $this->getClassName())
					{
						$classes[] = $rawClassName;
					}
				}
				$GridFieldAddNewMultiClass->setClasses($classes);
			}

		}
		return $fields;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$fieldGroup = Forms\FieldGroup::create($this->Name.'_group', $this->generateFormFields($validator, $defaults));
		$fieldGroup->setTitle($this->Name);
		foreach($fieldGroup->FieldList() as $field)
		{
			$field->setRightTitle($field->Title());
			$field->setTitle('');
		}
		$fieldGroup->addExtraClass('stacked col'.$fieldGroup->FieldList()->Count());
		return $fieldGroup;
	}

	public function EnableDisplay()
	{
		$fields = [];
		foreach($this->Fields() as $field)
		{
			$fields[] = $field->Name.': '.$field->dbObject('Enable')->Nice();
		}
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, implode("<br />", $fields));
	}

	public function FieldType()
	{
		$fields = [];
		foreach($this->Fields() as $field)
		{
			$fields[] = $field->Name.': '.$field->Name;
		}
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, implode("<br />", $fields));
	}

	public function ShowInSubmissionsTableDisplay()
	{
		$fields = [];
		foreach($this->Fields() as $field)
		{
			$fields[] = $field->Name.': '.$field->dbObject('ShowInSubmissionsTable')->Nice();
		}
		return FieldType\DBField::create_field(FieldType\DBHTMLVarchar::class, implode("<br />", $fields));
	}

	public function getjQuerySelector()
	{
		return '.form-builder-field-'.$this->ID;
	}

}