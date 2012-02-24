<?php
class CheckPdfBook extends SpecialPage {
        function CheckPdfBook() {
                SpecialPage::SpecialPage("CheckPdfBook");
                wfLoadExtensionMessages('checkpdfbook');
        }
 
        function execute( $par ) {
                global $wgRequest, $wgOut, $wgScriptPath, $PdfBookHtmlDoc;
 
                $this->setHeaders();
 
                # Get request data from, e.g.	
                $param = $wgRequest->getText('param');
 
                # Do stuff
                # ...
 
                function Status($Ok, $Text, $Hint) { 
	        //<strong>".(($Ok) ? "ok" : "failed")."</strong>
	            return "<tr><td style='padding-left: 4px; padding-right: 16px; background: ".(($Ok) ? "green" : "red").";'><strong>".(($Ok) ? "OK" : "ERROR")."</strong></td><td valign='top' style='padding: 8px; background: ".(($Ok) ? "green" : "red").";'><strong>$Text</strong><br /><font color='white'>".(($Ok) ? "" : $Hint)."</font></td></tr>";
		}
								     
 
                # Output
		$output = "";
		$output .= "<table border='0' cellpadding='0' cellspacing='0'>";
		$dir = dirname(dirname(dirname(__FILE__))) . '/images/pdfbook';
		$dircheck = file_exists($dir);
		$output .= Status($dircheck, wfMsg('checkpdfbookFolderCreated'), wfMsg('checkpdfbookFolderCreatedHint'));
	
		$dirtouch = false;
		if ($dircheck) {
		    $dirtouch = (is_writable($dir));
		}
		$output .= Status($dirtouch, wfMsg('checkpdfbookFolderPermissions'), wfMsg('checkpdfbookFolderPermissionsHint'));
		
		$htmldocpath = isset($PdfBookHtmlDoc);
		$output .= Status($htmldocpath, wfMsg('checkpdfbookHtmlDocPath'), wfMsg('checkpdfbookHtmlDocPathHint'));

		$htmldocexec = false;
		if ($htmldocpath) {
    		    $htmldocexec = is_executable($PdfBookHtmlDoc);
		}
		$output .= Status($htmldocexec, wfMsg('checkpdfbookHtmlDocExec'), wfMsg('checkpdfbookHtmlDocExecHint'));
		
		
		## TODO: TEST HTMLDOC
		
		$output .= "<tr><td colspan='2' style='height: 2px'>&nbsp;</td></tr>";
		$output .= Status(($dirtouch) && ($dircheck) && ($htmldocpath) && ($htmldocexec), wfMsg('checkpdfbookTotal'), "");
		$output .= "</table>";

		
		
		
                $wgOut->addHTML( $output );
        }
}

