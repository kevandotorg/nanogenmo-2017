<?php

include "tables.php";

convertPlay("http://shakespeare.mit.edu/merchant/full.html",telegramTable(),
			"Tillman","Merchant of Venice",
			"the <a href=\"http://www.gutenberg.org/ebooks/48232\">1897 Robinson Telegraphic Cipher</a>");
convertPlay("http://shakespeare.mit.edu/tempest/full.html",flagsTable(),
			"Victor Lima","The Tempest",
			"the <a href=\"http://bibliotheque-des-usages.cde-montpellier.com/sites/default/files/usages/catalogue/International_Code_of_signals.pdf\">International Code of Maritime Signals, 1969 edition</a>");
convertPlay("http://shakespeare.mit.edu/romeo_juliet/full.html",acronymTable(),
			"KOD","Romeo and Juliet",
			"<a href=\"http://www.oocities.org/eedd88/abbreviations.html\">Ed's Chat, Usenet, and Text Message Acronyms and Abbreviations</a>");

function convertPlay($play,$lookupTable,$newtitle,$oldtitle,$reference)
{
	$speakingBlank = ""; $speakingCouplet = "";
	$speechCount = 0; $coupletCount = 0; $inCouplets = 0;

	$inputfile = fopen($play,"r");
	$outputfile = fopen($newtitle.".html","w");

	//for ($i=0; $i<50; $i++)
	while(! feof($inputfile))
	{
	  $originalLine = trim(fgets($inputfile));

	  // Replace title and title box.
	  if (preg_match("/<title>.+: Entire Play/",$originalLine))
	  { $originalLine = "<title>$newtitle ($oldtitle)"; }
	  if (preg_match("/table width/",$originalLine))
	  { $originalLine = "<div style=\"background:#CCF6F6; text-align:center; padding:8px\">
							<h1 style=\"margin:8px\"><i>$newtitle</i></h1><div>William Shakespeare's <i>$oldtitle</i>, told through $reference.<br/>Autogenerated from <a href=\"$play\">the original text</a> fed through a script by <a href=\"http://kevan.org\">Kevan Davis</a> for <a href=\"https://github.com/NaNoGenMo/2017\">NaNoGenMo 2017</a>.</div>
						</div>"; }
	  if (preg_match("/(Shakespeare homepage|A href|Entire play|<tr>|<\/table>)/",$originalLine))
	  { $originalLine = ""; }

	  // Clean up some misformatted lines noticed in original texts.  
	  $originalLine = str_replace("On a ship at sea: a tempestuous noise","On a ship at sea: a tempestuous noise of thunder and lightning heard.",$originalLine);	  
	  if (preg_match("/>of thunder and lightning heard.</",$originalLine))
	  { $originalLine = ""; }
	  if (preg_match("/>ARIEL'S song.</",$originalLine))
	  { $originalLine = "<p><i>ARIEL'S song.</i></p>"; }
	  if (preg_match("/>EPILOGUE</",$originalLine))
	  { $originalLine = "</blockquote><a name=\"speechxx\"><b>EPILOGUE</b></a><br/>"; }
	  if (preg_match("/>SPOKEN BY PROSPERO</",$originalLine))
	  { $originalLine = "<a name=\"speechxx\"><b>SPOKEN BY PROSPERO</b></a><blockquote>"; }
  	  
	  if (preg_match("/<A NAME=(\d+\.\d+\.\d+)>([^<]+)<\/A><br>/",$originalLine,$matches))
	  {
		$speakingBlank .= $matches[2]." ";
		$speakingCouplet .= $matches[2]."<couplet>";
		$speechCount++;
		if (preg_match("/[\.,?!:;\-]$/",$matches[2]))
		{ $coupletCount++; }
	  }
	  else
	  {
		if ($coupletCount>($speechCount/2) && $speechCount>2)
		{
			$speechArray = preg_split("/<couplet>/", $speakingCouplet, -1);
			foreach($speechArray as $speechLine)
			{
				if ($speechLine != "")
				{
					$speechArray2 = preg_split("/((?:\.|\?|!|:|;|--)+)/", $speechLine, -1, PREG_SPLIT_DELIM_CAPTURE);
					$speechToPrint = ""; $finalSpeech = "";

					foreach($speechArray2 as $speechLine2)
					{
						if (preg_match("/^([\.?!:;\-]+)$/",$speechLine2))
						{	
							fwrite($outputfile,convertLine($speechToPrint.$speechLine2,$lookupTable)." ");
						}
						else	
						{ $speechToPrint = $speechLine2; }
					}
					if ($speechToPrint != "")
					{ fwrite($outputfile,convertLine($speechToPrint,$lookupTable)); }
					fwrite($outputfile,"<br />\n");
				}
			}
		}
		else
		{
			$speechArray = preg_split("/((?:\.|\?|!|:|;|--)+)/", $speakingBlank, -1, PREG_SPLIT_DELIM_CAPTURE);
			$speechToPrint = ""; $finalSpeech = "";
			foreach($speechArray as $speechLine)
			{
				if (preg_match("/^([\.?!:;\-]+)$/",$speechLine))
				{
					$finalSpeech .= convertLine($speechToPrint.$speechLine,$lookupTable)."<newline>";
				}
				else
				{ $speechToPrint = $speechLine; }
			}
			if ($finalSpeech != "")
			{
				if ($coupletCount>($speechCount/2) && $speechCount>2)
				{
					$finalSpeech = preg_replace_callback("/<newline>([a-z])/","ucline",$finalSpeech); 
					$finalSpeech = str_replace("<newline>","<br />",$finalSpeech); 
					fwrite($outputfile,"<A>".wordwrap($finalSpeech, 60, "<br />")."</A>\n");
				}
				else
				{
					$finalSpeech = str_replace("<newline>"," ",$finalSpeech);
					fwrite($outputfile,"<A>".wordwrap($finalSpeech, 60, "<br />")."</A>\n");
				}
				
			}
		}

		$speakingBlank = "";
		$speakingCouplet = "";
		$speechCount = 0;
		$coupletCount = 0;
		fwrite($outputfile,$originalLine."\n");
	  }
	}

	fclose($inputfile);
	fclose($outputfile);
}

function ucline($matches) {
  return "<br />".strtoupper($matches[0]);
}

function convertLine($feedline,$table)
{
	$direction = "";
	if (preg_match("/^\[([^\]]+)\] /",$feedline,$matches))
	{
		$feedline = preg_replace("/^\[[^\]]+\] /","",$feedline);
		$direction = "[".$matches[1]."] ";
	}
	
	if (strlen($feedline)>255)
	{ $feedline = substr($feedline,0,250); }

	$bestline = "..."; $bestscore = 0;
	foreach ($table as $tryline)
	{
		$fitness = 1000-levenshtein($feedline,$tryline);
		
		if ($fitness>$bestscore && $feedline <> $tryline && !(substr($feedline, -1) == "?" && substr($tryline, -1) != "?") && !(substr($feedline, -1) != "?" && substr($tryline, -1) == "?"))
		{
			$bestline = $tryline;
			$bestscore = $fitness;
			
			$feedcloser = substr($feedline, -1);
			
			if (preg_match("/(!|;|:|-|,)/",$feedcloser) && preg_match("/[.!]$/",$bestline))
			{
				$bestline = substr($bestline, 0, -1).$feedcloser;
				if ($feedcloser == "-") { $bestline .= "-"; }
			}
			elseif (substr($feedline, -1) == "!"  && substr($bestline, -1) != "!")
			{
				$bestline = $bestline."!";
			}
		}
	}

	if (preg_match("/^ [a-z]/",$feedline) && !preg_match("/^I[' ]/",$bestline) && !preg_match("/^(No.|St.|January|February|March|April|May|June|July|August|September|October|November|December|Canadian|Canada|Chicago|Illinois|Kansas|Michigan|Milwaukee|New York|Pittsburg|Pennsylvania|Cincinnati|Cleveland|Delaware|Denver|Duluth|Fitchburg|Flint|Grand|Great|Indiana|Iowa|Kansas|Kewaunee|Lake|Lehigh|Louisville|Maine|Memphis|Minneapolis|Minneapolis|Missouri|Missouri|Mobile|Nashville|Ohio|Northern|Omaha|Philadelphia|Rome|Southern|Southern|Texas|Toledo|Texas|Union|Vandalia|Wabash|West|Wisconsin|Erie|Kankakee|Hoosac|Inter|Kanawha|Lackawanna|Lehigh|Ontario|Sarnia|Union|Lackawanna|Lehigh|Minneapolis|Ogdensburg|Bremen|Cunard|Guion|Hamburg|Havre|Inman|Galveston|Rutland|Wheeling|Yazoo|Cumberland|Jersey|Neptune|Pacific|Philadelphia|Scandinavian|United|Virginia|Broomhall)/",$bestline))
	{ $bestline = lcfirst($bestline); }

	return $direction.$bestline;
}

?>
