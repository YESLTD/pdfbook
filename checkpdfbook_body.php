<?php

//////////////////////////////////////////////////////////////
//
//    Copyright (C) Thomas Kock, Delmenhorst, 2009
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License along
// with this program; if not, write to the Free Software Foundation, Inc.,
// 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
// http://www.gnu.org/copyleft/gpl.html
//
//////////////////////////////////////////////////////////////

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

        function Status($Ok, $Text, $Hint) {
            //<strong>".(($Ok) ? "ok" : "failed")."</strong>
            return "<tr><td style='padding-left: 4px; padding-right: 16px; background: ".(($Ok) ? "green" : "red").";'><strong>".(($Ok) ? "OK" : "ERROR")."</strong></td><td valign='top' style='padding: 8px; background: ".(($Ok) ? "green" : "red").";'><strong>$Text</strong><br /><font color='white'>".(($Ok) ? "" : $Hint)."</font></td></tr>";
        }

        $dbr = wfGetDB( DB_SLAVE );

        # Output
        $output = "";
        $output .= "<p>Web Server: <strong>".$_SERVER["SERVER_SOFTWARE"]."</strong></p>";
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

        $execTest = false;
        $execTestMessage = wfMsg('checkpdfbookHtmlDocExecTestHint1');
        if (($dircheck) && ($htmldocpath) && ($htmldocexec)) {
            $execTestMessage = wfMsg('checkpdfbookHtmlDocExecTestHint2');
            $file = "$wgUploadDirectory/pdfbook/".uniqid('pdf-book-');
            $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
            $fh = @fopen($file,'w+');
            @fwrite($fh,"<h1>Test</h1>");
            @fclose($fh);

            $cmd = " --headfootsize 8 --quiet --jpeg --color";
            $cmd .= " --format pdf14 ";

            global $PdfBookHtmlDoc;
            if (!isset($PdfBookHtmlDoc))
            $htmldocpath = "htmldoc";
            else
            $htmldocpath = $PdfBookHtmlDoc;
            putenv("HTMLDOC_NOCGI=1");

            $PDF = "";
            if (substr(php_uname(), 0, 7) == "Windows") {
                try {
                    $exec  = escapeshellarg($htmldocpath)." -t pdf $cmd -f ".escapeshellarg($file.".pdf")." ".escapeshellarg($file)."";
                    $obj = new COM("WScript.Shell");
                    if (substr($_SERVER["SERVER_SOFTWARE"],0,6) == "Apache") {
                        $Res = $obj->Run($exec, 0, true);
                    } else {
                        // for IIS
                        $Res = exec("".$exec);
                    }
                    // On Windows, PHP sometimes does not immediately recognize that the file is there.
                    // http://de.php.net/manual/en/function.file-exists.php#56121
                    $start = gettimeofday();
                    while (!file_exists($file.".pdf")) {
                        $stop = gettimeofday();
                        if ( 1000000 * ($stop['sec'] - $start['sec']) + $stop['usec'] - $start['usec'] > 500000) break;  // wait a moment
                    }

                    if (file_exists($file.".pdf")) {
                        $fd = fopen ($file.".pdf", "r");
                        $PDF = fgets($fd, 10);
                        fclose($fd);
                    }
                } catch (Exception $e) {
                    $execTestMessage = "<b>Command:</b><br>".$exec."<br><b>Error:</b><br>".$e->__toString();
                }
            } else {
                $exec  = escapeshellarg($htmldocpath)." -t pdf $cmd ".escapeshellarg($file)."";
                exec($exec, $PDFarr);
                $PDF = implode($PDFarr);
            }
            if (strpos($PDF, "%PDF") === false) {
            } else {
                $PDF = "OK";
                $execTest = true;
            }
            //if (file_exists($file))
            //unlink($file);
            //if (file_exists($file.".pdf"))
            //unlink($file.".pdf");
        }
        $output .= Status($execTest, wfMsg('checkpdfbookHtmlDocExecTest'), $execTestMessage."<br>".$exec);

        # TOTAL STATUS
        $output .= "<tr><td colspan='2' style='height: 2px'>&nbsp;</td></tr>";
        $output .= Status(($dirtouch) && ($dircheck) && ($htmldocpath) && ($htmldocexec) && ($execTest), wfMsg('checkpdfbookTotal'), "");
        $output .= "</table>";
        $wgOut->addHTML( $output );
    }
}
