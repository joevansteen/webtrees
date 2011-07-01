<?php
// Class that defines an event details object
//
// webtrees: Web based Family History software
// Copyright (C) 2011 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2008  PGV Development Team.  All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//
// $Id$

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

class WT_Event {
// These objects need further refinement in their implementations and parsing
// var $address = null;
// var $notes = array(); //[0..*]: string
// var $sourceCitations = array(); //[0..*]: SourceCitation
// var $multimediaLinks = array(); //[0..*]: MultimediaLink

	var $lineNumber = null;
	var $canShow = null;
	var $state = "";
	var $type = NULL;
	var $tag = NULL;
	var $date = NULL;
	var $place = null;
	var $gedcomRecord = null;
	var $resn = null;
	var $dest = false;
	var $label = null;
	var $parentObject = null;
	var $detail = NULL;
	var $values = NULL;
	var $sortOrder = 0;
	var $sortDate = NULL;
	//-- temporary state variable that can be used by other scripts
	var $temp = NULL;

	/**
	 * Get the value for the first given GEDCOM tag
	 *
	 * @param string $code
	 * @return string
	 */
	function getValue($code) {
		if (is_null($this->values)) {
			$this->values=array();
			preg_match_all('/\n2 ('.WT_REGEX_TAG.') (.+)/', $this->gedcomRecord, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				// If this is a link, remove the "@"
				if (preg_match('/^@'.WT_REGEX_XREF.'@$/', $match[2])) {
					$this->values[$match[1]]=trim($match[2], "@");
				} else {
					$this->values[$match[1]]=$match[2];
				}
			}
		}
		if (array_key_exists($code, $this->values)) {
			return $this->values[$code];
		}
		return null;
	}

	// Create an event objects from a gedcom fragment.
	// We also need to know the parent (to check privacy, etc.) and
	// the line number (from the original, privacy-filtered) gedcom
	// record, to allow editing
	function __construct($subrecord, $parent, $lineNumber) {
		if (preg_match('/^1 ('.WT_REGEX_TAG.') ?(.*)((\n2 CONT .*)*)/', $subrecord, $match)) {
			$this->tag   =$match[1];
			$this->detail=$match[2];
			// Some detail records contain multiple lines
			if ($match[3]) {
				$this->detail.=str_replace("\n2 CONT ", "\n", $match[3]);
			}
		} else {
			// We are not ready for this yet.
			// throw new Exception('Invalid GEDCOM data passed to WT_Event::_construct('.$subrecord.')');
		}
		$this->gedcomRecord=$subrecord;
		$this->parentObject=$parent;
		$this->lineNumber  =$lineNumber;
	}

	function setState($s) {
		$this->state = $s;
	}

	function getState() {
		return $this->state;
	}

	/**
	 * Check whether or not this event can be shown
	 *
	 * @return boolean
	 */
	function canShow() {
		if (is_null($this->canShow)) {
			if (empty($this->gedcomRecord)) {
				$this->canShow = false;
			} elseif (!is_null($this->parentObject)) {
				$this->canShow = canDisplayFact($this->parentObject->getXref(), $this->parentObject->getGedId(), $this->gedcomRecord);
			} else {
				$this->canShow = true;
			}
		}
		return $this->canShow;
	}

	// Check whether this fact is protected against edit
	public function canEdit() {
		// Managers can edit anything
		// Members cannot edit RESN, CHAN and locked records
		return
			$this->parentObject && $this->parentObject->canEdit() && (
				WT_USER_GEDCOM_ADMIN ||
				WT_USER_CAN_EDIT && strpos($this->gedcomRecord, "\n2 RESN locked")===false && $this->getTag()!='RESN' && $this->getTag()!='CHAN'
			);
	}

	/**
	 * The 4 character event type specified by GEDCom.
	 *
	 * @return string
	 */
	function getType() {
		if (is_null($this->type))
			$this->type=$this->getValue('TYPE');
		return $this->type;
	}

	/**
	 * The place where the event occured.
	 *
	 * @return string
	 */
	function getPlace() {
		if (is_null($this->place)) {
			$this->place=$this->getValue('PLAC');
		}
		return $this->place;
	}

	function getFamilyId() {
		return $this->getValue("_WTFS");
	}

	function getSpouseId() {
		return $this->getValue("_WTS");
	}

	/**
	 * Get the date object for this event
	 *
	 * @return WT_Date
	 */
	function getDate($estimate = true) {
		if (is_null($this->date))
			$this->date=new WT_Date($this->getValue('DATE'));

		if (!$estimate && $this->dest) return null;
		return $this->date;
	}

	/**
	 * Set the date of this event.  This method should only be used to force a date.
	 *
	 * @param WT_Date $date
	 */
	function setDate($date) {
		$this->date = $date;
		$this->dest = true;
	}

	/**
	 * The remaining unparsed GEDCom record
	 *
	 * @return string
	 */
	function getGedcomRecord() {
		return $this->gedcomRecord;
	}

	/**
	 * The line number, or line of occurrence in the GEDCom record.
	 *
	 * @return unknown
	 */
	function getLineNumber() {
		return $this->lineNumber;
	}

	/**
	 *
	 */
	function getTag() {
		return $this->tag;
	}

	/**
	 * The Person/Family record where this WT_Event came from
	 *
	 * @return GedcomRecord
	 */
	function getParentObject() {
		return $this->parentObject;
	}

	/**
	 *
	 */
	function getDetail() {
		return $this->detail;
	}

	/**
	 * Check whether this fact has information to display
	 * Checks for a date or a place
	 *
	 * @return boolean
	 */
	function hasDatePlace() {
		return ($this->getDate() || $this->getPlace());
	}

	function getLabel($abbreviate=false) {
		if ($abbreviate) {
			return WT_Gedcom_Tag::getAbbreviation($this->tag);
		} else {
			return WT_Gedcom_Tag::getLabel($this->tag, $this->parentObject);
		}
	}

	/**
	 * Print a simple fact version of this event
	 *
	 * @param boolean $return whether to print or return
	 * @param boolean $anchor whether to add anchor to date and place
	 */
	function print_simple_fact($return=false, $anchor=false) {
		global $SHOW_PEDIGREE_PLACES, $ABBREVIATE_CHART_LABELS;

		if (!$this->canShow()) return "";
		$data = "";
		if ($this->gedcomRecord != "1 DEAT") {
		   $data .= "<span class=\"details_label\">".$this->getLabel($ABBREVIATE_CHART_LABELS)."</span> ";
		}
		$emptyfacts = array("BIRT","CHR","DEAT","BURI","CREM","ADOP","BAPM","BARM","BASM","BLES","CHRA","CONF","FCOM","ORDN","NATU","EMIG","IMMI","CENS","PROB","WILL","GRAD","RETI","BAPL","CONL","ENDL","SLGC","EVEN","MARR","SLGS","MARL","ANUL","CENS","DIV","DIVF","ENGA","MARB","MARC","MARS","OBJE","CHAN","_SEPR","RESI", "DATA", "MAP");
		if (!in_array($this->tag, $emptyfacts))
			$data .= PrintReady($this->detail);
		if (!$this->dest)
			$data .= format_fact_date($this, $anchor, false, true);
		$data .= format_fact_place($this, $anchor, false, false);
		$data .= "<br />";
		if (!$return) echo $data;
		else return $data;
	}

	// Display an icon for this fact.
	// Icons are held in a theme subfolder.  Not all themes provide icons.
	function Icon() {
		$dir=WT_THEME_DIR.'images/facts/';
		$tag=$this->getTag();
		$file=$tag.'.png';
		if (file_exists($dir.$file)) {
			return '<img src="'.$dir.$file.'" title="'.WT_Gedcom_Tag::getLabel($tag).'" align="middle"/>';
		} elseif (file_exists($dir.'NULL.png')) {
			// Spacer image - for alignment - until we move to a sprite.
			return '<img src="'.$dir.'NULL.png" align="middle" />';
		} else {
			return '';
		}
	}

	/**
	 * Static Helper functions to sort events
	 *
	 * @param WT_Event $a
	 * @param WT_Event $b
	 * @return int
	 */
	static function CompareDate($a, $b) {
		$adate = $a->getDate();
		$bdate = $b->getDate();
		//-- non-dated events should sort according to the preferred sort order
		if (is_null($adate) && !is_null($a->sortDate)) {
			$ret = $a->sortOrder - $b->sortOrder;
		} elseif (is_null($bdate) && !is_null($b->sortDate)) {
			$ret = $a->sortOrder - $b->sortOrder;
		} else {
			$ret = WT_Date::Compare($adate, $bdate);
		}
		if ($ret==0) {
			$ret = $a->sortOrder - $b->sortOrder;
			//-- if dates are the same they should be ordered by their fact type
			if ($ret==0) {
				$ret = WT_Event::CompareType($a, $b);
			}
		}
		return $ret;
	}

	/**
	 * Static method to Compare two events by their type
	 *
	 * @param WT_Event $a
	 * @param WT_Event $b
	 * @return int
	 */
	static function CompareType($a, $b) {
		global $factsort;

		if (empty($factsort))
			$factsort=array_flip(array(
				"BIRT",
				"_HNM",
				"ALIA", "_AKA", "_AKAN",
				"ADOP", "_ADPF", "_ADPF",
				"_BRTM",
				"CHR", "BAPM",
				"FCOM",
				"CONF",
				"BARM", "BASM",
				"EDUC",
				"GRAD",
				"_DEG",
				"EMIG", "IMMI",
				"NATU",
				"_MILI", "_MILT",
				"ENGA",
				"MARB", "MARC", "MARL", "_MARI", "_MBON",
				"MARR", "MARR_CIVIL", "MARR_RELIGIOUS", "MARR_PARTNERS", "MARR_UNKNOWN", "_COML",
				"_STAT",
				"_SEPR",
				"DIVF",
				"MARS",
				"_BIRT_CHIL",
				"DIV", "ANUL",
				"_BIRT_", "_MARR_", "_DEAT_","_BURI_", // other events of close relatives
				"CENS",
				"OCCU",
				"RESI",
				"PROP",
				"CHRA",
				"RETI",
				"FACT", "EVEN",
				"_NMR", "_NMAR", "NMR",
				"NCHI",
				"WILL",
				"_HOL",
				"_????_",
				"DEAT", "CAUS",
				"_FNRL", "BURI", "CREM", "_INTE", "CEME",
				"_YART",
				"_NLIV",
				"PROB",
				"TITL",
				"COMM",
				"NATI",
				"CITN",
				"CAST",
				"RELI",
				"SSN", "IDNO",
				"TEMP",
				"SLGC", "BAPL", "CONL", "ENDL", "SLGS",
				"ADDR", "PHON", "EMAIL", "_EMAIL", "EMAL", "FAX", "WWW", "URL", "_URL",
				"AFN", "REFN", "_PRMN", "REF", "RIN", "_UID",
				"CHAN", "_TODO",
				"NOTE", "SOUR", "OBJE"
			));

		// Facts from same families stay grouped together
		// Keep MARR and DIV from the same families from mixing with events from other FAMs
		// Use the original order in which the facts were added
		if ($a->getFamilyId() && $b->getFamilyId() && $a->getFamilyId()!=$b->getFamilyId()) {
			return $a->sortOrder - $b->sortOrder;
		}

		$atag = $a->getTag();
		$btag = $b->getTag();

		// Events not in the above list get mapped onto one that is.
		if (!array_key_exists($atag, $factsort)) {
			if (preg_match('/^(_(BIRT|MARR|DEAT|BURI)_)/', $atag, $match)) {
				$atag=$match[1];
			} else {
				$atag="_????_";
			}
		}
		if (!array_key_exists($btag, $factsort)) {
			if (preg_match('/^(_(BIRT|MARR|DEAT|BURI)_)/', $btag, $match)) {
				$btag=$match[1];
			} else {
				$btag="_????_";
			}
		}

		//-- don't let dated after DEAT/BURI facts sort non-dated facts before DEAT/BURI
		//-- treat dated after BURI facts as BURI instead
		if ($a->getValue('DATE')!=NULL && $factsort[$atag]>$factsort['BURI'] && $factsort[$atag]<$factsort['CHAN']) $atag='BURI';
		if ($b->getValue('DATE')!=NULL && $factsort[$btag]>$factsort['BURI'] && $factsort[$btag]<$factsort['CHAN']) $btag='BURI';
		$ret = $factsort[$atag]-$factsort[$btag];
		//-- if facts are the same then put dated facts before non-dated facts
		if ($ret==0) {
			if ($a->getValue('DATE')!=NULL && $b->getValue('DATE')==NULL) return -1;
			if ($b->getValue('DATE')!=NULL && $a->getValue('DATE')==NULL) return 1;
			//-- if no sorting preference, then keep original ordering
			$ret = $a->sortOrder - $b->sortOrder;
		}
		return $ret;
	}
}
