<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Injector\Injector;

class Exportable extends DataExtension
{
	private static $export_config_components = [];

    private static $export_ignore_fields = [
        'ID',
        'Created',
        'LastEdited',
        'RecordClassName'
    ];

    private static $import_unique_field = 'Name';

    public function ImportConfig($data)
    {
        unset($data['ClassName'], $data['extra_fields']);
        foreach($data as $fieldName => $fieldValue)
        {
            if (!is_array($fieldValue))
            {
                $this->owner->setField($fieldName, $fieldValue);
            }
        }
        if (!$this->owner->Exists())
        {
            $this->owner->write();
        }
        foreach($data as $fieldName => $fieldValue)
        {
            if (is_array($fieldValue))
            {
                if (isset($fieldValue['ClassName']))
                {
                    // load has one
                    if (class_exists($fieldValue['ClassName']))
                    {
                        $child = $this->owner->{$fieldName}();
                        if ($child->hasMethod('ImportConfig'))
                        {
                            $child->ImportConfig($fieldValue);
                        }
                        $this->owner->{$fieldName.'ID'} = $child->ID;
                    }
                }
                else
                {
                    // load has many or many many
                    foreach($fieldValue as $childData)
                    {
                        if (class_exists($childData['ClassName']))
                        {
                            $uniqueField = singleton($childData['ClassName'])->Config()->get('import_unique_field');
                            if (!$child = $this->owner->{$fieldName}()->Find($uniqueField, $childData[$uniqueField]))
                            {
                                $child = Injector::inst()->create($childData['ClassName']);
                            }
                            if (!$child->hasMethod('ImportConfig'))
                            {
                                throw new \Exception('Class  '.$childData['ClassName'].' does not have the method ImportConfig');
                            }
                            $child->ImportConfig($childData);
                            if (isset($childData['extra_fields']))
                            {
                                $this->owner->{$fieldName}()->add($child, $childData['extra_fields']);
                            }
                            else
                            {
                                $this->owner->{$fieldName}()->add($child);
                            }
                        }
                    }
                }
            }
        }
        $this->owner->invokeWithExtensions('onAfterImportConfig', $data);
        $this->owner->write();
        return $this->owner;
    }

	public function ExportConfig()
	{
		$data = $this->owner->toMap();
        foreach($this->owner->Config()->get('export_ignore_fields') as $ignoreField)
        {
		    unset($data[$ignoreField]);
        }

		foreach($this->owner->Config()->get('export_config_components') as $componentName)
		{
			switch($this->owner->getRelationType($componentName))
			{
				case 'has_one':
				case 'belongs_to':
					unset($data[$componentName.'ID']);
					$component = $this->getComponent($componentName);
					$data[$componentName] = $this->getComponentExportData($component);
					break;
				case 'has_many':
					$data[$componentName] = [];
					foreach($this->owner->getComponents($componentName) as $component)
					{
						$data[$componentName][] = $this->owner->getComponentExportData($component);
					}
					break;
				case 'many_many':
				case 'belongs_many_many':
					$data[$componentName] = [];
					$components = $this->owner->getManyManyComponents($componentName);
					foreach($components as $component)
					{
						$componentData = $this->owner->getComponentExportData($component);
						$componentData['extra_fields'] = ($extraFields = $components->getExtraData($componentName, $component->ID)) ? $extraFields : [];
						$data[$componentName][] = $componentData;
					}
					break;
			}

		}
		$this->owner->extend('updateExportConfig', $data);
		return $data;
	}

	public function getComponentExportData($dataObject)
	{
		$data = [];
		if ($dataObject->hasMethod('ExportConfig'))
		{
			$data = $dataObject->ExportConfig();
		}
		else
		{
			$data = $dataObject->toMap();
		}
		unset($data['Created'], $data['LastEdited']);
		return $data;
	}

	public function updateExportConfig($data) { }
}
