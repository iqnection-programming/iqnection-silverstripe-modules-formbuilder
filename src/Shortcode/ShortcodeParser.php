<?php


namespace IQnection\FormBuilder\Shortcode;

use SilverStripe\View\Parsers\ShortcodeHandler;
use IQnection\FormBuilder\FormBuilder;

class ShortcodeParser implements ShortcodeHandler
{
	public static function get_shortcodes()
    {
        return array('formbuilder');
    }
	
	/**
     * @param array $arguments
     * @param string $content
     * @param ShortcodeParser $parser
     * @param string $shortcode
     * @param array $extra
     *
     * @return string
     */
    public static function handle_shortcode($attributes, $content, $parser, $shortcode, $extra = array())
    {
		if ( (isset($attributes['id'])) && ($FormBuilder = FormBuilder::get()->byID($attributes['id'])) )
		{
			return $FormBuilder->forTemplate();
		}
	}
	
	public static function generateShortcode(FormBuilder $FormBuilder)
	{
		$params = [
			'formbuilder',
			'id='.$FormBuilder->ID,
			'title="'.$FormBuilder->Title.'"'
		];
		return '['.implode(',',$params).']';
	}
}