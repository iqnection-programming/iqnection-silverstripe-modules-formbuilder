<?php


namespace IQnection\FormBuilder\Forms\GridField;

use SilverStripe\Forms\GridField;
use League\Csv\Writer;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use IQnection\FormBuilder\Model\Submission;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

class SubmissionsExportButton extends GridField\GridFieldExportButton
{
	/**
	 * @overload
	 *
     * Handle the export, for both the action button and the URL
     *
     * @param GridField $gridField
     * @param HTTPRequest $request
     *
     * @return HTTPResponse
     */
    public function handleExport($gridField, $request = null)
    {
        $now = date("d-m-Y-H-i");
		$formBuilderName = strtolower($gridField->getManipulatedList()->First()->FormBuilder()->Title);
		$formBuilderName = preg_replace('/[^A-Za-z0-9\-\_]+/','-',$formBuilderName);
		$formBuilderName = preg_replace('/^\-|\-$/','',$formBuilderName);
        $fileName = $formBuilderName.'-export-'.$now.'.csv';

        if ($fileData = $this->generateExportFileData($gridField)) {
            return HTTPRequest::send_file($fileData, $fileName, 'text/csv');
        }
        return null;
    }
	
	/**
	 * @overload
	 * 
     * Return the columns to export
     *
     * @param GridField $gridField
     *
     * @return array
     */
    protected function getExportColumnsForGridField(GridField\GridField $gridField)
    {
        if ($this->exportColumns) {
            return $this->exportColumns;
        }

		//Remove GridFieldPaginator as we're going to export the entire list.
        $gridField->getConfig()->removeComponentsByType(GridField\GridFieldPaginator::class);

        $items = $gridField->getManipulatedList();
		// @todo should GridFieldComponents change behaviour based on whether others are available in the config?
        foreach ($gridField->getConfig()->getComponents() as $component) {
            if ($component instanceof GridField\GridFieldFilterHeader || $component instanceof GridField\GridFieldSortableHeader) {
                $items = $component->getManipulatedData($gridField, $items);
            }
        }
		
        // get all availabel columns from all of this form's submissions
		$this->exportColumns = [
			'ID' => 'ID',
			'Created' => 'Date',
			'PageName' => 'Page'
		];
		foreach ($items->limit(null) as $item) 
		{
			$itemRawFormData = $item->RawFormData();
			if (is_array($itemRawFormData))
			{
				foreach(array_keys($itemRawFormData) as $key)
				{
					if (!array_key_exists($key, $this->exportColumns))
					{
						$this->exportColumns[$key] = $key;
					}
				}
			}
		}
		return $this->exportColumns;
    }
}



