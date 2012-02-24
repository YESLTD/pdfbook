<?php
class CheckPdfBook extends SpecialPage {
        function CheckPdfBook() {
                SpecialPage::SpecialPage("CheckPdfBook");
                wfLoadExtensionMessages('checkpdfbook');
        }
 
        function execute( $par ) {
                global $wgRequest, $wgOut, $wgScriptPath, $PdfBookHtmlDoc, $wgVersion, $IP, $wgDBprefix;
		global $wgUploadDirectory;
 
                $this->setHeaders();
 
                # Get request data from, e.g.	
                $param = $wgRequest->getText('param');
 
                # Do stuff
                # ...
 
                function Status($Ok, $Text, $Hint) { 
	        //<strong>".(($Ok) ? "ok" : "failed")."</strong>
	            return "<tr><td style='padding-left: 4px; padding-right: 16px; background: ".(($Ok) ? "green" : "red").";'><strong>".(($Ok) ? "OK" : "ERROR")."</strong></td><td valign='top' style='padding: 8px; background: ".(($Ok) ? "green" : "red").";'><strong>$Text</strong><br /><font color='white'>".(($Ok) ? "" : $Hint)."</font></td></tr>";
		}
								     
                $dbr = wfGetDB( DB_SLAVE );
 
                # Output
		$output = "";
		$output .= "<p>PHP version: <strong>".phpversion()."</strong></p>";
		$output .= "<p>Platform: <strong>".php_uname()."</strong></p>";
		$output .= "<p>Mediawiki version: <strong>".$wgVersion."</strong></p>";
		$output .= "<p>Database: <strong>".$dbr->getSoftwareLink()." ".$dbr->getServerVersion()."</strong></p>";
		$output .= "<p>Database prefix: <strong>".$wgDBprefix."</strong></p>";
		$output .= "<p>PdfBook version: <strong>".pdfbook_version."</strong></p>";
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
		$file = "pdfbook/".uniqid('pdf-book');
		$file = "$wgUploadDirectory/$file";
	    	$fh = @fopen($file,'w+');
		@fwrite($fh,"<h1>Test</h1>");
		@fclose($fh);
		
		$cmd = " --headfootsize 8 --quiet --jpeg --color";
		$cmd .= " --format pdf14 ";
		$file2  = str_replace('/', DIRECTORY_SEPARATOR, $file);

		global $PdfBookHtmlDoc;
		if (!isset($PdfBookHtmlDoc)) 
		    $htmldocpath = "htmldoc";
		  else
		    $htmldocpath = $PdfBookHtmlDoc;
		putenv("HTMLDOC_NOCGI=1");

		$PDF = "";
		$ExecTest = false;
		if (substr(php_uname(), 0, 7) == "Windows") {
		    $cmd  = escapeshellarg($htmldocpath)." -t pdf $cmd -f ".escapeshellarg($file2.".pdf")." ".escapeshellarg($file2)."";
		    $obj = new COM("WScript.Shell");
		    $obj->Run($cmd, 1, true);
		    if (!file_exists($file2.".pdf"))
			$PDF = file($file2.".pdf");
		} else {
		    $cmd  = escapeshellarg($htmldocpath)." -t pdf $cmd ".escapeshellarg($file2)."";
		    exec($cmd, $PDFarr);
		    $PDF = implode($PDFarr);
		}
		if (strpos($PDF, "%PDF") === false) { 
		} else {
		    $PDF = "OK";		    
		    $ExecTest = true;
		}
		$output .= Status($ExecTest, wfMsg('checkpdfbookHtmlDocExecTest'), wfMsg('checkpdfbookHtmlDocExecTestHint'));		

		# TOTAL STATUS		
		$output .= "<tr><td colspan='2' style='height: 2px'>&nbsp;</td></tr>";
		$output .= Status(($dirtouch) && ($dircheck) && ($htmldocpath) && ($htmldocexec) && ($ExecTest), wfMsg('checkpdfbookTotal'), "");
		$output .= "</table>";

		
		
		
                $wgOut->addHTML( $output );
        }
}


