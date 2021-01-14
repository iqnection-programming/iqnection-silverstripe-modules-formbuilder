<?php

namespace IQnection\FormBuilder\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;

class MoneyField extends TextField
{
	private static $table_name = 'FormBuilderMoneyField';
	private static $singular_name = 'Money';

	private static $db = [
		'MinAmount' => 'Currency',
		'MaxAmount' => 'Currency',
	];

	private static $defaults = [
		'Format' => 'validateDecimal'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'Format',
			'MinSize',
			'MaxSize',
			'MinAmount',
			'MaxAmount',
		]);
		$fields->addFieldToTab('Root.Main', Forms\FieldGroup::create('Restrictions', [
			Forms\CurrencyField::create('MinAmount','Minimum'),
			Forms\CurrencyField::create('MaxAmount','Maximum')
		])->setDescription('Set to zero for no restriction'));
		return $fields;
	}

	public function getBaseField(&$validator = null, $defaults = null)
	{
		$field = Forms\NumericField::create($this->getFrontendFieldName())
			->setHTML5(true)
			->setScale('2');
		if (ceil($this->MinAmount))
		{
			$field->setAttribute('min', $this->MinAmount);
		}
		if (ceil($this->MaxAmount))
		{
			$field->setAttribute('max', $this->MaxAmount);
		}
		return $field;
	}

	public function validateFormValue($value)
	{
		$errors = parent::validateFormValue($value);
		if ( (ceil($this->MinAmount) > 0) && ($value < $this->MinAmount) )
		{
			$errors[] = 'Minimum amount allowed is '.$this->MinAmount;
		}
		if ( (ceil($this->MaxAmount) > 0) && ($value > $this->MaxAmount) )
		{
			$errors[] = 'Maximum amount allowed is '.$this->MaxAmount;
		}
		return $errors;
	}

	public function getFieldJsValidation()
	{
		$rules = parent::getFieldJsValidation();
		if (ceil($this->MinAmount) > 0)
		{
			$rules['min'] = $this->MinAmount;
		}
		if (ceil($this->MaxAmount) > 0)
		{
			$rules['max'] = $this->MaxAmount;
		}
		return $rules;
	}
}