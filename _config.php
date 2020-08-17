<?php


\SilverStripe\View\Parsers\ShortcodeParser::get('default')->register('formbuilder', [IQnection\FormBuilder\Shortcode\ShortcodeParser::class, 'handle_shortcode']);

