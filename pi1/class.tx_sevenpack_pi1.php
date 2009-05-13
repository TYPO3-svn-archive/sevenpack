<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Sebastian Holtermann (sebholt@web.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is 
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Plugin 'Publication List' for the 'sevenpack' extension.
 *
 * @author	Sebastian Holtermann <sebholt@web.de>
 * @package TYPO3
 * @subpackage tx_sevenpack
 *
 */


require_once ( PATH_tslib.'class.tslib_pibase.php' );

require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
	'EXT:sevenpack/res/class.tx_sevenpack_reference_accessor.php' ) );

require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
	'EXT:sevenpack/res/class.tx_sevenpack_utility.php' ) );

class tx_sevenpack_pi1 extends tslib_pibase {

	public $prefixId = 'tx_sevenpack_pi1';		// Same as class name
	public $scriptRelPath = 'pi1/class.tx_sevenpack_pi1.php';	// Path to this script relative to the extension dir.
	public $extKey = 'sevenpack';	// The extension key.

	public $pi_checkCHash = TRUE;

	public $prefixShort = 'tx_sevenpack';	// Get/Post variable prefix.
	public $prefix_pi1 = 'tx_sevenpack_pi1';		// pi1 prefix id

	// Enumeration for list modes
	public $D_SIMPLE  = 0;
	public $D_Y_SPLIT = 1;
	public $D_Y_NAV   = 2;

	// Enumeration for view modes
	public $VIEW_LIST   = 0;
	public $VIEW_SINGLE = 1;
	public $VIEW_DIALOG = 2;

	// Single view modes
	public $SINGLE_SHOW = 0;
	public $SINGLE_EDIT = 1;
	public $SINGLE_NEW  = 2;
	public $SINGLE_CONFIRM_SAVE   = 3;
	public $SINGLE_CONFIRM_DELETE = 4;
	public $SINGLE_CONFIRM_ERASE  = 5;

	// Various dialog modes
	public $DIALOG_SAVE_CONFIRMED   = 1;
	public $DIALOG_DELETE_CONFIRMED = 2;
	public $DIALOG_ERASE_CONFIRMED  = 3;
	public $DIALOG_EXPORT           = 4;
	public $DIALOG_IMPORT           = 5;

	// Enumeration style in the list view
	public $ENUM_PAGE   = 1;
	public $ENUM_ALL    = 2;
	public $ENUM_BULLET = 3;
	public $ENUM_EMPTY  = 4;
	public $ENUM_FILE_ICON = 5;

	// Widget modes
	public $W_SHOW   = 0;
	public $W_EDIT   = 1;
	public $W_SILENT = 2;
	public $W_HIDDEN = 3;

	// Export modes
	public $EXP_BIBTEX = 1;
	public $EXP_XML    = 2;

	// Import modes
	public $IMP_BIBTEX = 1;
	public $IMP_XML    = 2;

	// Statistic modes
	public $STAT_NONE       = 0;
	public $STAT_TOTAL      = 1;
	public $STAT_YEAR_TOTAL = 2;

	// citeid generation modes
	public $AUTOID_OFF  = 0;
	public $AUTOID_HALF = 1;
	public $AUTOID_FULL = 2;

	// Sorting modes
	public $SORT_DESC = 0;
	public $SORT_ASC  = 1;

	// Database table for publications
	public $template; // HTML templates

	// These are derived/extra configuration values
	public $extConf;

	public $ra;  // The reference database accessor class
	public $fetchRes;
	public $icon_src = array();

	// Statistics
	public $stat;

	public $label_translator = array();


	/**
	 * The main function merges all configuration options and
	 * switches to the appropriate request handler
	 *
	 * @return The plugin HTML content
	 */
	function main ( $content, $conf ) {
		$this->conf = $conf;
		$this->extConf = array();
		$this->pi_setPiVarDefaults ();
		$this->pi_loadLL ();
		$this->extend_ll ( 'EXT:'.$this->extKey.'/locallang_db.xml' );
		$this->pi_initPIflexForm ();

		// Create some configuration shortcuts
		$this->extConf = array ( );
		$extConf =& $this->extConf;
		$this->ra = t3lib_div::makeInstance ( 'tx_sevenpack_reference_accessor' );
		$this->ra->set_cObj ( $this->cObj );
		$rT = $this->ra->refTable;
		$rta = $this->ra->refTableAlias;


		// Initialize current configuration
		$extConf['link_vars'] = array();
		$extConf['sub_page'] = array();

		// Determine charsets
		$extConf['page_charset'] = tx_sevenpack_utility::accquire_page_charset();
		$extConf['be_charset'] = tx_sevenpack_utility::accquire_be_charset();

		$extConf['view_mode'] = $this->VIEW_LIST;
		$extConf['debug'] = $this->conf['debug'] ? TRUE : FALSE;
		$extConf['ce_links'] = $this->conf['ce_links'] ? TRUE : FALSE;


		//
		// Retrieve general FlexForm values
		//
		$ff =& $this->cObj->data['pi_flexform'];
		$fSheet = 'sDEF';
		$extConf['d_mode']          = $this->pi_getFFvalue ( $ff, 'display_mode',   $fSheet );
		$extConf['enum_style']      = $this->pi_getFFvalue ( $ff, 'enum_style',     $fSheet );
		$extConf['show_nav_search'] = $this->pi_getFFvalue ( $ff, 'show_search',    $fSheet );
		$extConf['show_nav_author'] = $this->pi_getFFvalue ( $ff, 'show_authors',   $fSheet );
		$extConf['show_nav_pref']   = $this->pi_getFFvalue ( $ff, 'show_pref',      $fSheet );
		$extConf['sub_page']['ipp'] = $this->pi_getFFvalue ( $ff, 'items_per_page', $fSheet );
		$extConf['max_authors']     = $this->pi_getFFvalue ( $ff, 'max_authors',    $fSheet );
		$extConf['split_bibtypes']  = $this->pi_getFFvalue ( $ff, 'split_bibtypes', $fSheet );
		$extConf['stat_mode']       = $this->pi_getFFvalue ( $ff, 'stat_mode',      $fSheet );
		$extConf['export_mode']     = $this->pi_getFFvalue ( $ff, 'export_mode',    $fSheet );
		$extConf['date_sorting']    = $this->pi_getFFvalue ( $ff, 'date_sorting',   $fSheet );

		$show_fields = $this->pi_getFFvalue ( $ff, 'show_textfields', $fSheet);
		$show_fields = explode ( ',', $show_fields );
		$extConf['hide_fields'] = array ( 'abstract' => 1, 'annotation' => 1, 
			'note' => 1, 'keywords' => 1, 'tags' => 1 );
		foreach ( $show_fields as $f ) {
			$field = FALSE;
			switch ( $f ) {
				case 1: $field = 'abstract';   break;
				case 2: $field = 'annotation'; break;
				case 3: $field = 'note';       break;
				case 4: $field = 'keywords';   break;
				case 5: $field = 'tags';       break;
			}
			if ( $field ) $extConf['hide_fields'][$field] = 0;
		}
		//t3lib_div::debug ( $extConf['hide_fields'] );

		// Configuration by TypoScript selected
		if ( intval ( $extConf['d_mode'] ) < 0 )
			$extConf['d_mode'] = intval ( $this->conf['display_mode'] );
		if ( intval ( $extConf['enum_style'] ) < 0 )
			$extConf['enum_style'] = intval ( $this->conf['enum_style'] );
		if ( intval ( $extConf['date_sorting'] ) < 0 )
			$extConf['date_sorting'] = intval ( $this->conf['date_sorting'] );
		if ( intval ( $extConf['stat_mode'] ) < 0 )
			$extConf['stat_mode'] = intval ( $this->conf['statNav.']['mode'] );

		if ( intval ( $extConf['sub_page']['ipp'] ) < 0 ) {
			$extConf['sub_page']['ipp'] = intval ( $this->conf['items_per_page'] );
		}
		if ( intval ( $extConf['max_authors'] ) < 0 ) {
			$extConf['max_authors'] = intval ( $this->conf['max_authors'] );
		}

		// Override some values from typoscript
		if ( array_key_exists ( 'split_bibtypes', $this->conf ) )
			$extConf['split_bibtypes'] = $this->conf['split_bibtypes'] ? TRUE : FALSE;
		if ( array_key_exists ( 'export_mode', $this->conf ) )
			$extConf['export_mode'] = $this->conf['export_mode'];


		// Activate export modes
		$extConf['enable_export'] = 0;
		if ( intval ( $extConf['export_mode'] ) > 0 ) {
			$eex = $this->conf['export.']['enable_export'];
			if ( strlen ( $eex ) > 0 )
				$eex = tx_sevenpack_utility::explode_trim_lower ( ',', $eex, TRUE );

			// Check restrictions
			$grp = $this->conf['export.']['FE_groups_only'];
			if ( strlen ( $grp ) > 0 )
				$grp = tx_sevenpack_utility::check_fe_user_groups ( $grp );
			else
				$grp = TRUE;
	
			// Add export modes
			if ( is_array ( $eex ) && $grp ) {
				$ec_ee =& $extConf['enable_export'];
				if ( in_array ( 'bibtex', $eex ) )
					$ec_ee = $ec_ee | $this->EXP_BIBTEX;
				if ( in_array ( 'xml', $eex ) )
					$ec_ee = $ec_ee | $this->EXP_XML;
			}
		}


		//
		// Frontend editor configuration
		//
		$ecEditor =& $extConf['editor'];
		$fSheet = 's_fe_editor';
		$ecEditor['enabled']          = $this->pi_getFFvalue ( $ff, 'enable_editor',  $fSheet );
		$ecEditor['citeid_gen_new']   = $this->pi_getFFvalue ( $ff, 'citeid_gen_new', $fSheet );
		$ecEditor['citeid_gen_old']   = $this->pi_getFFvalue ( $ff, 'citeid_gen_old', $fSheet );
		$ecEditor['clear_page_cache'] = $this->pi_getFFvalue ( $ff, 'clear_cache',    $fSheet );

		// Overwrite editor configuration from TSsetup
		if ( is_array( $this->conf['editor.'] ) ) {
			$eo =& $this->conf['editor.'];
			if ( array_key_exists ( 'enabled', $eo ) )
				$extConf['editor']['enabled'] = $eo['enabled'] ? TRUE : FALSE;
			if ( array_key_exists ( 'citeid_gen_new', $eo ) )
				$extConf['editor']['citeid_gen_new'] = $eo['citeid_gen_new'] ? TRUE : FALSE;
			if ( array_key_exists ( 'citeid_gen_old', $eo ) )
				$extConf['editor']['citeid_gen_old'] = $eo['citeid_gen_old'] ? TRUE : FALSE;
		}
		$this->ra->clear_cache = $extConf['editor']['clear_page_cache'];


		//
		// Get storage page(s)
		//
		$pid_list = array();
		if ( isset ( $this->conf['pid_list'] ) ) {
			$pid_list = tx_sevenpack_utility::explode_intval ( ',', $this->conf['pid_list'] );
		}
		if ( isset ( $this->cObj->data['pages'] ) ) {
			$tmp = tx_sevenpack_utility::explode_intval ( ',', $this->cObj->data['pages'] );
			$pid_list = array_merge ( $pid_list, $tmp );
		}

		// Remove doubles and zero 
		$pid_list = array_unique ( $pid_list );
		if ( in_array ( 0, $pid_list ) ) {
			unset ( $pid_list[array_search(0,$pid_list)] );
		}

		//t3lib_div::debug ( array ( 'pid list conf' => $pid_list) );

		if ( sizeof ( $pid_list ) > 0 ) {
			// Determine the recursive depth
			$extConf['recursive'] = $this->cObj->data['recursive'];
			if ( isset ( $this->conf['recursive'] ) ) {
				$extConf['recursive'] = $this->conf['recursive'];
			}
			$extConf['recursive'] = intval ( $extConf['recursive'] );

			$pid_list = $this->pi_getPidList ( implode ( ',', $pid_list ), $extConf['recursive'] );
			$pid_list = tx_sevenpack_utility::explode_intval ( ',', $pid_list );

			$extConf['pid_list'] = $pid_list;
			$this->ra->pid_list = $pid_list;
		} else {
			return $this->finalize ( $this->error_msg ( 'No storage pid given. Select a Starting point.' ) );
		}

		// Adjustments
		switch ( $extConf['d_mode'] ) {
			case $this->D_SIMPLE:
			case $this->D_Y_SPLIT:
			case $this->D_Y_NAV:
				break;
			default:
				$extConf['d_mode'] = $this->D_SIMPLE; // emergency default
		}
		switch ( $extConf['enum_style'] ) {
			case $this->ENUM_PAGE:
			case $this->ENUM_ALL:
			case $this->ENUM_BULLET:
			case $this->ENUM_EMPTY:
			case $this->ENUM_FILE_ICON:
				break;
			default:
				$extConf['enum_style'] = $this->ENUM_ALL; // emergency default
		}
		switch ( $extConf['date_sorting'] ) {
			case $this->SORT_DESC:
			case $this->SORT_ASC:
				break;
			default:
				$extConf['date_sorting'] = $this->SORT_DESC; // emergency default
		}
		switch ( $extConf['stat_mode'] ) {
			case $this->STAT_NONE:
			case $this->STAT_TOTAL:
			case $this->STAT_YEAR_TOTAL:
				break;
			default:
				$extConf['stat_mode'] = $this->STAT_TOTAL; // emergency default
		}
		$extConf['sub_page']['ipp'] = max ( intval ( $extConf['sub_page']['ipp'] ), 0 );
		$extConf['max_authors']     = max ( intval ( $extConf['max_authors']     ), 0 );


		//
		// Search navi
		//
		if ( $extConf['show_nav_search'] ) {
			$extConf['search_navi'] = array();
			$sconf =& $extConf['search_navi'];
			$lvars =& $extConf['link_vars'];

			// Search string
			$p_val = $this->piVars['search']['text'];
			if ( strlen ( $p_val ) > 0 ) {
				$sconf['string'] = $p_val;
				$lvars['search']['text'] = $p_val;
			}

			// Clear string
			if ( isset ( $this->piVars['action']['clear_search'] ) ) {
				$sconf['string'] = '';
			}

			// Search rule
			$p_val = $this->piVars['search']['rule'];
			$sconf['rule'] = 0; // OR
			if ( strtoupper ( $p_val ) == 'AND' ) {
				$sconf['rule'] = 1;  // AND
				$lvars['search']['rule'] = 'AND';
			}

			// Search string separators
			$sconf['separators'] = array( ',', ' ' );

			//t3lib_div::debug ( $sconf );
		}


		//
		// Author navi
		//
		if ( $extConf['show_nav_author'] ) {
			$extConf['author_navi'] = array();
			$aconf =& $extConf['author_navi'];
			$lvars =& $extConf['link_vars'];

			$lvars['author_letter'] = '';
			$p_val = $this->piVars['author_letter'];
			if ( strlen ( $p_val ) > 0 ) {
				$aconf['sel_letter'] = $p_val;
				$lvars['author_letter'] = $p_val;
			}

			$lvars['author'] = '';
			$p_val = $this->piVars['author'];
			$aconf['sel_author'] = '0';
			if ( strlen ( $p_val ) > 0 ) {
				$aconf['sel_author'] = $p_val;
				$lvars['author'] = $p_val;
			}
		}


		//
		// Preference navi
		//
		if ( $extConf['show_nav_pref'] ) {
			// Items per page
			$iPP = $extConf['sub_page']['ipp'];
			$extConf['pref_ipps'] = tx_sevenpack_utility::explode_intval (
				',', $this->conf['prefNav.']['ipp_values'] );
			if ( is_numeric ( $this->conf['prefNav.']['ipp_default']  ) ) {
				$extConf['pref_ipp'] = intval ( $this->conf['prefNav.']['ipp_default'] );
				$iPP = $extConf['pref_ipp'];
			}

			$pvar = $this->piVars['items_per_page'];
			if ( is_numeric ( $pvar ) ) {
				$pvar = max ( intval ( $pvar ), 0 );
				if ( in_array ( $pvar, $extConf['pref_ipps'] ) ) {
					$iPP = $pvar;
					if ( $iPP != $extConf['pref_ipp'] )
						$extConf['link_vars']['items_per_page'] = $iPP;
				}
			}
			$extConf['sub_page']['ipp'] = $iPP;

			//t3lib_div::debug( $this->piVars );

			// Show abstracts
			$show = FALSE;
			if ( $this->piVars['show_abstracts'] != 0 )
				$show = TRUE;
			$extConf['hide_fields']['abstract'] = $show ? FALSE : TRUE;
			$extConf['link_vars']['show_abstracts'] = $show ? '1' : '0';

			// Show keywords
			$show = FALSE;
			if ( $this->piVars['show_keywords'] != 0 )
				$show = TRUE;
			$extConf['hide_fields']['keywords'] =  $show ? FALSE : TRUE;
			$extConf['link_vars']['show_keywords'] = $show ? '1' : '0';
			$extConf['hide_fields']['tags'] = $extConf['hide_fields']['keywords'];
		}


		//
		// Statistic navi
		//
		if ( intval ( $this->extConf['stat_mode'] ) != $this->STAT_NONE ) {
			$extConf['show_nav_stat'] = TRUE;
		}


		//
		// Enable Enable the edit mode
		// Check if this BE user has edit permissions
		//
		$be_ok = FALSE;
		if ( is_object ( $GLOBALS['BE_USER'] ) ) {
			if ( $GLOBALS['BE_USER']->isAdmin() )
				$be_ok = TRUE;
			else
				$be_ok = $GLOBALS['BE_USER']->check ( 'tables_modify', $this->ra->refTable );
		}

		// allow FE-user editing from special groups (set via TS)
		$fe_ok = FALSE;
		if ( !$be_ok && isset ( $this->conf['FE_edit_groups'] ) ) {
			$groups = $this->conf['FE_edit_groups'];
			if ( tx_sevenpack_utility::check_fe_user_groups ( $groups ) )
				$fe_ok = TRUE;
		}

		//t3lib_div::debug( array ( 'Edit mode' => array ( 'BE' => $be_ok, 'FE' => $fe_ok ) ) );
		$extConf['edit_mode'] = ( ($be_ok || $fe_ok) && $extConf['editor']['enabled'] );

		// Set the enumeration mode
		$extConf['has_enum'] = TRUE;
		if ( ( $extConf['enum_style'] == $this->ENUM_EMPTY ) ) {
			$extConf['has_enum'] = FALSE;
		}

		// Initialize data display restrictions
		$this->init_restrictions();

		// Initialize icons
		$this->init_list_icons();

		// Initialize the default filter
		$this->init_filters();

		// Don't show hidden entries
		$extConf['show_hidden'] = FALSE;
		if ( $extConf['edit_mode'] ) {
			$extConf['show_hidden'] = TRUE;
		}
		$this->ra->show_hidden = $extConf['show_hidden'];

		//
		// Edit mode specific !!!
		//
		if ( $extConf['edit_mode'] ) {

			// Disable caching in edit mode
			$GLOBALS['TSFE']->set_no_cache();

			// Do an action type evaluation
			if ( is_array ( $this->piVars['action'] ) ) {
				$act_str = implode('', array_keys ( $this->piVars['action'] ) );
				//t3lib_div::debug ( $act_str );
				switch ( $act_str ) {
					case 'new':
						$extConf['view_mode']   = $this->VIEW_SINGLE;
						$extConf['single_mode'] = $this->SINGLE_NEW;
						break;
					case 'edit':
						$extConf['view_mode']   = $this->VIEW_SINGLE;
						$extConf['single_mode'] = $this->SINGLE_EDIT;
						break;
					case 'confirm_save':
						$extConf['view_mode']   = $this->VIEW_SINGLE;
						$extConf['single_mode'] = $this->SINGLE_CONFIRM_SAVE;
						break;
					case 'save':
						$extConf['view_mode']   = $this->VIEW_DIALOG;
						$extConf['dialog_mode'] = $this->DIALOG_SAVE_CONFIRMED;
						break;
					case 'confirm_delete':
						$extConf['view_mode']   = $this->VIEW_SINGLE;
						$extConf['single_mode'] = $this->SINGLE_CONFIRM_DELETE;
						break;
					case 'delete':
						$extConf['view_mode']   = $this->VIEW_DIALOG;
						$extConf['dialog_mode'] = $this->DIALOG_DELETE_CONFIRMED;
						break;
					case 'confirm_erase':
						$extConf['view_mode']   = $this->VIEW_SINGLE;
						$extConf['single_mode'] = $this->SINGLE_CONFIRM_ERASE;
						break;
					case 'erase':
						$extConf['view_mode']   = $this->VIEW_DIALOG;
						$extConf['dialog_mode'] = $this->DIALOG_ERASE_CONFIRMED;
					case 'hide':
						$this->ra->hide_publication ( $this->piVars['uid'], TRUE );
						break;
					case 'reveal':
						$this->ra->hide_publication ( $this->piVars['uid'], FALSE );
						break;
					default:
				}
			}

			// Set unset extConf and piVars single mode
			if ( $extConf['view_mode'] == $this->VIEW_DIALOG ) {
				unset ( $this->piVars['single_mode'] );
			}

			if ( isset ( $extConf['single_mode'] ) ) {
				$this->piVars['single_mode'] = $extConf['single_mode'];
			} else if ( isset ( $this->piVars['single_mode'] ) ) {
					$extConf['view_mode']   = $this->VIEW_SINGLE;
					$extConf['single_mode'] = $this->piVars['single_mode'];
			}

			// Initialize edit icons
			$this->init_edit_icons();

			// Switch to an import view on demand
			$allImport = intval ( $this->IMP_BIBTEX | $this->IMP_XML );
			if ( isset( $this->piVars['import'] ) && 
			     ( intval ( $this->piVars['import'] ) & $allImport ) ) {
				$extConf['view_mode']   = $this->VIEW_DIALOG;
				$extConf['dialog_mode'] = $this->DIALOG_IMPORT;
			}

		}

		// Switch to an export view on demand
		$piv_exp = intval ( $this->piVars['export'] );
		if ( intval ( $extConf['export_mode'] ) != 0 ) {
			if ( ( $piv_exp & $extConf['enable_export'] ) != 0 ) {
				$extConf['view_mode']   = $this->VIEW_DIALOG;
				$extConf['dialog_mode'] = $this->DIALOG_EXPORT;
			}
		}


		//
		// Search navigation setup
		//
		if ( $extConf['show_nav_search'] ) {
			$sconf =& $extConf['search_navi'];
			if ( strlen ( $sconf['string'] ) > 0 ) {
				$pats = array();
				$strings = tx_sevenpack_utility::multi_explode_trim (
					$sconf['separators'], $sconf['string'], TRUE );
				foreach ( $strings as $txt ) {
					$spec = htmlentities ( $txt, ENT_QUOTES, 'UTF-8' );
					$pats[] = $txt;
					if ( $spec != $txt ) 
						$pats[] = $spec;
				}

				//t3lib_div::debug ( $pats );
				$ff =& $extConf['filters'];
				$ff['search'] = array();
				$ff['search']['all'] = array();
				$ff['search']['all']['words'] = $pats;
				$ff['search']['all']['rule'] = $sconf['rule'];
			}
		}

		//
		// Fetch publication statistics
		//
		$this->stat = array();
		//t3lib_div::debug ( $extConf['filters'] );
		$this->ra->set_filters ( $extConf['filters'] );

		//
		// Author navigation setup
		//
		if ( $extConf['show_nav_author'] ) {
			$aconf =& $extConf['author_navi'];
			$this->stat['authors'] = array();
			$astat =& $this->stat['authors'];

			$filter = array ( );

			$astat['surnames'] = $this->ra->fetch_author_surnames();

			// Filter for selected author letter
			$astat['sel_surnames'] = array();
			if ( strlen ( $aconf['sel_letter'] ) > 0 ) {
				$filters = $extConf['filters'];

				$txt = $aconf['sel_letter'];
				$spec = htmlentities ( $txt, ENT_QUOTES, 'UTF-8' );
				$pats = array ( $txt . '%' );
				if ( $spec != $txt ) 
					$pats[] = $spec . '%';

				// Setup filter
				foreach ( $pats as $pat )
					$filter[] = array ( 'surname' => $pat );

				$filters['temp'] = array();
				$filters['temp']['author'] = array();
				$filters['temp']['author']['authors'] = $filter;

				// Fetch surnames
				$this->ra->set_filters ( $filters );
				$astat['sel_surnames'] = $this->ra->fetch_author_surnames ( );
				//t3lib_div::debug ( $astat['sel_surnames'] );

				// Treat selection
				$lst = array();
				$txt = FALSE;
				foreach ( $astat['sel_surnames'] as $name ) {
					if ( !( strpos ( $name, '&' ) === FALSE ) ) {
						$name = html_entity_decode ( $name, ENT_COMPAT, 'UTF-8' );
						$txt = TRUE;
					}
					if ( !in_array ( $name, $lst ) ) {
						$lst[] = $name;
					}
				}
				if ( $txt ) {
					usort ( $lst, 'strcoll' );
					$astat['sel_surnames'] = $lst;
					//t3lib_div::debug ( $lst );
				}

				// Restore filter
				$this->ra->set_filters ( $extConf['filters'] );
			}

			// Filter for selected author
			if ( $aconf['sel_author'] != '0' ) {
				$txt = $aconf['sel_author'];
				$spec = htmlentities ( $txt, ENT_QUOTES, 'UTF-8' );
				$pats = array ( $txt );
				if ( $spec != $txt ) 
					$pats[] = $spec;

				// Setup filter
				$filter = array ( );
				foreach ( $pats as $pat )
					$filter[] = array ( 'surname' => $pat );
			}

			// Append filter
			if ( sizeof ( $filter ) > 0 )  {
				$ff =& $extConf['filters'];
				$ff['author'] = array();
				$ff['author']['author'] = array();
				$ff['author']['author']['authors'] = $filter;
			}

			//t3lib_div::debug ( $extConf['filters'] );
			$this->ra->set_filters ( $extConf['filters'] );
		}

		$hist = $this->ra->fetch_histogram ( 'year' );
		$this->stat['year_hist'] = $hist;
		$this->stat['years'] = array_keys ( $hist );
		sort ( $this->stat['years'] );
		$this->stat['num_all'] = array_sum ( $hist );

		//t3lib_div::debug ( $this->stat );

		//
		// Determine the year to display
		//
		$extConf['year'] = FALSE;
		$ecYear =& $extConf['year'];
		if ( is_numeric ( $this->piVars['year'] ) )
			$ecYear = intval ( $this->piVars['year'] );
		else
			$ecYear = intval ( date ( 'Y' ) ); // System year

		// The selected year has no publications so select the closest year
		// Set default link variables
		if ( $extConf['d_mode'] == $this->D_Y_NAV ) {
			if ( $this->stat['num_all'] > 0 ) {
				$ecYear = tx_sevenpack_utility::find_nearest_int ( $ecYear, $this->stat['years'] );
			}
			$extConf['link_vars']['year'] = $ecYear;
		}

		$this->stat['num_page'] = $this->stat['num_all'];
		if ( $this->extConf['d_mode'] == $this->D_Y_NAV ) {
			$this->stat['num_page'] = $this->stat['year_hist'][$ecYear];
		}

		//
		// Determine the number of sub pages and the current sub page (zero based)
		//
		$subPage =& $extConf['sub_page'];
		$iPP = $subPage['ipp'];
		if ( $iPP > 0 ) {
			$subPage['max']     = floor ( ( $this->stat['num_page']-1 ) / $iPP );
			$subPage['current'] = tx_sevenpack_utility::crop_to_range (
				$this->piVars['page'], 0, $subPage['max'] );
		} else {
			$subPage['max']     = 0;
			$subPage['current'] = 0;
		}

		//
		// Enable page and year navigation
		//
		if ( $extConf['d_mode'] == $this->D_Y_NAV )
			$extConf['show_nav_year'] = TRUE;
		if ( ( $iPP > 0 ) && ( $this->stat['num_page'] > $iPP ) )
			$extConf['show_nav_page'] = TRUE;

		//
		// Setup the browse filter
		//
		$extConf['filters']['browse'] = array();
		$br_filter =& $extConf['filters']['browse'];

		// Adjust sorting
		if ( $extConf['split_bibtypes'] ) {
			$dSort = 'DESC';
			if ( $extConf['date_sorting'] == $this->SORT_ASC )
				$dSort = 'ASC';
			if ( $extConf['d_mode'] == $this->D_SIMPLE ) {
				$br_filter['sorting'] = array (
					array ( 'field' => $rta.'.bibtype', 'dir' => 'ASC'  ),
					array ( 'field' => $rta.'.year',    'dir' => $dSort ),
					array ( 'field' => $rta.'.month',   'dir' => $dSort ),
					array ( 'field' => $rta.'.day',     'dir' => $dSort ),
					array ( 'field' => $rta.'.state',   'dir' => 'ASC'  ),
					array ( 'field' => $rta.'.sorting', 'dir' => 'ASC'  ),
					array ( 'field' => $rta.'.title',   'dir' => 'ASC'  )
				);
			} else {
				$br_filter['sorting'] = array (
					array ( 'field' => $rta.'.year',    'dir' => $dSort ),
					array ( 'field' => $rta.'.bibtype', 'dir' => 'ASC'  ),
					array ( 'field' => $rta.'.month',   'dir' => $dSort ),
					array ( 'field' => $rta.'.day',     'dir' => $dSort ),
					array ( 'field' => $rta.'.state',   'dir' => 'ASC'  ),
					array ( 'field' => $rta.'.sorting', 'dir' => 'ASC'  ),
					array ( 'field' => $rta.'.title',   'dir' => 'ASC'  )
				);
			}
		}

		// Adjust year filter
		if ( ( $extConf['d_mode'] == $this->D_Y_NAV ) && is_numeric ( $ecYear ) ) {
			$br_filter['year'] = array();
			$br_filter['year']['years'] = array ( $ecYear );
		}

		// Adjust the browse filter limit
		if ( $subPage['max'] > 0 ) {
			$br_filter['limit'] = array();
			$br_filter['limit']['start'] = $subPage['current']*$iPP;
			$br_filter['limit']['num'] = $iPP;
		}

		// Setup reference accessor
		$this->ra->set_filters ( $extConf['filters'] );

		//
		// Initialize the html template
		//
		$err = $this->init_template ( );
		if ( sizeof ( $err ) > 0 ) {
			$bad = '';
			foreach ( $err as $msg )
				$bad .= $this->error_msg ( $msg );
			return $this->finalize ( $bad );
		}

		//
		// Switch to requested view mode
		//
		switch ( $extConf['view_mode'] ) {
			case $this->VIEW_LIST :
				return $this->finalize ( $this->list_view () );
				break;
			case $this->VIEW_SINGLE :
				return $this->finalize ( $this->single_view () );
				break;
			case $this->VIEW_DIALOG :
				return $this->finalize ( $this->dialog_view () );
				break;
		}

		return $this->finalize ( $this->error_msg ( 'An illegal view mode occured' ) );
	}


	/**
	 * This is the last function called before ouptput
	 *
	 * @return The input string with some extra data
	 */
	function finalize ( $str )
	{
		if ( $this->extConf['debug'] )
			$str .= t3lib_div::view_array (
				array ( 
					'extConf' => $this->extConf,
					'conf' => $this->conf,
					'piVars' => $this->piVars,
					'HTTP_POST_VARS' => $GLOBALS['HTTP_POST_VARS'],
					'HTTP_GET_VARS' => $GLOBALS['HTTP_GET_VARS'],
					//'$this->cObj->data' => $this->cObj->data
				) 
			);
		return $this->pi_wrapInBaseClass ( $str );
	}


	/**
	 * Returns the error message wrapped into a mesage container
	 *
	 * @return The wrapper error message
	 */
	function error_msg ( $str )
	{
		$ret  = '<div class="'.$this->prefixShort.'-warning_box">'."\n";
		$ret .= '<h3>'.$this->prefix_pi1.' error</h3>'."\n";
		$ret .= '<div>'.$str.'</div>'."\n";
		$ret .= '</div>'."\n";
		return $ret;
	}


	/**
	 * This initializes field restrictions
	 *
	 * @return void
	 */
	function init_restrictions ( )
	{
		$this->extConf['restrict'] = FALSE;
		$restrict = $this->conf['restrictions.'];
		if ( is_array ( $restrict ) ) {
			$res = $restrict['file_url.'];
			if ( is_array ( $res ) ) {
				$all = ( $res['hide_all'] != 0 );
				$ext = tx_sevenpack_utility::explode_trim_lower ( 
					',', $res['hide_file_ext'], TRUE );
				$groups = strtolower ( $res['FE_user_groups'] );
				if ( strpos ( $groups, 'all' ) === FALSE )
					$groups = tx_sevenpack_utility::explode_intval ( ',', $groups );
				else
					$groups = 'all';
			}
			$this->extConf['restrict']['file_url']['hide_all'] = $all;
			$this->extConf['restrict']['file_url']['hide_ext'] = $ext;
			$this->extConf['restrict']['file_url']['fe_groups'] = $groups;
		}
	}


	/**
	 * This initializes all filters before the browsing filter
	 *
	 * @return FALSE or an error message
	 */
	function init_filters ( )
	{
		$this->extConf['filters'] = array();
		$this->init_flexform_filter();
		$this->init_selection_filter();
	}


	/**
	 * This initializes filter array from the flexform
	 *
	 * @return FALSE or an error message
	 */
	function init_flexform_filter ( )
	{
		$rT =& $this->ra->refTable;
		$rta =& $this->ra->refTableAlias;

		// Create and select the flexform filter
		$this->extConf['filters']['flexform'] = array();
		$filter =& $this->extConf['filters']['flexform'];

		// Flexform helpers
		$ff =& $this->cObj->data['pi_flexform'];
		$fSheet = 's_filter';

		// Pid filter
		$filter['pid'] = $this->extConf['pid_list'];

		// Year filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_year', $fSheet ) > 0 ) {
			$f = array();
			$f['years'] = array();
			$f['ranges'] = array();
			$ffStr = $this->pi_getFFvalue ( $ff, 'years', $fSheet );
			$arr = tx_sevenpack_utility::multi_explode_trim ( array ( ',', "\r" , "\n" ), $ffStr, TRUE );
			foreach ( $arr as $y ) {
				$match = array();
				if ( preg_match ( '/^\d+$/', $y, $match ) ) {
					$f['years'][] = intval ( $match[0] );
				} else if ( preg_match ( '/^(\d*)\s*-\s*(\d*)$/', $y, $match ) ) {
					$range = array();
					if ( intval ( $match[1] ) )
						$range['from'] = intval ( $match[1] );
					if ( intval ( $match[2] ) )
						$range['to'] = intval ( $match[2] );
					if ( sizeof ( $range ) )
						$f['ranges'][] = $range;
				}
			}
			if ( ( sizeof ( $f['years'] ) + sizeof ( $f['ranges'] ) ) > 0 ) {
				$filter['year'] = $f;
			}
		}

		// Author filter
		$this->extConf['highlight_authors'] = $this->pi_getFFvalue ( $ff, 'highlight_authors', $fSheet );

		if ( $this->pi_getFFvalue ( $ff, 'enable_author', $fSheet ) != 0 ) {
			$f = array();;
			$f['authors'] = array();
			$f['rule'] = $this->pi_getFFvalue ( $ff, 'author_rule', $fSheet );
			$f['rule'] = intval ( $f['rule'] );

			$authors = $this->pi_getFFvalue ( $ff, 'authors', $fSheet );
			$authors = tx_sevenpack_utility::multi_explode_trim ( array ( "\r" , "\n" ), $authors, TRUE );
			foreach ( $authors as $a ) {
				$parts = t3lib_div::trimExplode ( ',', $a );
				$author = array();
				if ( strlen ( $parts[0] ) > 0 )
					$author['surname'] = $parts[0];
				if ( strlen ( $parts[1] ) > 0 )
					$author['forename'] = $parts[1];
				if ( sizeof ( $author ) > 0 )
					$f['authors'][] = $author;
			}
			if ( sizeof ( $f['authors'] ) > 0 )
				$filter['author'] = $f;
		}

		// State filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_state', $fSheet ) != 0 ) {
			$f = array();
			$f['states'] = array();
			$states = intval ( $this->pi_getFFvalue ( $ff, 'states', $fSheet ) );

			$j = 1;
			for ( $i=0; $i < sizeof ( $this->ra->allStates ); $i++ ) {
				if ( $states & $j )
					$f['states'][] = $i;
				$j = $j*2;
			}
			if ( sizeof ( $f['states'] ) > 0 )
				$filter['state'] = $f;
		}

		// Bibtype filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_bibtype', $fSheet ) != 0 ) {
			$f = array();
			$f['types'] = array();
			$types = $this->pi_getFFvalue ( $ff, 'bibtypes', $fSheet );
			$types = explode ( ',', $types );
			foreach ( $types as $v ) {
				$v = intval ( $v );
				if ( ( $v >= 0 ) && ( $v < sizeof ( $this->ra->allBibTypes ) ) )
					$f['types'][] = $v;
			}
			if ( sizeof ( $f['types'] ) > 0 )
				$filter['bibtype'] = $f;
		}

		// Origin filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_origin', $fSheet ) != 0 ) {
			$f = array();
			$f['origin'] = $this->pi_getFFvalue ( $ff, 'origins', $fSheet );
			if( $f['origin'] == 1 )
				$f['origin'] = 0; // Legacy value
			else if( $f['origin'] == 2 )
				$f['origin'] = 1; // Legacy value
			$filter['origin'] = $f;
		}

		// Reviewed filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_reviewes', $fSheet ) != 0 ) {
			$f = array();
			$f['value'] = $this->pi_getFFvalue ( $ff, 'reviewes', $fSheet );
			$filter['reviewed'] = $f;
		}

		// In library filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_in_library', $fSheet ) != 0 ) {
			$f = array();
			$f['value'] = $this->pi_getFFvalue ( $ff, 'in_library', $fSheet );
			$filter['in_library'] = $f;
		}

		// Borrowed filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_borrowed', $fSheet ) != 0 ) {
			$f = array();
			$f['value'] = $this->pi_getFFvalue ( $ff, 'borrowed', $fSheet );
			$filter['borrowed'] = $f;
		}

		// Citeid filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_citeid', $fSheet ) != 0 ) {
			$f = array();
			$ids = $this->pi_getFFvalue ( $ff, 'citeids', $fSheet);
			if ( strlen ( $ids ) > 0 ) {
				$ids = tx_sevenpack_utility::multi_explode_trim ( array ( ',', "\r" , "\n" ), $ids, TRUE );
				$f['ids'] = array_unique ( $ids );
				$filter['citeid'] = $f;
			}
		}

		// Keywords filter
		if ( $this->pi_getFFvalue ( $ff, 'enable_keywords', $fSheet) ) {
			$f = array();
			$f['rule'] = $this->pi_getFFvalue ( $ff, 'keywords_rule', $fSheet);
			$f['rule'] = intval ( $f['rule'] );
			$kw = $this->pi_getFFvalue ( $ff, 'keywords', $fSheet);
			if ( strlen ( $kw ) > 0 ) {
				$f['words'] = tx_sevenpack_utility::multi_explode_trim ( array ( ',', "\r" , "\n" ), $kw, TRUE );
				$filter['keywords'] = $f;
			}
		}

		// General keyword search
		if ( $this->pi_getFFvalue ( $ff, 'enable_search_all', $fSheet) ) {
			$f = array();
			$f['rule'] = $this->pi_getFFvalue ( $ff, 'search_all_rule', $fSheet);
			$f['rule'] = intval ( $f['rule'] );
			$kw = $this->pi_getFFvalue ( $ff, 'search_all', $fSheet);
			if ( strlen ( $kw ) > 0 ) {
				$f['words'] = tx_sevenpack_utility::multi_explode_trim ( array ( ',', "\r" , "\n" ), $kw, TRUE );
				$filter['all'] = $f;
			}
		}

		//t3lib_div::debug ( array ( 'pid list final' => $pid_list) );

		//
		// Sorting
		//
		$dSort = 'DESC';
		if ( $this->extConf['date_sorting'] == $this->SORT_ASC )
			$dSort = 'ASC';
		$filter['sorting'] = array (
			array ( 'field' => $rta.'.year',    'dir' => $dSort ),
			array ( 'field' => $rta.'.month',   'dir' => $dSort ),
			array ( 'field' => $rta.'.day',     'dir' => $dSort ),
			array ( 'field' => $rta.'.bibtype', 'dir' => 'ASC'  ),
			array ( 'field' => $rta.'.state',   'dir' => 'ASC'  ),
			array ( 'field' => $rta.'.sorting', 'dir' => 'ASC'  ),
			array ( 'field' => $rta.'.title',   'dir' => 'ASC'  )
		);

		//t3lib_div::debug ( $filter );

	}


	/**
	 * This initializes the selction filter array from the piVars
	 *
	 * @return FALSE or an error message
	 */
	function init_selection_filter ( )
	{
		if ( !$this->conf['allow_selection'] )
			return FALSE;

		$this->extConf['filters']['selection'] = array();
		$filter =& $this->extConf['filters']['selection'];

		// Publication ids
		if ( is_string ( $this->piVars['search']['ref_ids'] ) ) {
			$ids = $this->piVars['search']['ref_ids'];
			$ids = tx_sevenpack_utility::explode_intval ( ',', $ids );

			if( sizeof ( $ids ) > 0 ) {
				$filter['uid'] = $ids;
			}
		}

		// General search
		if ( is_string ( $this->piVars['search']['all'] ) ) {
			$words = $this->piVars['search']['all'];
			$words = tx_sevenpack_utility::explode_trim ( ',', $words, TRUE );
			if ( sizeof ( $words ) > 0 ) {
				$filter['all']['words'] = $words;
				$filter['all']['rule'] = 1; // AND
				$rule = strtoupper ( trim ( $this->piVars['search']['all_rule'] ) );
				if ( strpos ( $rule, 'AND' ) === FALSE ) {
					$filter['all']['rule'] = 0; // OR
				}
			}
		}
	}


	/** 
	 * Initializes an array which contains subparts of the
	 * html templates.
	 *
	 * @return TRUE on error, FALSE otherwise
	 */
	function init_template ()
	{
		$err = array();

		// Allready initialized?
		if ( isset ( $this->template['LIST_VIEW'] ) )
			return $err;

		// List blocks
		$list_blocks = array (
			'YEAR_BLOCK', 'BIBTYPE_BLOCK', 'SPACER_BLOCK' 
		);

		// Bibtype data blocks
		$bib_types = array ();
		foreach ( $this->ra->allBibTypes as $val ) {
			$bib_types[] = strtoupper ( $val ) . '_DATA';
		}
		$bib_types[] = 'DEFAULT_DATA';
		$bib_types[] = 'ITEM_BLOCK';

		// Misc navigation blocks
		$navi_blocks = array ( 'EXPORT_NAVI_BLOCK', 
			'IMPORT_NAVI_BLOCK', 'NEW_ENTRY_NAVI_BLOCK' );

		// Fetch the template file list
		$tlist =& $this->conf['templates.'];
		if ( !is_array ( $tlist ) ) {
			$err[] = 'HTML templates are not set in TypoScript';
			return $err;
		}

		$info = array (
			'main' => array (
				'file' => $tlist['main'],
				'parts' => array ( 'LIST_VIEW' )
			),
			'list_blocks' => array (
				'file' => $tlist['list_blocks'],
				'parts' => $list_blocks
			),
			'list_items' => array (
				'file' => $tlist['list_items'],
				'parts' => $bib_types,
				'no_warn' => TRUE
			),
			'navi_misc' => array (
				'file' => $tlist['navi_misc'],
				'parts' => $navi_blocks,
			)
		);

		//t3lib_div::debug( $info );

		foreach ( $info as $key => $val ) {
			if ( strlen ( $val['file'] ) == 0 ) {
				$err[] = 'HTML template file for \'' . $key . '\' is not set' ;
				continue;
			}
			$tmpl = $this->cObj->fileResource ( $val['file'] );
			if ( strlen ( $tmpl ) == 0 ) {
				$err[] = 'The HTML template file \'' . $val['file'] . '\' for \'' . $key . 
					'\' is not readable or empty';
				continue;
			}
			foreach ( $val['parts'] as $part ) {
				$ptag = '###' . $part . '###';
				$pstr = $this->cObj->getSubpart ( $tmpl, $ptag );
				if ( ( strlen ( $pstr ) == 0 ) && !$val['no_warn'] ) {
					 $err[] = 'The subpart \'' . $ptag . '\' in the HTML template file \'' . $val['file'] . '\' is empty';
				}
				$this->template[$part] = $pstr;
			}
		}

		//t3lib_div::debug( $this->template );

		return $err;
	}


	/** 
	 * Initialize the edit icons
	 *
	 * @return void
	 */
	function init_edit_icons ()
	{
		$list = array ();
		$more = $this->conf['edit_icons.'];
		if ( is_array ( $more ) )
			$list = array_merge ( $list, $more );

		$tmpl =& $GLOBALS['TSFE']->tmpl;
		foreach ( $list as $key => $val ) {
			$this->icon_src[$key] = $tmpl->getFileName ( $base . $val );
		}
	}


	/** 
	 * Initialize the list view icons
	 *
	 * @return void
	 */
	function init_list_icons ()
	{
		$list = array ( 
			'default' => 'EXT:cms/tslib/media/fileicons/default.gif' );
		$more = $this->conf['file_icons.'];
		if ( is_array ( $more ) )
			$list = array_merge ( $list, $more );

		$tmpl =& $GLOBALS['TSFE']->tmpl;
		$this->icon_src['files'] = array();
		$ic =& $this->icon_src['files'];
		foreach ( $list as $key => $val ) {
			$ic['.'.$key] = $tmpl->getFileName ( $val );
		}
	}


	/** 
	 * Extend the $this->LOCAL_LANG label with another language set
	 *
	 * @return void
	 */
	function extend_ll ( $file )
	{
		if ( !is_array ( $this->extConf['LL_ext'] ) )
			$this->extConf['LL_ext'] = array();
		if ( !in_array ( $file, $this->extConf['LL_ext'] ) ) {

			//t3lib_div::debug ( 'Loading language file ' . $file );
			$tmpLang = t3lib_div::readLLfile ( $file, $this->LLkey );
			foreach ( $this->LOCAL_LANG as $lang => $list ) {
				foreach ( $list as $key => $word ) {
					$tmpLang[$lang][$key] = $word;
				}
			}
			$this->LOCAL_LANG = $tmpLang;

			if ( $this->altLLkey ) {
				$tmpLang = t3lib_div::readLLfile ( $file, $this->altLLkey );
				foreach ( $this->LOCAL_LANG as $lang => $list ) {
					foreach ( $list as $key => $word ) {
						$tmpLang[$lang][$key] = $word;
					}
				}
				$this->LOCAL_LANG = $tmpLang;
			}

			$this->extConf['LL_ext'][] = $file;
		}
		//t3lib_div::debug ( $this->LOCAL_LANG );
	}


	/** 
	 * Get the string in the local language to a given key .
	 *
	 * @return The string in the local language
	 */
	function get_ll ( $key, $alt = '', $hsc = FALSE )
	{
		return $this->pi_getLL ( $key, $alt, $hsc );
	}


	/**
	 * Composes a link of an url an some attributes
	 *
	 * @return The link (HTML <a> element)
	 */
	function compose_link ( $url, $content, $attribs = NULL )
	{
		$lstr = '<a href="'.$url.'"';
		if ( is_array ( $attribs ) ) {
			foreach ( $attribs as $k => $v ) {
				$lstr .= ' ' . $k . '="' . $v . '"';
			}
		}
		$lstr .= '>'.$content.'</a>';
		return $lstr;
	}


	/**
	 * Wraps the content into a link to the current page with
	 * extra link arguments given in the array $vars
	 *
	 * @return The link to the current page
	 */
	function get_link ( $content, $vars = array(), $auto_cache = TRUE, $attribs = NULL )
	{
		$url = $this->get_link_url ( $vars , $auto_cache );
		return $this->compose_link ( $url, $content, $attribs );
	}


	/**
	 * Same as get_link but returns just the URL
	 *
	 * @return The url
	 */
	function get_link_url ( $vars = array(), $auto_cache = TRUE, $current_record = TRUE )
	{
		if ( $this->extConf['edit_mode'] ) $auto_cache = FALSE;

		$vars = array_merge ( $this->extConf['link_vars'], $vars );
		$vars = array ( $this->prefix_pi1 => $vars );

		$record = '';
		if ( $this->extConf['ce_links'] && $current_record )
			$record = "#c".strval ( $this->cObj->data['uid'] );

		$this->pi_linkTP ( 'x', $vars, $auto_cache );
		$url = $this->cObj->lastTypoLinkUrl . $record;

		$url = preg_replace ( '/&([^;]{8})/', '&amp;\\1', $url );
		return $url;
	}


	/**
	 * Same as get_link() but for edit mode links
	 *
	 * @return The link to the current page
	 */
	function get_edit_link ( $content, $vars = array(), $auto_cache = TRUE, $attribs = array() )
	{
		$url = $this->get_edit_link_url ( $vars , $auto_cache );
		return $this->compose_link ( $url, $content, $attribs );
	}


	/**
	 * Same as get_link_url() but for edit mode urls
	 *
	 * @return The url
	 */
	function get_edit_link_url ( $vars = array(), $auto_cache = TRUE, $current_record = TRUE )
	{
		$pv =& $this->piVars;
		$keep = array ( 'uid', 'single_mode', 'editor' );
		foreach ( $keep as $k ) {
			$pvar =& $pv[$k];
			if ( is_string ( $pvar ) || is_array ( $pvar ) || is_numeric ( $pvar ) ) {
				$vars[$k] = $pvar;
			}
		}
		return $this->get_link_url ( $vars, $auto_cache, $current_record );
	}


	/**
	 * Returns an instance of a navigation bar class
	 *
	 * @return The url
	 */
	function get_navi_instance ( $type )
	{
		$file = 'EXT:'.$this->extKey.'/pi1/class.' . $type . '.php';
		require_once ( $GLOBALS['TSFE']->tmpl->getFileName ( $file ) );
		$obj = t3lib_div::makeInstance ( $type );
		$obj->initialize ( $this );
		return $obj;
	}


	/**
	 * This function composes the html-view of a set of publications
	 *
	 * @return The list view
	 */
	function list_view ()
	{
		$this->setup_search_navi ();  // setup year navigation
		$this->setup_year_navi ();  // setup year navigation
		$this->setup_author_navi (); // setup author navigation
		$this->setup_pref_navi ();  // setup preferences navigation
		$this->setup_page_navi ();  // setup page navigation

		$this->setup_new_entry_navi ();  // setup new entry button

		$this->setup_export_navi ();  // setup export links
		$this->setup_import_navi ();  // setup import link
		$this->setup_statistic_navi ();  // setup statistic element

		$this->setup_spacer ();  // setup spacer
		$this->setup_top_navigation ();  // setup page navigation element

		$this->setup_items (); // setup the publication items

		//t3lib_div::debug ( $this->template['LIST_VIEW'] );

		return $this->template['LIST_VIEW'];
	}


	/** 
	 * Returns the year navigation bar
	 *
	 * @return A HTML string with the year navigation bar
	 */
	function setup_search_navi ()
	{
		$trans = array();
		$hasStr = '';
		$cObj =& $this->cObj;

		if ( $this->extConf['show_nav_search'] ) {
			$obj = $this->get_navi_instance ( 'tx_sevenpack_navi_search' );

			$trans = $obj->translator();
			$hasStr = array ( '', '' );

			if ( strlen ( $trans['###SEARCH_NAVI_TOP###'] ) > 0 )
				$this->extConf['has_top_navi'] = TRUE;
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_SEARCH_NAVI###', $hasStr );
		$tmpl = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );
	}


	/** 
	 * Returns the year navigation bar
	 *
	 * @return A HTML string with the year navigation bar
	 */
	function setup_year_navi ()
	{
		$trans = array();
		$hasStr = '';
		$cObj =& $this->cObj;

		if ( $this->extConf['show_nav_year'] ) {
			$obj = $this->get_navi_instance ( 'tx_sevenpack_navi_year' );

			$trans = $obj->translator();
			$hasStr = array ( '', '' );

			if ( strlen ( $trans['###YEAR_NAVI_TOP###'] ) > 0 )
				$this->extConf['has_top_navi'] = TRUE;
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_YEAR_NAVI###', $hasStr );
		$tmpl = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );
	}


	/**
	 * Sets up the author navigation bar
	 *
	 * @return void
	 */
	function setup_author_navi ()
	{
		$trans = array();
		$hasStr = '';
		$cObj =& $this->cObj;

		if ( $this->extConf['show_nav_author'] ) {
			$obj = $this->get_navi_instance ( 'tx_sevenpack_navi_author' );

			$trans = $obj->translator();
			$hasStr = array ( '', '' );

			if ( strlen ( $trans['###AUTHOR_NAVI_TOP###'] ) > 0 )
				$this->extConf['has_top_navi'] = TRUE;
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_AUTHOR_NAVI###', $hasStr );
		$tmpl = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );
	}


	/**
	 * Sets up the page navigation bar
	 *
	 * @return void
	 */
	function setup_page_navi ()
	{
		$trans = array();
		$hasStr = '';
		$cObj =& $this->cObj;

		if ( $this->extConf['show_nav_page'] ) {
			$obj = $this->get_navi_instance ( 'tx_sevenpack_navi_page' );

			$trans = $obj->translator();
			$hasStr = array ( '', '' );

			if ( strlen ( $trans['###PAGE_NAVI_TOP###'] ) > 0 )
				$this->extConf['has_top_navi'] = TRUE;
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_PAGE_NAVI###', $hasStr );
		$tmpl = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );
	}


	/**
	 * Sets up the preferences navigation bar
	 *
	 * @return void
	 */
	function setup_pref_navi ()
	{
		$trans = array();
		$hasStr = '';
		$cObj =& $this->cObj;

		if ( $this->extConf['show_nav_pref'] ) {
			$obj = $this->get_navi_instance ( 'tx_sevenpack_navi_pref' );

			$trans = $obj->translator();
			$hasStr = array ( '', '' );

			if ( strlen ( $trans['###PREF_NAVI_TOP###'] ) > 0 )
				$this->extConf['has_top_navi'] = TRUE;
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_PREF_NAVI###', $hasStr );
		$tmpl = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );
	}


	/** 
	 * Setup the add-new-entry element
	 *
	 * @return void
	 */
	function setup_new_entry_navi ()
	{
		$linkStr = '';
		$hasStr = '';

		if ( $this->extConf['edit_mode'] )  {
			$tmpl = $this->enum_condition_block ( $this->template['NEW_ENTRY_NAVI_BLOCK'] );
			$linkStr = $this->get_new_manipulator ( );
			$linkStr = $this->cObj->substituteMarker ( $tmpl, '###NEW_ENTRY###', $linkStr );
			$hasStr = array ( '','' );
			//t3lib_div::debug ( $linkStr );
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $this->cObj->substituteSubpart ( $tmpl, '###HAS_NEW_ENTRY###', $hasStr );
		$tmpl = $this->cObj->substituteMarker ( $tmpl, '###NEW_ENTRY###', $linkStr );
	}


	/** 
	 * Setup the statistic element
	 *
	 * @return void
	 */
	function setup_statistic_navi ()
	{
		$trans = array();
		$hasStr = '';
		$cObj =& $this->cObj;

		if ( $this->extConf['show_nav_stat'] ) {
			$obj = $this->get_navi_instance ( 'tx_sevenpack_navi_stat' );

			$trans = $obj->translator();
			$hasStr = array ( '', '' );

			if ( strlen ( $trans['###STAT_NAVI_TOP###'] ) > 0 )
				$this->extConf['has_top_navi'] = TRUE;
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $this->cObj->substituteSubpart ( $tmpl, '###HAS_STAT_NAVI###', $hasStr );
		$tmpl = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );
	}


	/** 
	 * Setup the export-link element 
	 *
	 * @return void
	 */
	function setup_export_navi ()
	{
		$str = '';
		$hasStr = '';

		if ( ( $this->extConf['enable_export'] != 0 ) && ( $this->stat['num_all'] > 0) )  {

			$cfg = array();
			if ( is_array ( $this->conf['export.'] ) )
				$cfg =& $this->conf['export.'];

			$str = $this->enum_condition_block ( $this->template['EXPORT_NAVI_BLOCK'] );
			$translator = array();
			$exports = array();

			// Export bibtex
			if ( $this->extConf['enable_export'] & $this->EXP_BIBTEX ) {
				$title = $this->get_ll ( 'export_bibtexLinkTitle', 'bibtex', TRUE );
				$link = $this->get_link ( $this->get_ll ( 'export_bibtex' ), array ( 'export'=>$this->EXP_BIBTEX ), 
						FALSE, array ( 'title' => $title ) );
				$exports[] = $this->cObj->stdWrap ( $link, $cfg['bibtex.'] );
			}

			// Export xml
			if ( $this->extConf['enable_export'] & $this->EXP_XML ) {
				$title = $this->get_ll ( 'export_xmlLinkTitle', 'xml' ,TRUE );
				$link = $this->get_link ( $this->get_ll ( 'export_xml' ), array('export'=>$this->EXP_XML), 
						FALSE, array ( 'title' => $title ) );
				$exports[] = $this->cObj->stdWrap ( $link, $cfg['xml.'] );
			}

			$sep = '&nbsp;';
			if ( array_key_exists ( 'separator', $cfg ) )
				$sep = $this->cObj->stdWrap ( $cfg['separator'], $cfg['separator.'] );

			// Export label
			$translator['###LABEL###'] = $this->cObj->stdWrap ( 
				$this->get_ll ( $cfg['label'] ), $cfg['label.'] );
			$translator['###EXPORTS###'] = implode ( $sep, $exports );
 
			$str = $this->cObj->substituteMarkerArrayCached ( $str, $translator, array() );
			$hasStr = array ( '','' );
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $this->cObj->substituteSubpart ( $tmpl, '###HAS_EXPORT###', $hasStr );
		$tmpl = $this->cObj->substituteMarker ( $tmpl, '###EXPORT###', $str );
	}


	/** 
	 * Setup the import-link element in the
	 * HTML-template
	 *
	 * @return void
	 */
	function setup_import_navi ()
	{
		$str = '';
		$hasStr = '';

		if ( $this->extConf['edit_mode'] )  {

			$cfg = array();
			if ( is_array ( $this->conf['import.'] ) )
				$cfg =& $this->conf['import.'];

			$str = $this->enum_condition_block ( $this->template['IMPORT_NAVI_BLOCK'] );
			$translator = array();
			$imports = array();

			// Import bibtex
			$title = $this->get_ll ( 'import_bibtexLinkTitle', 'bibtex', TRUE );
			$link = $this->get_link ( $this->get_ll ( 'import_bibtex' ), array('import'=>$this->IMP_BIBTEX), 
					FALSE, array ( 'title' => $title ) );
			$imports[] = $this->cObj->stdWrap ( $link, $cfg['bibtex.'] );

			// Import xml
			$title = $this->get_ll ( 'import_xmlLinkTitle', 'xml', TRUE );
			$link = $this->get_link ( $this->get_ll ( 'import_xml' ), array('import'=>$this->IMP_XML), 
					FALSE, array ( 'title' => $title ) );
			$imports[] = $this->cObj->stdWrap ( $link, $cfg['xml.'] );

			$sep = '&nbsp;';
			if ( array_key_exists ( 'separator', $cfg ) )
				$sep = $this->cObj->stdWrap ( $cfg['separator'], $cfg['separator.'] );

			// Import label
			$translator['###LABEL###'] = $this->cObj->stdWrap ( 
				$this->get_ll ( $cfg['label'] ), $cfg['label.'] );
			$translator['###IMPORTS###'] = implode ( $sep, $imports );
 
			$str = $this->cObj->substituteMarkerArrayCached ( $str, $translator, array() );
			$hasStr = array ( '','' );
		}

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $this->cObj->substituteSubpart ( $tmpl, '###HAS_IMPORT###', $hasStr );
		$tmpl = $this->cObj->substituteMarker ( $tmpl, '###IMPORT###', $str );
	}


	/** 
	 * Setup the top navigation block
	 *
	 * @return void
	 */
	function setup_top_navigation ()
	{
		$hasStr = '';
		if ( $this->extConf['has_top_navi'] ) {
			$hasStr = array ( '', '' );
		}
		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $this->cObj->substituteSubpart ( $tmpl, '###HAS_TOP_NAVI###', $hasStr );
	}


	/** 
	 * Prepares database publication data for displaying
	 *
	 * @return The procesed publication data array
	 */
	function prepare_pub_display( $pub, &$warnings = array() ) {

		// Prepare processed row data
		$pdata = $pub;
		foreach ( $this->ra->refFields as $f ) {
			$pdata[$f] = tx_sevenpack_utility::filter_pub_html_display ( $pdata[$f] );
		}

		// Preformat some data
		// Bibtype
		$pdata['bibtype_short'] = $this->ra->allBibTypes[$pdata['bibtype']];
		$pdata['bibtype'] = $this->get_ll (
			$this->ra->refTable.'_bibtype_I_'.$pdata['bibtype'],
			'Unknown bibtype: '.$pdata['bibtype'], TRUE ) ;

		// Extern
		$pdata['extern'] = ( $pub['extern'] == 0 ? '' : 'extern' );

		// Day
		if ( ($pub['day'] > 0) && ($pub['day'] <= 31) ) {
			$pdata['day'] = strval ( $pub['day'] );
		} else {
			$pdata['day'] = '';
		}

		// Month
		if ( ($pub['month'] > 0) && ($pub['month'] <= 12) ) {
			$tme = mktime ( 0, 0, 0, intval ( $pub['month'] ), 15, 2008 );
			$pdata['month'] = $tme;
		} else {
			$pdata['month'] = '';
		}

		// Automatic url
		$order = tx_sevenpack_utility::explode_trim ( ',', $this->conf['auto_url_order'], TRUE );
		$pdata['auto_url'] = $this->get_auto_url ( $pdata, $order );

		// State
		switch ( $pdata['state'] ) {
			case 0 :  
				$pdata['state'] = ''; 
				break;
			default : 
				$pdata['state'] = $this->get_ll (
				$this->ra->refTable.'_state_I_'.$pdata['state'],
				'Unknown state: '.$pdata['state'], TRUE ) ;
		}

		// Format the author string
		$pdata['authors'] = $this->get_item_authors_html ( $pub['authors'] );

		// Look for missing citeid
		if ( ( strlen ( $pub['citeid'] ) == 0 ) && $this->extConf['edit_mode']
		     && ($this->conf['editor.']['warnings.']['m_citeid'] > 0) ) {
			$warnings[] = 'Citeid missing';
		}

		// Prepare the translator
		// Remove empty field marker from the template
		foreach ( $this->ra->pubFields as $f ) {
			$val = trim ( strval ( $pdata[$f] ) );

			if ( strlen ( $val ) > 0 )  {
				// Do some special treatment for certain fields
				$charset = strtoupper ( $this->extConf['be_charset'] );
				switch ( $f ) {
					case 'file_url':
					case 'web_url':
						$val = tx_sevenpack_utility::fix_html_ampersand ( $val );
						$pdata[$f] = $val;
						break;
					default:
						$pdata[$f] = $val;
				}
			}
		}

		return $pdata;
	}


	/** 
	 * Returns the html interpretation of the publication
	 * item as it is defined in the html template
	 *
	 * @return HTML string for a single item in the list view
	 */
	function get_item_html ( $pdata, $templ )
	{
		//t3lib_div::debug ( array ( 'get_item_html($pdata)' => $pdata ) );
		$translator = array();
		$now = time();
		$cObj =& $this->cObj;
		$conf =& $this->conf;

		$bib_str = $pdata['bibtype_short'];
		$all_base = 'rnd' . strval ( rand() ) . 'rnd';
		$all_wrap = $all_base;

		// Prepare the translator
		// Remove empty field marker from the template
		foreach ( $this->ra->pubFields as $f ) {
			$upStr = strtoupper ( $f );
			$tkey = '###'.$upStr.'###';
			$hasStr = '';
			$translator[$tkey] = '';

			$val = strval ( $pdata[$f] );

			if ( strlen ( $val ) > 0 )  {
				if ( $this->check_field_restriction ( $f, $val ) )
					$val = '';
			}

			if ( strlen ( $val ) > 0 )  {
				// Wrap default or by bibtype
				$stdWrap = $conf['field.'][$f.'.'];
				if ( is_array ( $conf['field.'][$bib_str.'.'][$f.'.'] ) )
					$stdWrap = $conf['field.'][$bib_str.'.'][$f.'.'];
				//t3lib_div::debug ( $stdWrap );
				$val = $cObj->stdWrap ( $val, $stdWrap );

				if ( strlen ( $val ) > 0 ) {
					$hasStr =  array ( '', '' );
					$translator[$tkey] = $val;
				}
			}

			$templ = $cObj->substituteSubpart ( $templ, '###HAS_'.$upStr.'###', $hasStr );
		}

		// Reference wrap
		$all_wrap = $cObj->stdWrap ( $all_wrap, $conf['reference.'] );

		// Embrace hidden references with wrap
		if ( ( $pdata['hidden'] != 0 ) && is_array ( $conf['editor.']['hidden.'] ) ) {
			$all_wrap = $cObj->stdWrap ( $all_wrap, $conf['editor.']['hidden.'] );
		}

		$templ = $cObj->substituteMarkerArrayCached ( $templ, $translator );
		$templ = $cObj->substituteMarkerArrayCached ( $templ, $this->label_translator );

		// Wrap elements with an anchor
		$url_wrap = array ( '', '' );
		if ( strlen ( $pdata['file_url'] ) > 0 ) {
			$url_wrap = $cObj->typolinkWrap ( array ( 'parameter' => $pdata['auto_url'] ) );
		}
		$templ = $cObj->substituteSubpart ( $templ, '###URL_WRAP###', $url_wrap );

		$all_wrap = explode ( $all_base, $all_wrap );
		$templ = $cObj->substituteSubpart ( $templ, '###REFERENCE_WRAP###', $all_wrap );

		// remove empty divs
		$templ = preg_replace ( "/<div[^>]*>[\s\r\n]*<\/div>/", "\n", $templ );
		// remove multiple line breaks
		$templ = preg_replace ( "/\n+/", "\n", $templ );
		//t3lib_div::debug ( $templ );

		return $templ;
	}


	/** 
	 * Returns the authors string for a publication
	 *
	 * @return void
	 */
	function get_item_authors_html ( $authors ) {
		$res = '';

		// Load publication data into cObj
		$cObj =& $this->cObj;
		$cObj_restore = $cObj->data;

		// Format the author string$this->
		$and = ' '.$this->get_ll ( 'label_and', 'and', TRUE ).' ';

		$max_authors = abs ( intval ( $this->extConf['max_authors'] ) );
		$last_author = sizeof ( $authors ) - 1;
		$cut_authors = FALSE;
		if ( ( $max_authors > 0 ) && ( sizeof ( $authors ) > $max_authors ) ) {
			$cut_authors = TRUE;
			if ( sizeof($authors) == ( $max_authors + 1 ) ) {
				$last_author = $max_authors - 2;
			} else {
				$last_author = $max_authors - 1;
			}
		}
		$last_author = max ( $last_author, 0 );
		
		//t3lib_div::debug ( array ( 'authors' => $authors, 'max_authors' => $max_authors, 'last_author' => $last_author ) );

		$hl_authors = $this->extConf['highlight_authors'] ? TRUE : FALSE;

		$a_sep  = $this->extConf['author_sep'];
		$a_tmpl = $this->extConf['author_tmpl'];

		$filter_authors = array();
		if ( $hl_authors ) {
			// Collect filter authors
			foreach ( $this->extConf['filters'] as $filter ) {
				if ( is_array( $filter['author']['authors'] ) ) {
					$filter_authors = array_merge ( 
						$filter_authors, $filter['author']['authors'] );
				}
			}
		}
		//t3lib_div::debug ( $filter_authors );

		for ( $i_a=0; $i_a<=$last_author; $i_a++ ) {
			$a =& $authors[$i_a];
			//t3lib_div::debug ( $a );

			$cObj->data = $a;
			$cObj->data['url'] = htmlspecialchars_decode ( $a['url'], ENT_QUOTES );

			// The forename
			$a_fn = trim ( $a['forename'] );
			if ( strlen ( $a_fn ) > 0 ) {
				$a_fn = tx_sevenpack_utility::filter_pub_html_display ( $a_fn );
				$a_fn = $this->cObj->stdWrap ( $a_fn, $this->conf['authors.']['forename.'] );
			}

			// The surname
			$a_sn = trim ( $a['surname'] );
			if ( strlen ( $a_sn ) > 0 ) {
				$a_sn = tx_sevenpack_utility::filter_pub_html_display ( $a_sn );
				$a_sn = $this->cObj->stdWrap ( $a_sn, $this->conf['authors.']['surname.'] );
			}

			// Compose names and apply stdWrap
			$a_str = str_replace ( 
				array ( '###FORENAME###', '###SURNAME###' ), 
				array ( $a_fn, $a_sn ), $a_tmpl );
			$stdWrap = $this->conf['field.']['author.'];
			if ( is_array ( $this->conf['field.'][$bib_str.'.']['author.'] ) )
				$stdWrap = $this->conf['field.'][$bib_str.'.']['author.'];
			$a_str = $this->cObj->stdWrap ( $a_str, $stdWrap );

			// Wrap the filtered authors with a highlightning class on demand
			if ( $hl_authors ) {
				foreach ( $filter_authors as $fa ) {
					if ( $a['surname'] == $fa['surname'] ) {
						if ( !$fa['forename'] || ($a['forename'] == $fa['forename']) ) {
							$a_str = $this->cObj->stdWrap ( 
								$a_str, $this->conf['authors.']['highlight.'] );
							break;
						}
					}
				}
			}

			// Append author name
			$res .= $a_str;

			// Append an author separator or "et al."
			$app = '';
			if ( $i_a < ($last_author-1) ) {
				$app = $a_sep;
			} else {
				if ( $cut_authors ) {
					$app = $a_sep;
					if ( $i_a == $last_author ) {

						// Append et al.
						$et_al = $this->get_ll ( 'label_et_al', 'et al.', TRUE );
						$app = ( strlen ( $et_al ) > 0 ) ? ' '.$et_al : '';

						// Highlight "et al." on demand
						if ( $hl_authors ) {
							for ( $j = $last_author + 1; $j < sizeof ( $authors ); $j++ ) {
								$a_et = $authors[$j];
								foreach ( $filter_authors as $fa ) {
									if ( $a_et['surname'] == $fa['surname'] ) {
										if ( !$fa['forename'] || ($a_et['forename'] == $fa['forename']) ) {
											$app = $this->cObj->stdWrap ( $app, $this->conf['authors.']['highlight.'] );
											$j = sizeof ( $authors );
											break;
										}
									}
								}
							}
						}

					}
				} elseif ( $i_a < $last_author ) {
					$app = $and;
				}
			}

			$res .= $app;
		}

		// Restore cObj data
		$cObj->data = $cObj_restore;

		return $res;
	}


	/** 
	 * Setup items in the html-template
	 *
	 * @return void
	 */
	function setup_items ()
	{
		$items = '';
		$hasStr = '';

		// Aliases
		$ra =& $this->ra;
		$cObj =& $this->cObj;
		$conf =& $this->conf;
		$filters =& $this->extConf['filters'];

		// Store cObj data
		$cObj_restore = $cObj->data;

		// The author name template
		$this->extConf['author_tmpl'] = '###FORENAME### ###SURNAME###';
		if ( isset ( $conf['authors.']['template'] ) ) {
			$this->extConf['author_tmpl'] = $cObj->stdWrap ( 
				$conf['authors.']['template'], $conf['authors.']['template.'] 
			);
		}
		$this->extConf['author_sep'] = ', ';
		if ( isset ( $conf['authors.']['separator'] ) ) {
			$this->extConf['author_sep'] = $cObj->stdWrap ( 
				$conf['authors.']['separator'], $conf['authors.']['separator.'] 
			);
		}

		// Initialize the label translator
		$this->label_translator = array();
		$lt =& $this->label_translator;
		$lt['###LABEL_ABSTRACT###']   = $cObj->stdWrap ( $this->get_ll ( 'label_abstract' ),  $conf['label.']['abstract.']  );
		$lt['###LABEL_ANNOTATION###'] = $cObj->stdWrap ( $this->get_ll ( 'label_annotation' ), $conf['label.']['annotation.'] );
		$lt['###LABEL_EDITION###']    = $cObj->stdWrap ( $this->get_ll ( 'label_edition' ),   $conf['label.']['edition.']   );
		$lt['###LABEL_EDITOR###']     = $cObj->stdWrap ( $this->get_ll ( 'label_editor' ),    $conf['label.']['editor.']    );
		$lt['###LABEL_ISBN###']       = $cObj->stdWrap ( $this->get_ll ( 'label_isbn' ),      $conf['label.']['ISBN.']      );
		$lt['###LABEL_KEYWORDS###']   = $cObj->stdWrap ( $this->get_ll ( 'label_keywords' ),  $conf['label.']['keywords.']  );
		$lt['###LABEL_TAGS###']       = $cObj->stdWrap ( $this->get_ll ( 'label_tags' ),      $conf['label.']['tags.']      );
		$lt['###LABEL_NOTE###']       = $cObj->stdWrap ( $this->get_ll ( 'label_note' ),      $conf['label.']['note.']      );
		$lt['###LABEL_OF###']         = $cObj->stdWrap ( $this->get_ll ( 'label_of' ),        $conf['label.']['of.']        );
		$lt['###LABEL_PAGE###']       = $cObj->stdWrap ( $this->get_ll ( 'label_page' ),      $conf['label.']['page.']      );
		$lt['###LABEL_PUBLISHER###']  = $cObj->stdWrap ( $this->get_ll ( 'label_publisher' ), $conf['label.']['publisher.'] );
		$lt['###LABEL_VOLUME###']     = $cObj->stdWrap ( $this->get_ll ( 'label_volume' ),    $conf['label.']['volume.']    );

		// Initialize the enumeration template
		$eid = 'page';
		switch ( intval ( $this->extConf['enum_style'] ) ) {
			case $this->ENUM_ALL:
				$eid = 'all'; break;
			case $this->ENUM_BULLET:
				$eid = 'bullet'; break;
			case $this->ENUM_EMPTY:
				$eid = 'empty'; break;
			case $this->ENUM_FILE_ICON:
				$eid = 'file_icon'; break;
		}
		$enum_base = strval ( $conf['enum.'][$eid] );
		$enum_wrap = $conf['enum.'][$eid.'.'];


		// Database accessor initialization
		$ra->mFetch_initialize();

		// Determine publication numbers
		$pubs_before = 0;
		if ( $this->extConf['d_mode'] == $this->D_Y_NAV ) {
			foreach ( $this->stat['year_hist'] as $y => $n ) {
				if ( $y == $this->extConf['year'] )
					break;
				$pubs_before += $n;
			}
		}

		$prevBibType = -1;
		$prevYear = -1;

		// Initialize counters
		$limit_start = intval ( $filters['browse']['limit']['start'] );
		$i_page = $this->stat['num_page'] - $limit_start;
		$i_page_delta = -1;
		if ( $this->extConf['date_sorting'] == $this->SORT_ASC ) {
			$i_page = $limit_start + 1;
			$i_page_delta = 1;
		}

		$i_subpage = 1;
		$i_bibtype = 1;

		// Start the fetch loop
		while ( $pub = $ra->mFetch ( ) )  {
			// Get prepared publication data
			$warnings = array();
			$pdata = $this->prepare_pub_display( $pub, $warnings );

			// Item data
			$cObj->data = $pub;
			// Needed since stdWrap/Typolink applies htmlspecialchars to url data
			$cObj->data['file_url'] = htmlspecialchars_decode ( $pdata['file_url'], ENT_QUOTES );
			$cObj->data['web_url'] = htmlspecialchars_decode ( $pdata['web_url'], ENT_QUOTES );
			$cObj->data['auto_url'] = htmlspecialchars_decode ( $pdata['auto_url'], ENT_QUOTES );

			// All publications counter
			$i_all = $pubs_before + $i_page;

			// Determine evenOdd
			if ( $this->extConf['split_bibtypes'] ) {
				if ( $pub['bibtype'] != $prevBibType )
					$i_bibtype = 1;
				$evenOdd = $i_bibtype % 2;
			} else {
				$evenOdd = $i_subpage % 2;
			}

			// Setup the item template
			$data_block = strtoupper ( $pdata['bibtype_short'] ) . '_DATA';
			$data_block = $this->template[$data_block];
			$item_block = $this->template['ITEM_BLOCK'];

			if ( strlen ( $data_block ) == 0 )
				$data_block = $this->template['DEFAULT_DATA'];

			$templ = $cObj->substituteMarker ( $item_block,
				'###ITEM_DATA###', $data_block );

			$templ = $this->enum_condition_block ( $templ );

			// Initialize the translator
			$translator = array();

			$enum = $enum_base;
			$enum = str_replace ( '###I_ALL###', strval ( $i_all ), $enum );
			$enum = str_replace ( '###I_PAGE###', strval ( $i_page ), $enum );
			if ( !( strpos( $enum, '###FILE_URL_ICON###' ) === FALSE ) ) {
				$repl = $this->get_file_url_icon ( $pdata['file_url'] );
				$enum = str_replace ( '###FILE_URL_ICON###', $repl, $enum );
			}
			$translator['###ENUM_NUMBER###'] = $cObj->stdWrap ( $enum, $enum_wrap );

			// Row classes
			$eo = $evenOdd ? 'even' : 'odd';

			$translator['###ROW_CLASS###'] = $conf['classes.'][$eo];

			$translator['###NUMBER_CLASS###'] = $this->prefixShort.'-enum';
			//$translator['###TITLECLASS###'] = $this->prefix_pi1.'-bibtitle';

			// Manipulators
			$translator['###MANIPULATORS###'] = '';
			$manip_edit = '';
			$manip_hide = '';
			$manip_all = array();
			$subst_sub = '';
			if ( $this->extConf['edit_mode'] )  {
				$subst_sub = array ( '', '' );
				$manip_all[] = $this->get_edit_manipulator ( $pub );
				$manip_all[] = $this->get_hide_manipulator ( $pub );
				$manip_all = tx_sevenpack_utility::html_layout_table ( array ( $manip_all ) );

				$translator['###MANIPULATORS###'] = $cObj->stdWrap (
					$manip_all, $conf['editor.']['manipulators.']['all.']
				);
			}

			$templ = $cObj->substituteSubpart ( $templ, '###HAS_MANIPULATORS###', $subst_sub );

			// Year separator label
			if ( ($this->extConf['d_mode'] == $this->D_Y_SPLIT) && ( $pub['year'] != $prevYear ) )  {
				$yearStr = $cObj->stdWrap ( strval ( $pub['year'] ), $conf['label.']['year.'] );
				$t_str = $this->enum_condition_block ( $this->template['YEAR_BLOCK'] );
				$items .= $cObj->substituteMarker ( $t_str, '###YEAR###', $yearStr );
				$prevBibType = -1;
			}

			// Bibtype separator label
			if ( $this->extConf['split_bibtypes'] && ($pub['bibtype'] != $prevBibType) )  {
				$bibStr = $cObj->stdWrap (
					$this->get_ll ( 'bibtype_plural_'.$pub['bibtype'], $pub['bibtype'], TRUE ),
					$conf['label.']['bibtype.']
				);
				$t_str = $this->enum_condition_block ( $this->template['BIBTYPE_BLOCK'] );
				$items .= $cObj->substituteMarker ( $t_str, '###BIBTYPE###', $bibStr );
			}

			// Apply translator
			$templ = $cObj->substituteMarkerArrayCached ( $templ, $translator, array() );

			// Pass to item processor
			$items .= $this->get_item_html ( $pdata, $templ );

			// Update counters
			$i_page += $i_page_delta;
			$i_subpage++;
			$i_bibtype++;

			$prevBibType = $pub['bibtype'];
			$prevYear = $pub['year'];
		}

		// clean up
		$ra->mFetch_finish();

		// Restore cObj data
		$cObj->data = $cObj_restore;

		if ( strlen ( $items ) )
			$hasStr = array ( '', '' );

		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_ITEMS###', $hasStr );
		$tmpl = $cObj->substituteMarker ( $tmpl, '###ITEMS###', $items );
	}


	/**
	 * Returns the new entry button
	 */
	function get_new_manipulator ( ) {
		$label = $this->get_ll ( 'manipulators_new', 'New', TRUE );
		$imgSrc = 'src="'.$this->icon_src['new_record'].'"';
		$img = '<img '.$imgSrc.' alt="'.$label.'" ' . 
			'class="'.$this->prefixShort.'-new_icon" />';

		$res = $this->get_link ( $img, array('action'=>array('new'=>1)), TRUE, array('title'=>$label) );
		$res . $this->cObj->stdWrap ( $res, $this->conf['editor.']['manipulators.']['new.'] );
		return $res;
	}


	/**
	 * Returns the edit button
	 */
	function get_edit_manipulator ( $pub ) {
		// The edit button
		$label = $this->get_ll ( 'manipulators_edit', 'Edit', TRUE );
		$imgSrc = 'src="'.$this->icon_src['edit'].'"';
		$img = '<img '.$imgSrc.' alt="'.$label.'" ' . 
			'class="'.$this->prefixShort.'-edit_icon" />';

		$res = $this->get_link ( $img, 
			array ( 'action'=>array('edit'=>1),'uid'=>$pub['uid'] ), 
			TRUE, array ( 'title'=>$label ) );

		$res = $this->cObj->stdWrap ( $res, $this->conf['editor.']['manipulators.']['edit.'] );

		return $res;
	}


	/**
	 * Returns the hide button
	 */
	function get_hide_manipulator ( $pub ) {
		if ( $pub['hidden'] == 0 )  {
			$label = $this->get_ll ( 'manipulators_hide', 'Hide', TRUE );
			$imgSrc = 'src="'.$this->icon_src['hide'].'"';
			$action = array('hide'=>1);
		}  else  {
			$label = $this->get_ll ( 'manipulators_reveal', 'Reveal', TRUE );
			$imgSrc = 'src="'.$this->icon_src['reveal'].'"';
			$action = array('reveal'=>1);
		}

		$img = '<img '.$imgSrc.' alt="'.$label.'" ' . 
			'class="'.$this->prefixShort.'-hide_icon" />';
		$res = $this->get_link ( $img, 
			array ( 'action'=>$action, 'uid'=>$pub['uid'] ), 
			TRUE, array('title'=>$label) );

		$res = $this->cObj->stdWrap ( $res, $this->conf['editor.']['manipulators.']['hide.'] );

		return $res;
	}


	/**
	 * Returns TRUE if the field/value combination is restricted
	 * and should not be displayed
	 *
	 * @return TRUE (restricted) or FALSE (not restricted)
	 */
	function check_field_restriction ( $field, $value ) {
		$res = FALSE;

		if ( strlen ( $value ) == 0 )
			return FALSE;

		if ( $this->extConf['hide_fields'][$field] ) {
			return TRUE;
		}

		$restric =& $this->extConf['restrict'];
		if ( !is_array ( $restric ) )
			return FALSE;

		if ( $field == 'file_url' && is_array ( $restric['file_url'] ) ) {
			$rest =& $restric['file_url'];

			$show = TRUE;
			// Disable on hide all
			if ( $rest['hide_all'] )
				$show = FALSE;

			// Disable if file extensions matches
			if ( $show && is_array ( $rest['hide_ext'] ) ) {
				foreach ( $rest['hide_ext'] as $ext ) {
					// Sanitize input
					$len = strlen ( $ext );
					if ( ( $len > 0 ) && ( strlen ( $value ) >= $len ) ) {
						$uext = strtolower ( substr ( $value, -$len ) );
						//t3lib_div::debug( array ( 'ext: ' => $ext, 'uext: ' => $uext ) );
						if ( $uext == $ext ) {
							$show = FALSE;
							break;
						}
					}
				}
			}

			// Enable if usergroup matches
			if ( !$show && isset ( $rest['fe_groups'] ) ) {
				$groups = $rest['fe_groups'];
				if ( tx_sevenpack_utility::check_fe_user_groups ( $groups ) )
					$show = TRUE;
			}

			// Disable if local file does not exist
			if ( strpos ( $value, 'fileadmin/' ) === 0 ) {
				if ( !file_exists ( $value ) ) {
					$show = FALSE;
					//t3lib_div::debug( array ( 'Local file does not exist: ' => $value ) );
				}
			}

			if ( !$show )
				$res = TRUE;
		}

		//t3lib_div::debug( array ( 'Restricted: ' => $res ? 'True' : 'False' ) );
		return $res;
	}


	/**
	 * Prepares the virtual auto_url from the data and field order
	 *
	 * @return The generated url
	 */
	function get_auto_url ( $pdata, $order ) {
		//t3lib_div::debug( array ( 'Order: ' => $order ) );
		$url = '';

		foreach ( $order as $field ) {
			if ( strlen ( $url ) > 0 )
				break;
			$data = trim ( strval ( $pdata[$field] ) );
			if ( strlen ( $data ) > 0 ) {
				$rest = $this->check_field_restriction ( $field, $data );
				if ( !$rest ) {
					if ( $field == 'DOI' ) {
						$url = 'http://dx.doi.org/' .
							tx_sevenpack_utility::filter_pub_html_display ( $data );
					} else {
						$url = $data;
					}
				}
			}
		}
		//t3lib_div::debug ( array ( 'auto_url: ' => $url ) );
		return $url;
	}


	/**
	 * Returns the file url icon
	 */
	function get_file_url_icon( $url ) {
		$res = '';

		if ( strlen ( $url ) > 0 ) {
			$sources =& $this->icon_src['files'];
			$src = $sources['.default'];
			$cr_link = TRUE;

			if ( $cr_link ) {
				$rest = $this->check_field_restriction ( 'file_url', $url );
				if ( $rest ) { 
					$cr_link = FALSE;
				}
			}

			foreach ( $sources as $ext => $file  ) {
				$len = strlen( $ext );
				if ( strlen ( $url ) >= $len ) {
					$sub = strtolower( substr ( $url, -$len ) );
					if ( $sub == $ext ) {
						$src = $file;
						break;
					}
				}
			}
			$img = '<img src="' . $src . '"';
			$img .= '/>';
			$res .= $img;

			if ( $cr_link )
				$res = $this->cObj->getTypoLink ( $res, $url );

			$res = $this->cObj->stdWrap ( $res, $this->conf['enum.']['file_icon_image.'] );
		} else {
			$res = '&nbsp;';
		}

		//t3lib_div::debug ( array ( 'image: ' => $res ) );
		return $res;
	}


	/** 
	 * Removes the enumeration condition block
	 * or just the block markers
	 *
	 * @return void
	 */
	function enum_condition_block ( $templ ) {
		if ( $this->extConf['has_enum'] ) {
			$templ = $this->cObj->substituteSubpart ( $templ,
				'###HAS_ENUM###', array ( '', '' ) );
		} else {
			$templ = $this->cObj->substituteSubpart ( $templ,
				'###HAS_ENUM###', '' );
		}
		return $templ;
	}


	/** 
	 * Setup the BibTex export link in the template
	 *
	 * @return void
	 */
	function setup_spacer ()
	{
		$t_str = $this->enum_condition_block ( $this->template['SPACER_BLOCK'] );
		$tmpl =& $this->template['LIST_VIEW'];
		$tmpl = $this->cObj->substituteMarker ( $tmpl, '###SPACER###', $t_str );
	}


	/** 
	 * This loads the single view
	 *
	 * @return The single view
	 */
	function single_view ()
	{
		require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
			'EXT:'.$this->extKey.'/pi1/class.tx_sevenpack_single_view.php' ) );
		$sv = t3lib_div::makeInstance ( 'tx_sevenpack_single_view' );
		$sv->initialize ( $this );
		return $sv->single_view();
	}


	/** 
	 * This switches to the requested dialog
	 *
	 * @return The requested dialog
	 */
	function dialog_view ( )
	{
		$con = '';
		switch ( $this->extConf['dialog_mode'] ) {
			case $this->DIALOG_EXPORT :
				$con .= $this->export_dialog ( );
				break;
			case $this->DIALOG_IMPORT :
				$con .= $this->import_dialog ( );
				break;
			default :
				require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
					'EXT:'.$this->extKey.'/pi1/class.tx_sevenpack_single_view.php' ) );
				$sv = t3lib_div::makeInstance('tx_sevenpack_single_view');
				$sv->initialize ( $this );
				$con .= $sv->dialog_view();
		}
		$con .= '<p>';
		$con .= $this->get_link ( $this->get_ll ( 'link_back_to_list' ) );
		$con .= '</p>'."\n";
		return $con;
	}


	/** 
	 * The export dialog
	 *
	 * @return The export dialog
	 */
	function export_dialog ( )
	{
		$con = '';
		$title = $this->get_ll ( 'export_title' );
		$con .= '<h2>'.$title.'</h2>'."\n";
		$mode = $this->piVars['export'];
		$label = 'export';

		if ( $mode > 0 ) {
			$exp = FALSE;
			switch ( $mode ) {
				case $this->EXP_BIBTEX:
					require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
						'EXT:'.$this->extKey.'/pi1/class.tx_sevenpack_exporter_bibtex.php' ) );
					$exp = t3lib_div::makeInstance ( 'tx_sevenpack_exporter_bibtex' );
					$label = $this->get_ll ( 'export_bibtex' );
					break;
				case $this->EXP_XML:
					require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
						'EXT:'.$this->extKey.'/pi1/class.tx_sevenpack_exporter_xml.php' ) );
					$exp = t3lib_div::makeInstance ( 'tx_sevenpack_exporter_xml' );
					$label = $this->get_ll ( 'export_xml' );
					break;
			}
			
			if ( is_object ( $exp ) ) {
				$exp->initialize ( $this );
				if ( $exp->export () ) {
					$con .= $this->error_msg ( $exp->error );
				} else {
					$link = $this->cObj->getTypoLink ( $exp->file_name,
						$exp->get_file_rel() );
					$con .= '<ul><li><div>';
					$con .= $link;
					if ( $exp->file_new )
						$con .= ' (' . $this->get_ll ( 'export_file_new' ) . ')';
					$con .= '</div></li>';
					$con .= '</ul>' . "\n";
				}
			}
		} else {
			$con .= $this->error_msg ( 'Unknown export mode' );
		}

		return $con;
	}


	/** 
	 * The import dialog
	 *
	 * @return The import dialog
	 */
	function import_dialog ()
	{
		$con = '';
		$title = $this->get_ll ( 'import_title' );
		$con .= '<h2>'.$title.'</h2>'."\n";
		$mode = $this->piVars['import'];

		if ( ( $mode == $this->IMP_BIBTEX ) || ( $mode == $this->IMP_XML ) ) {

			$importer = FALSE;

			switch ( $mode ) {
				case $this->IMP_BIBTEX:
					require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
						'EXT:'.$this->extKey.'/pi1/class.tx_sevenpack_importer_bibtex.php' ) );
					$importer = t3lib_div::makeInstance ( 'tx_sevenpack_importer_bibtex' );
					break;
				case $this->IMP_XML:
					require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
						'EXT:'.$this->extKey.'/pi1/class.tx_sevenpack_importer_xml.php' ) );
					$importer = t3lib_div::makeInstance ( 'tx_sevenpack_importer_xml' );
					break;
			}
			$importer->initialize ( $this );
			$con .= $importer->import();
		} else {
			$con .= $this->error_msg ( 'Unknown import mode' );
		}

		return $con;
	}

}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sevenpack/pi1/class.tx_sevenpack_pi1.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sevenpack/pi1/class.tx_sevenpack_pi1.php"]);
}

?>
