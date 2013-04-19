<?php
/**
 * (c) 2013 by http://www.keepitnative.ch
 * all rights reserved
 * @file write_jf_menus_to_menus.php
 * @author ps
 */

//change affected table here
define('REF_TABLE', 'menu');

//include mainframe db etc..
require_once ('ajax.php');
require_once ('../classes/migrate_helper.php');

class write_jf_menus_to_menus extends db {
	/**
	 * @file write_jf_menus_to_menus.php
	 * @user ps
	 * migrates menu translations in jf_content to native joomla menu entries
	 */

	/**
	 * sum of menus entries migrated
	 * @var $counterEntries
	 */
	var $counterEntries = 0;

	public function __construct() {
		parent::__construct();
		$this -> get_records();
	}

	public function get_records() {
		/**
		 * @return void
		 * gets records in order of reference id
		 * and calls group function
		 */
		$data_reform = array();
		$query = "SELECT  *  FROM `" . $this -> pfx_before . $this -> table . "` WHERE reference_table= '" . REF_TABLE . "' GROUP BY reference_id ASC, reference_field ASC";

		if ($result = $this -> mysqli -> query($query)) {

			/* fetch object array */
			while ($row = $result -> fetch_object()) {
				$fields = array($row -> id, $row -> language_id, $row -> reference_id, $row -> reference_table, $row -> reference_field, $row -> value, $row -> original_value, $row -> original_text, $row -> modified, $row -> modified_by, $row -> published);
				foreach ($fields as $k => $v) {
					$fields[$k] = $this -> mysqli -> real_escape_string($v);
				}
				$data_reform[] = implode('--', $fields);
			}
			$this -> group($data_reform);
			//call group function
			/* free result set */
			$result -> close();
		}
		/* close connection */
		$this -> mysqli -> close();
	}

	public function group($recordset) {
		/**
		 * reorder records form 'x' to 'y' axis
		 * and call save_sql function
		 * @return void
		 */
		$out = array();
		$counter = 1;

		$nr_of_same_ref_ids = migrate_helper::get_amount_of_same_ref_id($recordset);
		//get amount of records with same id from helper
		$same_ref_id = 0;
		//get counter for array to have same record through same ref_id
		for ($line_nr = 0; $line_nr < count($recordset); $line_nr++) {//loop trough lines

			$rows = explode("--", $recordset[$line_nr]);

			//get lang id
			$out[$same_ref_id]['lang'] = $rows[1];

			//get rows of a line
			switch( $rows[4] ) {
				case 'title' :
					$out[$same_ref_id]['title'] = $rows[5];
					break;
				case 'alias' :
					$out[$same_ref_id]['alias'] = $rows[2];
					break;

				case 'link' :
					$out[$same_ref_id]['link'] = $rows[5];
					break;
				case 'params' :
					$params = migrate_helper::jsonifyParams($rows[5]);
					$params = $this -> mysqli -> real_escape_string($params);
					$out[$same_ref_id]['params'] = $params;
					break;
			}
			if (count($recordset) > $line_nr + 1) {//@FIXME don' take last entry
				preg_match('/^(.*?)([0-9])--([0-9])--([0-9])(.*)$/s', $recordset[$line_nr + 1], $matches);
			}
			if (count($matches) > 3) {
				$ref_id_next = $matches[4];
				//echo $rows[2]."  ==   ".$ref_id_next." ---- ".$same_ref_id."--------".$rows[4]."-----------"."<br />";
				if ($rows[2] != $ref_id_next) {
					$same_ref_id++;
				}
			}
		}
		for ($nr = 0; $nr < count($out); $nr++) {//reorder to output array
			if( isset($out[$nr]['title'])) {
				$title = $out[$nr]['title'];
			} else {
				$title = '';
			}
			if( isset($out[$nr]['alias'])) {
				$alias = $out[$nr]['alias'];
			} else {
				$alias = '';
			}
			if( isset($out[$nr]['link'])) {
				$link = $out[$nr]['link'];
			} else {
				$link = '';
			}
			if( isset($out[$nr]['params'])) {
				$params = $out[$nr]['params'];
			} else {
				$params = '';
			}
			if( isset($out[$nr]['lang'])) {
				$lang = $out[$nr]['lang'];
			} else {
				$lang = -1;
			}			
			
			if ($title != '' || $params != '') {
				$counter++;
				//call save function
				$error = $this -> save_sql($title, $alias, $link, $params, $lang);
			}
		}
		if ($error == false) {
			echo '<p class="clear" style="color:rgb(0,128,0);"><span>' . $this -> counterEntries . 'menu entries successfully inserted!</span></p><br />';
		} else {
			echo "<p class='clear' style='color:rgb(255,255,255);'><span>Failed to run query: (" . $this -> mysqli -> errno . ") " . $this -> mysqli -> error . "</span></p>";
		}
	}

	public function save_sql($title, $alias, $link, $params, $lang) {
		/**
		 * write reorderd records to menu table
		 */
		//title, alias, link, params, $lang
		switch( $lang ) {
			case 1 :
				$lang = 'en-GB';
				break;
			case 2 :
				$lang = 'de-DE';
				break;
			case 3 :
				$lang = 'fr-FR';
				break;
			case 4 :
				$lang = 'it-IT';
				break;
		}
		//set unique client_id for unique index bug
		$counter = intval($this -> counterEntries);
		
		//build query
		$sql = '';
		$sql = "INSERT INTO `" . $this -> database . "`.`" . $this -> pfx . REF_TABLE . "` (";
		$sql .= "`id`, `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `ordering`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES";
		$sql .= "('', 'mainmenu', '" . $title . "', '" . $alias . "-".$counter."', '', '" . $alias . "','" . $link . "', 'component', 1, 1, 1, 22, 6, 0, '0000-00-00 00:00:00', 0, 1, '', 0, '" . $params . "', 101, 116, 0, '" . $lang . "', $counter)";
		//echo $sql;exit;
		$res = mysqli_query($this -> mysqli, $sql);

		if (!$res) {
			return true;
			/*error*/
		} else {
			$this -> counterEntries++;
			return false;
		}
	}

}

//init menu migration
new write_jf_menus_to_menus();
