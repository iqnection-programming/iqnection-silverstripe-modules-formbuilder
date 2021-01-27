<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use IQnection\FormBuilder\FormBuilder;
use SilverStripe\Core\Config\Config;

class Duplicable extends DataExtension
{
	private static $form_builder_has_one_duplicates = [];
	private static $form_builder_has_many_duplicates = [];
	private static $form_builder_many_many_duplicates = [];

	public function onAfterDuplicate_FormBuilder()
	{
		if (!$original = FormBuilder::$_original_objects[$this->owner->getClassName()][$this->owner->ID])
		{
			return;
		}
		// correct has_one relations to new duplicated objects
		$form_builder_has_one_duplicates = $this->owner->Config()->get('form_builder_has_one_duplicates');
		foreach($form_builder_has_one_duplicates as $componentName)
		{
			// get the componet from the original object
			$originalComponent = $original->getComponent($componentName);
			// see if a duplicate object was created
			if (!$newComponent = FormBuilder::$_duplicated_objects[$originalComponent->getClassName()][$originalComponent->ID])
			{
				$newComponent = $originalComponent->duplicate();
			}
			if (!$newComponent->Exists())
			{
				$newComponent->write();
			}
			if ($newComponent->hasMethod('onAfterDuplicate_FormBuilder'))
			{
				$newComponent->invokeWithExtensions('onAfterDuplicate_FormBuilder');
			}
			// assign the duplicate object to the local object's relation
			$this->owner->setComponent($componentName,$newComponent);
		}
		$this->owner->write();

		// correct has_many relations to new duplicated objects
		$form_builder_has_many_duplicates = $this->owner->Config()->get('form_builder_has_many_duplicates');
		foreach($form_builder_has_many_duplicates as $componentName)
		{
			// clear any current components, they're probably original objects
			$newComponentsList = $this->owner->getComponents($componentName);
			$existingComponentIDs = $newComponentsList->Column('ID');
			$newComponentIDs = [];

			// get the components of the original object
			$components = $original->getComponents($componentName);
			foreach($components as $originalComponent)
			{
				// get the duplicated component object
				if (!$newComponent = FormBuilder::$_duplicated_objects[$originalComponent->getClassName()][$originalComponent->ID])
				{
					$newComponent = $originalComponent->duplicate();
				}
				// make the relation
				$newComponent->{$newComponentsList->getForeignKey()} = $this->owner->ID;
				$newComponent->write();
				$newComponentIDs[] = $newComponent->ID;
				if ($newComponent->hasMethod('onAfterDuplicate_FormBuilder'))
				{
					$newComponent->invokeWithExtensions('onAfterDuplicate_FormBuilder');
				}
			}
			$removeIds = array_diff($existingComponentIDs, $newComponentIDs);
			if (count($removeIds))
			{
				$newComponentsList->removeMany($removeIds);
			}
		}

		// correct many_many relations to new duplicated objects
		$form_builder_many_many_duplicates = $this->owner->Config()->get('form_builder_many_many_duplicates');
		foreach($form_builder_many_many_duplicates as $componentName)
		{
			// clear any current components, they're probably original objects
			$newComponentsList = $this->owner->getManyManyComponents($componentName);
			$newComponentsList->removeAll();

			$extraFieldNames = $newComponentsList->getExtraFields();
			$components = $original->getManyManyComponents($componentName);
			foreach($components as $originalComponent)
			{
				if (!$newComponent = FormBuilder::$_duplicated_objects[$originalComponent->getClassName()][$originalComponent->ID])
				{
					$newComponent = $originalComponent->duplicate();
					$newComponent->write();
				}
				if ($newComponent->hasMethod('onAfterDuplicate_FormBuilder'))
				{
					$newComponent->invokeWithExtensions('onAfterDuplicate_FormBuilder');
				}
				$extraFields = [];
				foreach($extraFieldNames as $fieldName => $fieldType)
				{
		            $extraFields[$fieldName] = $originalComponent->getField($fieldName);
		        }
				$newComponentsList->add($newComponent, $extraFields);
			}
		}
	}

	public function onAfterDuplicate($original, $doWrite, $relations)
	{
		if (!$this->owner->Exists())
		{
			$this->owner->write();
		}
		FormBuilder::$_duplicated_objects[$this->owner->getClassName()][$original->ID] = $this->owner;
		FormBuilder::$_original_objects[$this->owner->getClassName()][$this->owner->ID] = $original;
	}
}



