<?php

//////////////////////////////////////////////////////////////
//
//    Copyright: Author: [http://www.organicdesign.co.nz/nad User:Nad]
//               Started: 2007-08-08
//    Modifications Copyright by Thomas Kock, Delmenhorst, 2008, 2009
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

if (!defined('MEDIAWIKI')) die('Not an entry point.');

define('PDFBOOK_VERSION','0.0.11, 2008-05-30');

global $PdfBookShowTab, $PdfBookCodePage;	
$PdfBookShowTab		= false;
$PdfBookCodePage	= "";


$dir = dirname(__FILE__) . '/';
require_once($dir.'version.php');
require_once($dir.'checkpdfbook.php');
$wgExtensionMessagesFiles['pdfbook'] = $dir.'pdfbook.i18n.php';
$wgPdfBookMagic                = "book";
$wgExtensionFunctions[]        = 'wfSetupPdfBook';
$wgHooks['LanguageGetMagic'][] = 'wfPdfBookLanguageGetMagic';

$wgExtensionCredits['parserhook'][] = array(
    'name'	      => 'Pdf Book',
    'author'      => '[http://www.organicdesign.co.nz/nad User:Nad]',
    'description' => 'Composes a book from articles in a category and exports as a PDF book',
    'url'	      => 'http://www.mediawiki.org/wiki/Extension:Pdf_Book',
    'version'     => PDFBOOK_VERSION
);

class PdfBook {

    # Constructor
    function PdfBook() {
        global $wgHooks,$wgParser,$wgPdfBookMagic;
        $wgParser->setFunctionHook($wgPdfBookMagic,array($this,'magicBook'));
        $wgHooks['UnknownAction'][] = $this;

        # Add a new pdf log type
        global $wgLogTypes,$wgLogNames,$wgLogHeaders,$wgLogActions;
        $wgLogTypes[]             = 'pdf';
        $wgLogNames  ['pdf']      = 'pdflogpage';
        $wgLogHeaders['pdf']      = 'pdflogpagetext';
        $wgLogActions['pdf/book'] = 'pdflogentry';
    }

    # Expand the book-magic
    function magicBook(&$parser) {

        # Populate $argv with both named and numeric parameters
        $argv = array();
        foreach (func_get_args() as $arg) if (!is_object($arg)) {
            if (preg_match('/^(.+?)\\s*=\\s*(.+)$/',$arg,$match)) $argv[$match[1]] = $match[2]; else $argv[] = $arg;
        }

        return $text;
    }

    function onUnknownAction($action,$article) {
        global $wgOut,$wgUser,$wgTitle,$wgParser;
        global $wgServer,$wgArticlePath,$wgScriptPath,$wgUploadPath,$wgUploadDirectory,$wgScript;
        global $wgDBprefix;
        global $PdfBookCodePage;

        if ($action == 'pdfbook') {

            # Log the export
            $msg = $wgUser->getUserPage()->getPrefixedText().' exported as a PDF book';
            $log = new LogPage('pdf',false);
            $log->addEntry('book',$wgTitle,$msg);

            # Initialise PDF variables
            $layout  = '--firstpage toc';
            $left    = $this->setProperty('LeftMargin',  '1cm');
            $right   = $this->setProperty('RightMargin', '1cm');
            $top     = $this->setProperty('TopMargin',   '1cm');
            $bottom  = $this->setProperty('BottomMargin','1cm');
            $font    = $this->setProperty('Font',	'Arial');
            $size    = $this->setProperty('FontSize',    '8');
            $link    = $this->setProperty('LinkColour',  '217A28');
            $levels  = $this->setProperty('TocLevels',   '2');
            $exclude = $this->setProperty('Exclude',     array());
            if (!is_array($exclude)) $exclude = split('\\s*,\\s*',$exclude);

            # Select articles from members if a category or links in content if not
            $articles = array();
            $title    = $article->getTitle();
            $opt      = ParserOptions::newFromUser($wgUser);
            if ($title->getNamespace() == NS_CATEGORY) {
                $db     = &wfGetDB(DB_SLAVE);
                $cat    = $db->addQuotes($title->getDBkey());
                $cat2   = str_replace(" ","_",$db->strencode($wgTitle->mTextform));
                $result = $db->query("select page_id from  ".$wgDBprefix."page where page_namespace=14 and page_title='".$cat2."'");
                while ($row = $db->fetchObject($result)) {
                    //				    print($row->page_id."\n");
                    $articles[str_pad(0, 10, "0", STR_PAD_LEFT)."_".$row->page_id] = Title::newFromID($row->page_id);
                }
                //if ($result instanceof ResultWrapper) $result = $result->result;
                //		    	        $articles[str_pad(0, 10, "0", STR_PAD_LEFT)."_".$article->getID()] = Title::newFromID($article->getID());
                $result = $db->query("select cl_from, cl_sortkey, to_title as level from  ".$wgDBprefix."categorylinks left outer join ".$wgDBprefix."fchw_relation on (from_id = cl_from) where cl_to = $cat and ((relation is null) or (upper(relation) = 'LEVEL')) group by cl_from, cl_sortkey, level");
                while ($row = $db->fetchObject($result)) {
                    //				    print($row->cl_from."\n");
                    $articles[str_pad($row->level, 10, "0", STR_PAD_LEFT)."_".$row->cl_from] = Title::newFromID($row->cl_from);
                }
                //				die();
                ksort($articles);
            }
            else {
                $text = $article->fetchContent();
                $text = $wgParser->preprocess($text,$title,$opt);
                if (preg_match_all('/^\\*\\s*\\[{2}\\s*([^\\|\\]]+)\\s*.*?\\]{2}/m',$text,$link))
                foreach ($links[1] as $link) $articles[] = Title::newFromText($link);
            }

            # Format the article's as a single HTML document with absolute URL's
            $book	  = $title->getText();
            $html	  = '';
            $wgArticlePath = $wgServer.$wgArticlePath;
            $wgScriptPath  = $wgServer.$wgScriptPath;
            $wgUploadPath  = $wgServer.$wgUploadPath;
            $wgScript      = $wgServer.$wgScript;
            foreach ($articles as $title) {
                $ttext = $title->getPrefixedText();
                if (!in_array($ttext,$exclude)) {
                    $article = new Article($title);
                    $text    = $article->fetchContent();
                    $text    = preg_replace('/<!--([^@]+?)-->/s','@@'.'@@$1@@'.'@@',$text); # preserve HTML comments
                    $text   .= '__NOTOC__';
                    $opt->setEditSection(false);    # remove section-edit links
                    $wgOut->setHTMLTitle($ttext);   # use this so DISPLAYTITLE magic works
                    $out     = $wgParser->parse($text,$title,$opt,true,true);
                    $ttext   = $wgOut->getHTMLTitle();
                    $text    = $out->getText();
                    $text    = preg_replace('|(<img[^>]+?src=")(/.+?>)|',"$1$wgServer$2",$text);
                    $text    = preg_replace('|@{4}([^@]+?)@{4}|s','<!--$1-->',$text); # HTML comments hack
                    $text    = preg_replace('|<table|','<table border borderwidth=2 cellpadding=3 cellspacing=0',$text);
                    $ttext   = basename($ttext);
                    if ($PdfBookCodePage == "")
                    $html   .= utf8_decode("<h1>$ttext</h1>$text\n");
                    else
                    if ($PdfBookCodePage == "iso-8859-2")
                    $html   .= UTF8toISO88592("<h1>$ttext</h1>$text\n");
                    else
                    die("Codepage $PdfBookCodePage is not supported");
                }
            }

            # If format=html in query-string, return html content directly
            if (isset($_REQUEST['format']) && $_REQUEST['format'] == 'html') {
                $wgOut->disable();
                header("Content-Type: text/html");
                header("Content-Disposition: attachment; filename=\"$book.html\"");
                print $html;
            }
            else {
                # Write the HTML to a tmp file
                $file = "$wgUploadDirectory/pdfbook/".uniqid('pdf-book-');
                $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
                $fh = fopen($file,'w+');
                fwrite($fh,$html);
                fclose($fh);

                $charset = "";
                if ($PdfBookCodePage != "")
                $charset = " --charset $PdfBookCodePage ";

                # Send the file to the client via htmldoc converter
                $wgOut->disable();
                header("Content-Type: application/pdf");
                header("Content-Disposition: attachment; filename=\"$book.pdf\"");
                $cmd  = "--left $left --right $right --top $top --bottom $bottom";
                //$cmd .= " --header ... --footer .1. --headfootsize 8 --quiet --jpeg --color";
                $cmd .= " --headfootsize 8 --quiet --jpeg --color";
                $cmd .= " --bodyfont $font --fontsize $size --linkstyle plain --linkcolor $link";
                $cmd .= " --toclevels $levels --format pdf14 --numbered $layout";
                //$cmd  = "htmldoc -t pdf $charset $cmd $file";
                $file2  = str_replace('/', DIRECTORY_SEPARATOR, $file);
                //				echo($cmd);
                global $PdfBookHtmlDoc;
                if (!isset($PdfBookHtmlDoc))
                $htmldocpath = "htmldoc";
                else
                $htmldocpath = $PdfBookHtmlDoc;
                putenv("HTMLDOC_NOCGI=1");
                if (substr(php_uname(), 0, 7) == "Windows") {
                    $exec  = escapeshellarg($htmldocpath)." -t pdf $charset $cmd -f ".escapeshellarg($file2.".pdf")." ".escapeshellarg($file2)."";
                    $obj = new COM("WScript.Shell");
                    if (substr($_SERVER["SERVER_SOFTWARE"],0,6) == "Apache") {
                        $obj->Run($exec, 0, true);
                    } else {
                        // for IIS
                        $Res = $obj->Exec("".$exec);
                    }
                    // On Windows, PHP sometimes does not immediately recognize that the file is there.
                    // http://de.php.net/manual/en/function.file-exists.php#56121
                    $start = gettimeofday();
                    while (!file_exists($file2.".pdf")) {
                        $stop = gettimeofday();
                        if ( 1000000 * ($stop['sec'] - $start['sec']) + $stop['usec'] - $start['usec'] > 500000) break;  // wait a moment
                    }
                    readfile($file2.".pdf");
                } else {
                    $exec  = escapeshellarg($htmldocpath)." -t pdf $charset $cmd ".escapeshellarg($file2)."";
                    passthru($exec);
                }
                @unlink($file2);
                @unlink($file2.".pdf");
            }
            return false;
        }

        return true;
    }

    # Return a property for htmldoc using global, request or passed default
    function setProperty($name,$default) {
        if (isset($_REQUEST["pdf$name"]))      return $_REQUEST["pdf$name"];
        if (isset($GLOBALS["wgPdfBook$name"])) return $GLOBALS["wgPdfBook$name"];
        return $default;
    }

    # Needed in some versions to prevent Special:Version from breaking
    function __toString() { return 'PdfBook'; }
}

# Called from $wgExtensionFunctions array when initialising extensions
function wfSetupPdfBook() {
    global $wgPdfBook, $wgHooks, $wgMessageCache, $PdfBookShowTab;
    $wgPdfBook = new PdfBook();
    if ($PdfBookShowTab) {
        wfLoadExtensionMessages('pdfbook');
        $wgMessageCache->addMessage('pdfbook', wfMsg('pdfbook_ExportPDF'));
        $wgHooks['SkinTemplateContentActions'][] = 'wfPdfBookActionContentHook';
    }
}

# Needed in MediaWiki >1.8.0 for magic word hooks to work properly
function wfPdfBookLanguageGetMagic(&$magicWords,$langCode = 0) {
    global $wgPdfBookMagic;
    $magicWords[$wgPdfBookMagic] = array(0,$wgPdfBookMagic);
    return true;
}

# show pdfbok tab on category pages
function wfPdfBookActionContentHook( &$content_actions ) {
    global $wgRequest, $wgRequest, $wgTitle;
    $action = $wgRequest->getText('action');
    global $wgPdfBook;
    if (!isset($wgPdfBook))
    return true;
    if ( $wgTitle->getNamespace() != NS_SPECIAL ) {
        if ($wgTitle->getNamespace() == NS_CATEGORY) {
            $content_actions['myact'] = array(
            'class' => $action == 'pdfbook' ? 'selected' : false,
                'text' => wfMsg('pdfbook'),
            'href' => $wgTitle->getLocalUrl('action=pdfbook')
            );
        }
    }
    return true;
}

static $UTF8_ISO88592 = array(
        "\xC3\x81" => "�",
        "\xC3\x84" => "�",
        "\xC4\x86" => "�",
        "\xC4\x8C" => "�",
        "\xC4\x8E" => "�",
        "\xC3\x89" => "�",
        "\xC4\x9A" => "�",
        "\xC3\x8D" => "�",
        "\xC4\xBB" => "�",
        "\xC4\xBD" => "�",
        "\xC5\x87" => "�",
        "\xC3\x93" => "�",
        "\xC3\x94" => "�",
        "\xC3\x96" => "�",
        "\xC5\x94" => "�",
        "\xC5\x98" => "�",
        "\xC5\xA0" => "�",
        "\xC5\xA4" => "�",
        "\xC3\x9A" => "�",
        "\xC5\xAE" => "�",
        "\xC3\x9C" => "�",
        "\xC3\x9D" => "�",
        "\xC5\xBD" => "�",
        "\xC3\x9F" => "�",
        "\xC3\xA1" => "�",
        "\xC3\xA4" => "�",
        "\xC4\x87" => "�",
        "\xC4\x8D" => "�",
        "\xC4\x8F" => "�",
        "\xC3\xA9" => "�",
        "\xC4\x9B" => "�",
        "\xC3\xAD" => "�",
        "\xC4\xBA" => "�",
        "\xC4\xBE" => "�",
        "\xC5\x88" => "�",
        "\xC3\xB3" => "�",
        "\xC3\xB4" => "�",
        "\xC3\xB6" => "�",
        "\xC5\x95" => "�",
        "\xC5\x99" => "�",
        "\xC5\xA1" => "�",
        "\xC5\xA5" => "�",
        "\xC3\xBA" => "�",
        "\xC5\xAF" => "�",
        "\xC3\xBC" => "�",
        "\xC3\xBD" => "�",
        "\xC5\xBE" => "�"
);

function UTF8toISO88592($input) {
    global $UTF8_ISO88592;
    return strtr($input, $UTF8_ISO88592);
}