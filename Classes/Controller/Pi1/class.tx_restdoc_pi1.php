<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2013 Xavier Perseguers <xavier@causal.ch>
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
 * Plugin 'reST Documentation Viewer' for the 'restdoc' extension.
 *
 * @category    Plugin
 * @package     TYPO3
 * @subpackage  tx_restdoc
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   Causal Sàrl
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class tx_restdoc_pi1 extends tslib_pibase {

	public $prefixId      = 'tx_restdoc_pi1';
	public $scriptRelPath = 'Classes/Controller/Pi1/class.tx_restdoc_pi1.php';
	public $extKey        = 'restdoc';
	public $pi_checkCHash = TRUE;

	/** @var string */
	protected static $defaultFile = 'index';

	/** @var array */
	public $renderingConfig = array();

	/**
	 * Current chapter information as static to be accessible from
	 * TypoScript when coming back to generate menu entries
	 *
	 * @var array
	 */
	protected static $current = array();

	/** @var Tx_Restdoc_Reader_SphinxJson */
	protected static $sphinxReader;

	/**
	 * The main method of the Plugin.
	 *
	 * @param string $content The plugin content
	 * @param array $conf The plugin configuration
	 * @return string The content that is displayed on the website
	 * @throws RuntimeException
	 */
	public function main($content, array $conf) {
		$this->init($conf);
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;    // USER_INT object

		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			$storageConfiguration = self::$sphinxReader->getStorage()->getConfiguration();
			$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
		} else {
			$basePath = PATH_site;
		}

		$documentRoot = $basePath . rtrim($this->conf['path'], '/') . '/';
		$document = self::$defaultFile . '/';
		$pathSeparators = isset($this->conf['fallbackPathSeparators']) ? t3lib_div::trimExplode(',', $this->conf['fallbackPathSeparators'], TRUE) : array();
		$pathSeparators[] = $this->conf['pathSeparator'];
		if (isset($this->piVars['doc']) && strpos($this->piVars['doc'], '..') === FALSE) {
			$document = str_replace($pathSeparators, '/', $this->piVars['doc']) . '/';
		}

		// Sources are requested, if allowed and available, return them
		if ($this->conf['publishSources'] && t3lib_div::isFirstPartOfStr($document, '_sources/')) {
			$sourceFile = rtrim($document, '/');
			// Security check
			if (substr($sourceFile, -4) === '.txt' && substr(realpath($documentRoot . $sourceFile), 0, strlen(realpath($documentRoot))) === realpath($documentRoot)) {
				// Will exit program normally
				Tx_Restdoc_Utility_Helper::showSources($documentRoot . $sourceFile);
			}
		}

		self::$sphinxReader
			->setPath($documentRoot)
			->setDocument($document)
			->setKeepPermanentLinks(!$this->conf['showPermanentLink'])
			->setDefaultFile($this->conf['defaultFile'])
			// TODO: only for TOC, BREADCRUMB, ... ? (question's context is when generating the general index)
			->enableDefaultDocumentFallback();

		try {
			if (!self::$sphinxReader->load()) {
				throw new RuntimeException('Document failed to load', 1365166377);
			};
		} catch (RuntimeException $e) {
			return $e->getMessage();
		}

		$jsonData = self::$sphinxReader->getData();
		$skipDefaultWrap = FALSE;

		self::$current = array(
			'path'          => $this->conf['path'],
			'pathSeparator' => $this->conf['pathSeparator'],
		);

		if (!isset($jsonData['genindexentries'])) {
			switch ($this->conf['mode']) {
				case 'TOC':
					$this->renderingConfig = $this->conf['setup.']['TOC.'];
					$output = $this->cObj->cObjGetSingle($this->renderingConfig['renderObj'], $this->renderingConfig['renderObj.']);
					break;
				case 'RECENT':
					$this->renderingConfig = $this->conf['setup.']['RECENT.'];
					$output = $this->cObj->cObjGetSingle($this->renderingConfig['renderObj'], $this->renderingConfig['renderObj.']);
					break;
				case 'BODY':
					if ($this->conf['advertiseSphinx']) {
						$this->advertiseSphinx();
					}
					$output = $this->generateBody();
					break;
				case 'TITLE':
					$output = isset($jsonData['title']) ? $jsonData['title'] : '';
					$skipDefaultWrap = TRUE;
					break;
				case 'QUICK_NAVIGATION':
					$output = $this->generateQuickNavigation();
					break;
				case 'BREADCRUMB':
					$this->renderingConfig = $this->conf['setup.']['BREADCRUMB.'];
					$output = $this->cObj->cObjGetSingle($this->renderingConfig['renderObj'], $this->renderingConfig['renderObj.']);
					break;
				case 'FILENAME':
					$output = self::$sphinxReader->getJsonFilename();
					$skipDefaultWrap = TRUE;
					break;
				case 'SEARCH':
					$output = $this->generateSearchForm();
					break;
				default:
					$output = '';
					break;
			}
		} else {
			switch ($this->conf['mode']) {
				case 'BODY':
					if ($this->conf['advertiseSphinx']) {
						$this->advertiseSphinx();
					}
					// Generating output for the general index
					$output = $this->generateIndex($documentRoot, $document, $jsonData);
					break;
				case 'TITLE':
					$output = $this->pi_getLL('index_title', 'Index');
					$skipDefaultWrap = TRUE;
					break;
				case 'FILENAME':
					$output = self::$sphinxReader->getJsonFilename();
					$skipDefaultWrap = TRUE;
					break;
				default:
					// Generating TOC, ... for the root document instead
					$this->piVars['doc'] = '';
					return $this->main('', $conf);
			}
		}

		// Hook for post-processing the output
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['renderHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['renderHook'] as $classRef) {
				$hookObject = t3lib_div::getUserObj($classRef);
				$params = array(
					'mode' => $this->conf['mode'],
					'documentRoot' => $documentRoot,
					'document' => $document,
					'output' => &$output,
					'config' => $this->conf,
					'pObj' => $this,
				);
				if (is_callable(array($hookObject, 'postProcessOutput'))) {
					$hookObject->postProcessOutput($params);
				}
			}
		}

		// Wrap the whole result, with baseWrap if defined, else with standard pi_wrapInBaseClass() call
		if (isset($this->conf['baseWrap.'])) {
			$output = $this->cObj->stdWrap($output, $this->conf['baseWrap.']);
		} elseif (!$skipDefaultWrap) {
			$output = $this->pi_wrapInBaseClass($output);
		}

		return $output;
	}

	/**
	 * Returns the default file.
	 *
	 * @return string
	 */
	public function getDefaultFile() {
		return self::$defaultFile;
	}

	/**
	 * Returns the Sphinx Reader.
	 *
	 * @return Tx_Restdoc_Reader_SphinxJson
	 */
	public function getSphinxReader() {
		return self::$sphinxReader;
	}

	/**
	 * Generates the array for rendering the reST menu in TypoScript.
	 *
	 * @param string $content
	 * @param array $conf
	 * @return array
	 */
	public function makeMenuArray($content, array $conf) {
		$data = array();
		$type = isset($conf['userFunc.']['type']) ? $conf['userFunc.']['type'] : 'menu';

		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			$storageConfiguration = self::$sphinxReader->getStorage()->getConfiguration();
			$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
		} else {
			$basePath = PATH_site;
		}

		$documentRoot = self::$sphinxReader->getPath();
		$document = self::$sphinxReader->getDocument();
		$jsonData = self::$sphinxReader->getData();

		switch ($type) {
			case 'menu':
				$toc = self::$sphinxReader->getTableOfContents(array($this, 'getLink'));

				$data = $toc ? Tx_Restdoc_Utility_Helper::getMenuData(Tx_Restdoc_Utility_Helper::xmlstr_to_array($toc)) : array();

				// Mark the first entry as 'active'
				$data[0]['ITEM_STATE'] = 'CUR';
				break;

			case 'previous':
				if (isset($jsonData['prev'])) {
					$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($documentRoot . $document, '../' . $jsonData['prev']['link']);
					$link = $this->getLink(substr($absolute, strlen($documentRoot)));
					$data[] = array(
						'title' => $jsonData['prev']['title'],
						'_OVERRIDE_HREF' => $link,
					);
				}
				break;

			case 'next':
				if (isset($jsonData['next'])) {
					$nextDocument = $document === $this->getDefaultFile() . '/' ? $documentRoot : $documentRoot . $document;
					$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($nextDocument, '../' . $jsonData['next']['link']);
					$link = $this->getLink(substr($absolute, strlen($documentRoot)));
					$data[] = array(
						'title' => $jsonData['next']['title'],
						'_OVERRIDE_HREF' => $link,
					);
				}
				break;

			case 'breadcrumb':
				foreach ($jsonData['parents'] as $parent) {
					$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($documentRoot . $document, '../' . $parent['link']);
					$link = $this->getLink(substr($absolute, strlen($documentRoot)));
					$data[] = array(
						'title' => $parent['title'],
						'_OVERRIDE_HREF' => $link,
					);
				}
				// Add current page to breadcrumb menu
				$data[] = array(
					'title' => $jsonData['title'],
					'_OVERRIDE_HREF' => $this->getLink($document),
					'ITEM_STATE' => 'CUR',
				);
				break;

			case 'updated':
				$limit = t3lib_utility_Math::forceIntegerInRange($conf['limit'], 0, 100);	// max number of items
				$maxAge = intval(tslib_cObj::calc($conf['maxAge']));
				$sortField = 'crdate';
				$extraWhere = '';
				if (!empty($conf['excludeChapters'])) {
					$excludeChapters = array_map(
						function ($chapter) {
							return $GLOBALS['TYPO3_DB']->fullQuoteStr($chapter, 'tx_restdoc_toc');
						},
						t3lib_div::trimExplode(',', $conf['excludeChapters'])
					);
					if (count($excludeChapters) > 0) {
						$extraWhere .= ' AND document NOT IN (' . implode(',', $excludeChapters) . ')';
					}
				}
				if ($maxAge > 0) {
					$extraWhere .= ' AND ' . $sortField . '>' . ($GLOBALS['SIM_ACCESS_TIME'] - $maxAge);
				}
				// TODO: prefix root entries with the storage UID when using FAL, to prevent clashes with multiple
				//       directories with similar names
				$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
					'*',
					'tx_restdoc_toc',
					'root=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(substr($documentRoot, strlen($basePath)), 'tx_restdoc_toc') .
						$extraWhere,
					'',
					$sortField . ' DESC',
					$limit
				);
				foreach ($rows as $row) {
					$data[] = array(
						'title' => $row['title'] ?: '[no title]',
						'_OVERRIDE_HREF' => $row['url'],
						'SYS_LASTCHANGED' => $row[$sortField],
					);
				}
				break;
		}

		// Hook for post-processing the menu entries
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['makeMenuArrayHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['makeMenuArrayHook'] as $classRef) {
				$hookObject = t3lib_div::getUserObj($classRef);
				$params = array(
					'documentRoot' => $documentRoot,
					'document' => $document,
					'data' => &$data,
					'pObj' => $this,
				);
				if (is_callable(array($hookObject, 'postProcessMakeMenuArray'))) {
					$hookObject->postProcessTOC($params);
				}
			}
		}

		return $data;
	}

	/**
	 * Advertises Sphinx.
	 *
	 * @return void
	 */
	protected function advertiseSphinx() {
		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			$storageConfiguration = self::$sphinxReader->getStorage()->getConfiguration();
			$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
		} else {
			$basePath = PATH_site;
		}
		$metadata = Tx_Restdoc_Utility_Helper::getMetadata($basePath . $this->conf['path']);
		if (!empty($metadata['release'])) {
			$version = $metadata['release'];
		} elseif (!empty($metadata['version'])) {
			$version = $metadata['version'];
		} else {
			$version = '1.0.0';
		}

		$urlRoot = str_replace('___PLACEHOLDER___', '', $this->getLink('___PLACEHOLDER___/', TRUE, $this->conf['rootPage']));
		// Support for RealURL
		if (substr($urlRoot, -6) === '/.html') {
			$urlRoot = substr($urlRoot, 0, strlen($urlRoot) - 5);	// .html suffix is not a must have
		}
		$hasSource = isset($metadata['has_source']) && $metadata['has_source'] && $this->conf['publishSources'];
		$hasSource = $hasSource ? 'true' : 'false';
		$separator = urlencode(self::$current['pathSeparator']);

		$GLOBALS['TSFE']->additionalJavaScript[$this->prefixId . '_sphinx'] = <<<JS
var DOCUMENTATION_OPTIONS = {
	URL_ROOT:    '$urlRoot',
	VERSION:     '$version',
	COLLAPSE_INDEX: false,
	FILE_SUFFIX: '',
	HAS_SOURCE:  $hasSource,
	SEPARATOR: '$separator'
};
JS;
	}

	/**
	 * Generates the Quick Navigation.
	 *
	 * @return string
	 */
	protected function generateQuickNavigation() {
		$this->renderingConfig = $this->conf['setup.']['QUICK_NAVIGATION.'];

		$documentRoot = self::$sphinxReader->getPath();
		$document = self::$sphinxReader->getDocument();
		$jsonData = self::$sphinxReader->getData();

		$data = array();
		$data['home_title'] = $this->pi_getLL('home_title', 'Home');
		$data['home_uri'] = $this->getLink('');
		$data['home_uri_absolute'] = $this->getLink('', TRUE);

		if (isset($jsonData['prev'])) {
			$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($documentRoot . $document, '../' . $jsonData['prev']['link']);
			$link = $this->getLink(substr($absolute, strlen($documentRoot)));
			$linkAbsolute = $this->getLink(substr($absolute, strlen($documentRoot)), TRUE);

			$data['previous_title'] = $jsonData['prev']['title'];
			$data['previous_uri'] = $link;
			$data['previous_uri_absolute'] = $linkAbsolute;
		}

		if (isset($jsonData['next'])) {
			$nextDocument = $document === $this->getDefaultFile() . '/' ? $documentRoot : $documentRoot . $document;
			$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($nextDocument, '../' . $jsonData['next']['link']);
			$link = $this->getLink(substr($absolute, strlen($documentRoot)));
			$linkAbsolute = $this->getLink(substr($absolute, strlen($documentRoot)), TRUE);

			$data['next_title'] = $jsonData['next']['title'];
			$data['next_uri'] = $link;
			$data['next_uri_absolute'] = $linkAbsolute;
		}

		if (count($jsonData['parents']) > 0) {
			$parent = array_pop($jsonData['parents']);
			$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($documentRoot . $document, '../' . $parent['link']);
			$link = $this->getLink(substr($absolute, strlen($documentRoot)));
			$linkAbsolute = $this->getLink(substr($absolute, strlen($documentRoot)), TRUE);

			$data['parent_title'] = $parent['title'];
			$data['parent_uri'] = $link;
			$data['parent_uri_absolute'] = $linkAbsolute;
		}

		if (is_file($documentRoot . 'genindex.fjson')) {
			$link = $this->getLink('genindex/');
			$linkAbsolute = $this->getLink('genindex/', TRUE);

			$data['index_title'] = $this->pi_getLL('index_title', 'Index');
			$data['index_uri'] = $link;
			$data['index_uri_absolute'] = $linkAbsolute;
		}

		$data['has_previous'] = !empty($data['previous_uri']) ? 1 : 0;
		$data['has_next']     = !empty($data['next_uri'])     ? 1 : 0;
		$data['has_parent']   = !empty($data['parent_uri'])   ? 1 : 0;
		$data['has_index']    = !empty($data['index_uri'])    ? 1 : 0;

		// Hook for post-processing the quick navigation
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['quickNavigationHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['quickNavigationHook'] as $classRef) {
				$hookObject = t3lib_div::getUserObj($classRef);
				$params = array(
					'documentRoot' => $documentRoot,
					'document' => $document,
					'data' => &$data,
					'pObj' => $this,
				);
				if (is_callable(array($hookObject, 'postProcessQUICK_NAVIGATION'))) {
					$hookObject->postProcessQUICK_NAVIGATION($params);
				}
			}
		}

		if ($this->conf['addHeadPagination']) {
			$paginationPattern = '<link rel="%s" title="%s" href="%s" />';

			if ($data['has_parent']) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_parent'] = sprintf(
					$paginationPattern,
					'top',
					htmlspecialchars($data['parent_title']),
					str_replace('&', '&amp;', $data['parent_uri_absolute'])
				);
			}
			if ($data['has_previous']) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_previous'] = sprintf(
					$paginationPattern,
					'prev',
					htmlspecialchars($data['previous_title']),
					str_replace('&', '&amp;', $data['previous_uri_absolute'])
				);
			}
			if ($data['has_next']) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_next'] = sprintf(
					$paginationPattern,
					'next',
					htmlspecialchars($data['next_title']),
					str_replace('&', '&amp;', $data['next_uri_absolute'])
				);
			}
		}

		/** @var $contentObj tslib_cObj */
		$contentObj = t3lib_div::makeInstance('tslib_cObj');
		$contentObj->start($data);
		$output = $contentObj->cObjGetSingle($this->renderingConfig['renderObj'], $this->renderingConfig['renderObj.']);

		return $output;
	}

	/**
	 * Generates the general index.
	 *
	 * @param string $documentRoot
	 * @param string $document
	 * @param array $jsonData
	 * @return string
	 */
	protected function generateIndex($documentRoot, $document, array $jsonData) {
		$linksCategories = array();
		$contentCategories = array();

		foreach ($jsonData['genindexentries'] as $indexGroup) {
			$category = $indexGroup[0];
			$anchor = 'tx-restdoc-index-' . htmlspecialchars($category);

			$conf = array(
				$this->prefixId => array(
					'doc' => str_replace('/', $this->conf['pathSeparator'], substr($document, 0, strlen($document) - 1)),
				)
			);
			$link = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $conf);
			$link .= '#' . $anchor;

			$linksCategories[] = '<a href="' . $link . '"><strong>' . htmlspecialchars($category) . '</strong></a>';

			$contentCategory = '<h2 id="' . $anchor . '">' . htmlspecialchars($category) . '</h2>' . LF;
			$contentCategory .= '<div class="tx-restdoc-genindextable">' . LF;
			$contentCategory .= $this->getIndexDefinitionList($documentRoot, $indexGroup[1]);
			$contentCategory .= '</div>' . LF;

			$contentCategories[] = $contentCategory;
		}

		$output = '<h1>' . $this->pi_getLL('index_title', 'Index', TRUE) . '</h1>' . LF;
		$output .= '<div class="tx-restdoc-genindex-jumpbox">' . implode(' | ', $linksCategories) . '</div>' . LF;
		$output .= implode(LF, $contentCategories);

		return $output;
	}

	/**
	 * Returns an index definition list as HTML.
	 *
	 * @param string $documentRoot
	 * @param array $index
	 * @return string
	 */
	protected function getIndexDefinitionList($documentRoot, array $index) {
		$output = '<dl>' . LF;
		foreach ($index as $dt) {
			$relativeLinks = array();
			for ($i = 0; $i < count($dt[1]); $i++) {
				if (!empty($dt[1][$i]) && t3lib_div::isFirstPartOfStr($dt[1][$i][1], '../')) {
					$relativeLinks[] = array(
						'title' => $dt[1][$i][0],
						'link'  => substr($dt[1][$i][1], 3),
					);
				} elseif ($i == 0 && !empty($dt[1][$i]) && is_array($dt[1][$i][0]) && t3lib_div::isFirstPartOfStr($dt[1][$i][0][1], '../')) {
					$relativeLinks[] = array(
						'title' => $dt[1][$i][0][0],
						'link'  => substr($dt[1][$i][0][1], 3),
					);
				} else {
					// No more entry links, we have subentries from now on
					break;
				}
			}
			// Remove category links from the list of subentries, first subentry is always a link, possibly empty
			for ($i = 0; $i < max(1, count($relativeLinks)); $i++) {
				array_shift($dt[1]);
			}

			$output .= '<dt>';
			if ($relativeLinks) {
				for ($i = 0; $i < count($relativeLinks); $i++) {
					if ($i == 0) {
						$title = htmlspecialchars($dt[0]);
					} else {
						$output .= ', ';
						$title = '[' . $i . ']';
					}
					if ($relativeLinks[$i]['title'] === 'main') {
						$title = '<strong>' . $title . '</strong>';
					}
					$link = $this->getLink($relativeLinks[$i]['link']);
					$link = str_replace('&amp;', '&', $link);
					$link = str_replace('&', '&amp;', $link);

					$output .= '<a href="' . $link . '">' . $title . '</a>';
				}
			} else {
				$output .= htmlspecialchars($dt[0]);
			}
			$output .= '</dt>' . LF;

			if ($dt[1]) {
				$output .= '<dd>' . LF;
				foreach ($dt[1] as $term) {
					$output .= $this->getIndexDefinitionList($documentRoot, $term);
				}
				$output .= '</dd>' . LF;
			}
		}
		$output .= '</dl>' . LF;

		return $output;
	}

	/**
	 * Generates the Body.
	 *
	 * @return string
	 */
	protected function generateBody() {
		$this->renderingConfig = $this->conf['setup.']['BODY.'];
		$body = self::$sphinxReader->getBody(
			array($this, 'getLink'),
			array($this, 'processImage')
		);
		return $body;
	}

	/**
	 * Generates the search form.
	 *
	 * @return string
	 */
	protected function generateSearchForm() {
		$searchIndexFile = self::$sphinxReader->getPath() . 'searchindex.json';
		if (!is_file($searchIndexFile)) {
			return 'ERROR: File ' . $this->conf['path'] . 'searchindex.json was not found.';
		}

		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			$storageConfiguration = self::$sphinxReader->getStorage()->getConfiguration();
			$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
		} else {
			$basePath = PATH_site;
		}

		$metadata = Tx_Restdoc_Utility_Helper::getMetadata($basePath . $this->conf['path']);
		$sphinxVersion = isset($metadata['sphinx_version']) ? $metadata['sphinx_version'] : '1.1.3';

		$config = array(
			'jsLibs' => array(
				'Resources/Public/JavaScript/underscore.js',
				'Resources/Public/JavaScript/doctools.js',
				// Sphinx search library differs in branch v1.2
				t3lib_div::isFirstPartOfStr($sphinxVersion, '1.2')
					? 'Resources/Public/JavaScript/searchtools.12.js'
					: 'Resources/Public/JavaScript/searchtools.js'
			),
			'jsInline' => '',
			'advertiseSphinx' => TRUE,
		);

		$searchIndexContent = file_get_contents($searchIndexFile);
		$config['jsInline'] = <<<JS
jQuery(function() { Search.setIndex($searchIndexContent); });
JS;

		// Hook for pre-processing the search form
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['searchFormHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['searchFormHook'] as $classRef) {
				$hookObject = t3lib_div::getUserObj($classRef);
				$params = array(
					'config' => &$config,
					'pObj' => $this,
				);
				if (is_callable(array($hookObject, 'preProcessSEARCH'))) {
					$hookObject->preProcessSEARCH($params);
				}
			}
		}

		foreach ($config['jsLibs'] as $jsLib) {
			$this->includeJsFile($jsLib);
		}
		if ($config['advertiseSphinx']) {
			$this->advertiseSphinx();
		}
		if ($config['jsInline']) {
			$GLOBALS['TSFE']->additionalJavaScript[$this->extKey . '_search'] = $config['jsInline'];
		}

		$action = t3lib_div::getIndpEnv('REQUEST_URI');
		$parameters = array();
		if (($pos = strpos($action, '?')) !== FALSE) {
			$parameters = t3lib_div::trimExplode('&', substr($action, $pos + 1));
			$action = substr($action, 0, $pos);
		}
		$hiddenFields = '';
		foreach ($parameters as $parameter) {
			list($key, $value) = explode('=', $parameter);
			if ($key === 'q') continue;
			$hiddenFields .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . LF;
		}
		$searchPlaceholder = $this->pi_getLL('search_placeholder', 'search', TRUE);
		$searchAction = $this->pi_getLL('search_action', 'search', TRUE);

		return <<<HTML
<form action="$action" method="get">
$hiddenFields
<input type="search" name="q" value="" placeholder="$searchPlaceholder" />
<input type="submit" value="$searchAction" />
<span id="search-progress" style="padding-left: 10px"></span>
</form>

<div id="search-results">

</div>
HTML;

	}

	/**
	 * Includes a JavaScript library in header.
	 *
	 * @param string $file
	 * @return void
	 */
	protected function includeJsFile($file) {
		$relativeFile = substr(t3lib_extMgm::extPath($this->extKey), strlen(PATH_site)) . $file;
		$relativeFile = $this->cObj->typoLink_URL(array('parameter' => $relativeFile));
		$GLOBALS['TSFE']->additionalHeaderData[$relativeFile] = '<script type="text/javascript" src="' . $relativeFile . '"></script>';
	}

	/**
	 * Generates a link to navigate within a reST documentation project.
	 *
	 * @param string $document Target document
	 * @param boolean $absolute Whether absolute URI should be generated
	 * @param integer $rootPage UID of the page showing the documentation
	 * @return string
	 * @private This method is made public to be accessible from a lambda-function scope
	 */
	public function getLink($document, $absolute = FALSE, $rootPage = 0) {
		$urlParameters = array();
		$anchor = '';
		if ($document !== '') {
			if (($pos = strrpos($document, '#')) !== FALSE) {
				$anchor = substr($document, $pos + 1);
				$document = substr($document, 0, $pos);
			}
			$doc = str_replace('/', self::$current['pathSeparator'], substr($document, 0, strlen($document) - 1));
			if ($doc) {
				$urlParameters = array(
					$this->prefixId => array(
						'doc' => $doc,
					)
				);
			}
		}
		if (substr($document, 0, 11) === '_downloads/') {
			$link = $this->cObj->typoLink_URL(array('parameter' => rtrim(self::$current['path'], '/') . '/' . $document));
		} else {
			$typolinkConfig = array(
				'parameter' => $rootPage ?: $GLOBALS['TSFE']->id,
				'forceAbsoluteUrl' => $absolute ? 1 : 0,
				'forceAbsoluteUrl.' => array(
					'scheme' => t3lib_div::getIndpEnv('TYPO3_SSL') ? 'https' : 'http',
				),
				'returnLast' => 'url',
			);
			if ($urlParameters) {
				$typolinkConfig['additionalParams'] = t3lib_div::implodeArrayForUrl('', $urlParameters);
			}
			$link = $this->cObj->typoLink('', $typolinkConfig);
			if ($anchor !== '') {
				$link .= '#' . $anchor;
			}
		}
		return $link;
	}

	/**
	 * Processes an image.
	 *
	 * @param array $data
	 * @return string
	 * @private This method is made public to be accessible from a lambda-function scope
	 */
	public function processImage(array $data) {
		/** @var $contentObj tslib_cObj */
		$contentObj = t3lib_div::makeInstance('tslib_cObj');
		$contentObj->start($data);

		return $contentObj->cObjGetSingle(
			$this->renderingConfig['image.']['renderObj'],
			$this->renderingConfig['image.']['renderObj.']
		);
	}

	/**
	 * Applies stdWrap to a given key in a configuration array.
	 *
	 * @param array &$conf
	 * @param string $baseKey
	 * @return void
	 */
	protected function applyStdWrap(array &$conf, $baseKey) {
		if (isset($conf[$baseKey . '.'])) {
			$conf[$baseKey] = $this->cObj->stdWrap($conf[$baseKey], $conf[$baseKey . '.']);
			unset($conf[$baseKey . '.']);
		}
	}

	/**
	 * This method performs various initializations.
	 *
	 * @param array $conf: Plugin configuration, as received by the main() method
	 * @return void
	 */
	protected function init(array $conf) {
		$this->conf = $conf;

		if (isset($this->conf['oldStaticInclude']) && $this->conf['oldStaticInclude']) {
			t3lib_div::deprecationLog('EXT:' . $this->extKey . ' - Inclusion of old static TS. This is deprecated since 1.2.0 and will be removed in 1.4.0.');
		}

		// Apply stdWrap on a few TypoScript configuration options
		$this->applyStdWrap($this->conf, 'path');
		$this->applyStdWrap($this->conf, 'defaultFile');
		$this->applyStdWrap($this->conf, 'mode');
		$this->applyStdWrap($this->conf, 'rootPage');
		$this->applyStdWrap($this->conf, 'showPermanentLink');
		$this->applyStdWrap($this->conf, 'pathSeparator');
		$this->applyStdWrap($this->conf, 'fallbackPathSeparators');
		$this->applyStdWrap($this->conf, 'documentStructureMaxDocuments');
		$this->applyStdWrap($this->conf, 'advertiseSphinx');
		$this->applyStdWrap($this->conf, 'addHeadPagination');
		$this->applyStdWrap($this->conf, 'publishSources');

		if (isset($this->conf['setup.'])) {
			// @deprecated since 1.2.0, will be removed in 1.4.0
			$this->applyStdWrap($this->conf['setup.'], 'defaultFile');
			if (isset($this->conf['setup.']['defaultFile'])) {
				t3lib_div::deprecationLog('EXT:' . $this->extKey . ' - TypoScript plugin.' . $this->prefixId . '.setup.defaultFile is deprecated since 1.2.0 and will be removed in 1.4.0. Please use plugin.' . $this->prefixId . '.defaultFile instead.');
				$this->conf['defaultFile'] = $this->conf['setup.']['defaultFile'];
			}
		}

		// Load the flexform and loop on all its values to override TS setup values
		// Some properties use a different test (more strict than not empty) and yet some others no test at all
		// see http://wiki.typo3.org/index.php/Extension_Development,_using_Flexforms
		$this->pi_initPIflexForm(); // Init and get the flexform data of the plugin

		// Assign the flexform data to a local variable for easier access
		$piFlexForm = $this->cObj->data['pi_flexform'];

		if (is_array($piFlexForm['data'])) {
			$multiValueKeys = array();
			// Traverse the entire array based on the language
			// and assign each configuration option to $this->settings array...
			foreach ($piFlexForm['data'] as $sheet => $data) {
				foreach ($data as $lang => $value) {
					/** @var $value array */
					foreach ($value as $key => $val) {
						$value = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
						if (trim($value) !== '' && in_array($key, $multiValueKeys)) {
							// Funny, FF contains a comma-separated list of key|value and
							// we only want to have key...
							$tempValues = explode(',', $value);
							$tempKeys = array();
							foreach ($tempValues as $tempValue) {
								list($k, $v) = explode('|', $tempValue);
								$tempKeys[] = $k;
							}
							$value = implode(',', $tempKeys);
						}
						if (trim($value) !== '' || !isset($this->conf[$key])) {
							$this->conf[$key] = $value;
						}
					}
				}
			}
		}

		self::$sphinxReader = t3lib_div::makeInstance('Tx_Restdoc_Reader_SphinxJson');

		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			if (preg_match('/^file:(\d+):(.*)$/', $this->conf['path'], $matches)) {
				/** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
				$storageRepository = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\StorageRepository');
				/** @var $storage \TYPO3\CMS\Core\Resource\ResourceStorage */
				$storage = $storageRepository->findByUid(intval($matches[1]));
				$storageRecord = $storage->getStorageRecord();
				if ($storageRecord['driver'] === 'Local') {
					$this->conf['path'] = substr($matches[2], 1);
					self::$sphinxReader->setStorage($storage);
				} else {
					throw new RuntimeException('Access to the documentation requires an unsupported driver: ' . $storageRecord['driver'], 1365688549);
				}
			}
		}

		if (isset($this->conf['defaultFile'])) {
			self::$defaultFile = $this->conf['defaultFile'];
		}
		if (empty($this->conf['pathSeparator'])) {
			// The path separator CANNOT be empty
			$this->conf['pathSeparator'] = '|';
		}
		$this->conf['rootPage'] = intval($this->conf['rootPage']);
	}

	/**
	 * Loads the locallang file.
	 *
	 * @return void
	 */
	public function pi_loadLL() {
		if (!$this->LOCAL_LANG_loaded && $this->scriptRelPath) {
			$basePath = 'EXT:' . $this->extKey . '/Resources/Private/Language/locallang.xml';

			// Read the strings in the required charset (since TYPO3 4.2)
			$this->LOCAL_LANG = t3lib_div::readLLfile($basePath, $this->LLkey, $GLOBALS['TSFE']->renderCharset);
			if ($this->altLLkey) {
				$tempLOCAL_LANG = t3lib_div::readLLfile($basePath, $this->altLLkey);
				$this->LOCAL_LANG = array_merge(is_array($this->LOCAL_LANG) ? $this->LOCAL_LANG : array(), $tempLOCAL_LANG);
				unset($tempLOCAL_LANG);
			}

			// Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
			$confLL = $this->conf['_LOCAL_LANG.'];
			if (is_array($confLL)) {
				foreach ($confLL as $k => $lA) {
					if (is_array($lA)) {
						$k = substr($k, 0, -1);
						foreach ($lA as $llK => $llV) {
							if (!is_array($llV)) {
								if (version_compare(TYPO3_version, '4.6.0', '>=')) {
									// Internal structure is from XLIFF
									$this->LOCAL_LANG[$k][$llK][0]['target'] = $llV;
								} else {
									// Internal structure is from ll-XML
									$this->LOCAL_LANG[$k][$llK] = $llV;
								}

								// For labels coming from the TypoScript (database) the charset is assumed to be "forceCharset" and if that is not set, assumed to be that of the individual system languages
								$this->LOCAL_LANG_charset[$k][$llK] = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->csConvObj->charSetArray[$k];
							}
						}
					}
				}
			}
		}
		$this->LOCAL_LANG_loaded = 1;
	}

}

?>