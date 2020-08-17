<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;

class DatalessField extends DataExtension
{
	public function updateCMSFields($fields)
	{
		$fields->removeByName([
			'Required',
			'Description',
			'ShowInSubmissionsTable',
		]);
	}
	
	public function updateFrontendFieldName($name)
	{
		$name = substr(md5($this->owner->ID),0,10);
	}
	
	public function uniqueItentifier()
	{
		return 'field-'.substr(md5($this->owner->ID),0,10);
	}
	
	public function updateExtraCssClasses($extraClasses)
	{
		$extraClasses[] = $this->owner->uniqueItentifier();
		return $extraClasses;
	}
	
	public function updatejQuerySelector(&$selector)
	{
		$selector = '.'.$this->owner->uniqueItentifier();
	}
}