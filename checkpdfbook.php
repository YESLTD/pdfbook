<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
Extension is not installed
EOT;
        exit( 1 );
}
 
$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['CheckPdfBook'] = $dir . 'checkpdfbook_body.php'; # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['checkpdfbook'] = $dir . 'checkpdfbook.i18n.php';
$wgSpecialPages['CheckPdfBook'] = 'CheckPdfBook'; # Let MediaWiki know about your new special page.
$wgHooks['LanguageGetSpecialPageAliases'][] = 'checkpdfbookLocalizedPageName'; # Add any aliases for the special page.
 
function checkpdfbookLocalizedPageName(&$specialPageArray, $code) {
  # The localized title of the special page is among the messages of the extension:
  wfLoadExtensionMessages('checkpdfbook');
  $text = wfMsg('checkpdfbook');
 
  # Convert from title in text form to DBKey and put it into the alias array:
  $title = Title::newFromText($text); 
  return true;
}
