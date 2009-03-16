<?php

if ( !isset($GLOBALS['TSFE']) )
	die ('This file is no meant to be executed');

require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
	'EXT:sevenpack/res/class.tx_sevenpack_pregexp_translator.php') );

class tx_sevenpack_exporter {

	public $pi1;
	public $ra;
	public $filter;
	public $filter_key;
	public $page_mode;

	public $file_path;
	public $file_name;
	public $file_new;

	public $info;
	public $error;

	public $EM_CONF;

	/**
	 * Initializes the export. The argument must be the plugin class
	 *
	 * @return void
	 */
	function initialize ( $pi1 ) {
		$this->pi1 =& $pi1;
		$this->ra  =& $pi1->ra;

		// Setup filter
		$this->filter = $this->pi1->extConf['filter'];
		//$this->filter_key = t3lib_div::shortMD5 ( serialize ( $this->filter ) );
		$this->filter_key = 'page'.strval ( $GLOBALS['TSFE']->id );

		// Setup export file path and name
		$this->file_path = $this->pi1->conf['export.']['path'];
		if ( !strlen($this->file_path) ) 
			$this->file_path = 'uploads/tx_sevenpack';

		$this->file_name = $this->pi1->extKey.'_'.$this->filter_key.'.dat';
		$this->file_new = FALSE;

		$_EXTKEY = $this->pi1->extKey;
		include ( $GLOBALS['TSFE']->tmpl->getFileName ( 'EXT:sevenpack/ext_emconf.php' ) );
		if ( is_array ( $EM_CONF ) ) {
			$this->EM_CONF = $EM_CONF[$this->pi1->extKey];
		}
	}


	/**
	 * Returns the composed path/file name
	 *
	 * @return The file address
	 */
	function get_file_rel() {
		return $this->file_path . '/' . $this->file_name;
	}


	/**
	 * Returns absolute system file path
	 *
	 * @return The absolute file path
	 */
	function get_file_abs() {
		return PATH_site . $this->get_file_rel();
	}


	/**
	 * Checks if the file exists and is newer than
	 * the latest change (tstamp) in the publication database
	 *
	 * @return TRUE if file exists and is newer than the
	 *         database content, FALSE otherwise.
	 */
	function file_is_newer ( $file ) {
		$db_time = $this->ra->fetch_max_tstamp ( );

		if ( file_exists ( $file ) ) {
			$ft = filemtime ( $file );
			if ( !($ft === FALSE) && ($db_time < $ft) ) {
				return TRUE;
			}
		}
		return FALSE;
	}


	/**
	 * This writes the filtered database content 
	 * to the export file
	 *
	 * @return TRUE ond error, FALSE otherwise
	 */
	function export ( ) {
		$ret = TRUE; // Means an error occurred
		$this->file_new = FALSE;
		$infoArr = array();

		$file_abs = $this->get_file_abs ( );

		if ( $this->file_is_newer ( $file_abs ) && !$this->pi1->extConf['debug'] ) {
			//t3lib_div::debug ( 'File exists '.$file_abs );
			return FALSE;
		} else {
			//t3lib_div::debug ( 'Writing file '.$file_abs );
		}

		$file_res = fopen ( $file_abs, 'w' );

		if ( $file_res )	{

			// Initialize fetching
			$this->ra->set_filter ( $this->filter );
			$this->ra->mFetch_initialize ();

			// Setup info array
			$infoArr['pubNum'] = $this->ra->mFetch_num();
			$infoArr['index'] = -1;

			// --- write file
			fwrite ( $file_res, $this->file_intro ( $infoArr ) );
			while ( $pub =  $this->ra->mFetch() )  {
				$infoArr['index']++;
				fwrite ( $file_res, $this->export_format_publication ( $pub, $infoArr ) );
			}
			fwrite ( $file_res, $this->file_outtro ( $infoArr ) );

			// --- clean up
			$this->ra->mFetch_finish();
			fclose ( $file_res );

			$this->info = $infoArr;
			$ret = FALSE; // All good
			$this->file_new = TRUE;
		} else {
			$this->error = $this->pi1->extKey.' error: Could not open file for writing.';
		}

		return $ret;
	}


	/**
	 * Formats one publication for the export
	 *
	 * @return The export string
	 */
	function export_format_publication ( $pub, $infoArr = array() )
	{
		return '';
	}


	/**
	 * Returns the file intro
	 *
	 * @return The file header string
	 */
	function file_intro ( $infoArr = array() )
	{
		return '';
	}


	/**
	 * Returns the file outtro
	 *
	 * @return The file header string
	 */
	function file_outtro ( $infoArr = array() )
	{
		return '';
	}


	/**
	 * Returns a general information text for the exported dataset
	 *
	 * @return A filter information string
	 */
	function info_text ( $infoArr = array() ) {
		$str = '';

		$num = intval ( $infoArr['pubNum'] );

		$str .= 'This file was created by the Typo3 extension' . "\n";
		$str .= $this->pi1->extKey;
		if ( is_array ( $this->EM_CONF ) ) {
			$str .= ' version ' . $this->EM_CONF['version'] . "\n";
		}
		$str .= "\n";
		$str .= '--- Timezone: ' . date('T') . "\n";
		$str .= 'Creation date: ' . date('Y-m-d') . "\n";
		$str .= 'Creation time: ' . date('H-i-s') . "\n";

		//$arr = $this->filter_info ( );
		//$str .= '--- Applied database filters'."\n";
		//if ( is_array ( $arr ) && sizeof ( $arr ) ) {
		//	foreach ( $arr as $f ) {
		//		$str .= preg_replace ( '/^(.?)/m', '% \1', $f );
		//	}
		//} else {
		//	$str .= 'none'."\n";
		//}

		if ( $num >= 0 ) {
			$str .= '--- Number of references'."\n";
			$str .= ''.$num."\n";
			$str .= ''."\n";
		}

		return $str;
	}


	/**
	 * Returns some database filter information
	 *
	 * @return A filter information string
	 */
	function filter_info ( )
	{
		$arr = array();

		$f =& $this->filter['year'];
		if ( $f && $f['enabled'] ) {
			$str = 'Year is (inclusive OR):'."\n";
			foreach ( $f['years'] as $y )
				$str .= '  '.$y."\n";
			foreach ( $f['ranges'] as $y )
				$str .= '  '.$y['from'].'-'.$y['to']."\n";
			$arr['year'] = $str;
		}

		$f =& $this->filter['author'];
		if ( $f && $f['enabled'] && sizeof ( $f['authors'] ) ) {
			if ( $f['rule'] == 1 ) {
				$str = 'Author is (AND):'."\n";
			} else {
				$str = 'Author is (inclusive OR):'."\n";
			}
			foreach ( $f['authors'] as $a ) {
				if ( strlen(trim($a)) )
					$fn = $a['fn'];
					$sn = $a['sn'];
					$str .= '  '.$sn;
					$str .= ', '.$fn."\n";
			}
			$arr['author'] = $str;
		}

		$f =& $this->filter['state'];
		if ( $f && $f['enabled'] ) {
			$str = 'State is (inclusive OR):'."\n";
			foreach ( $f['states'] as $s ) {
				$str .= '  ';
				$str .= $this->pi1->get_ll (
					$this->ra->refTable.'_state_I_'.$s,
					'Unknown state: '.$s, TRUE ) ;
				$str .= "\n";
			}
			$arr['state'] = $str;
		}

		$f =& $this->filter['bibtype'];
		if ( $f && $f['enabled'] ) {
			$str = 'Bibtype is (inclusive OR):'."\n";
			foreach ( $f['types'] as $t ) {
				$str .= '  '.$this->ra->allBibTypes[$t] . "\n";
			}
			$arr['bibtype'] = $str;
		}

		$f =& $this->filter['origin'];
		if ( $f && $f['enabled'] ) {
			$str = 'Origin is:'."\n";
			if ( $f['origin'] & 1 )
				$str .= '  local'."\n";
			if ( $f['origin'] & 2 )
				$str .= '  external'."\n";
			$arr['origin'] = $str;
		}

		$f =& $this->filter['citeid'];
		if ( $f && $f['enabled'] ) {
			$str = 'Citeid is (inclusive OR):'."\n";
			foreach ( $f['citeid']['ids'] as $c ) {
				$str .= '  '.$c . "\n";
			}
			$arr['citeid'] = $str;
		}

		return $arr;
	}

}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sevenpack/pi1/class.tx_sevenpack_exporter.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sevenpack/pi1/class.tx_sevenpack_exporter.php"]);
}


?>
