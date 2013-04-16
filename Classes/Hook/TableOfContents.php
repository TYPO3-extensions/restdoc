<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Xavier Perseguers <xavier@causal.ch>
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
 * Storing the documentation structure into the database for integration
 * within EXT:dd_googlesitemap.
 *
 * @category    Hooks
 * @package     TYPO3
 * @subpackage  tx_restdoc
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   Causal Sàrl
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class Tx_Restdoc_Hook_TableOfContents {

	/**
	 * Maximum number of timestamps to save
	 */
	const MAX_ENTRIES = 5;

	/**
	 * Max age of a cache entry without having been refreshed
	 */
	const CACHE_MAX_AGE = 7776000;	// 86400 * 30 * 3 = 3 months

	/** @var tx_restdoc_pi1 */
	protected $pObj;

	/** @var string */
	protected $root;

	/** @var integer */
	protected $pageId;

	/** @var integer */
	protected $maxDocuments;

	/** @var array */
	protected $processedDocuments = array();

	/**
	 * Stores the TOC into database for future inclusion within EXT:dd_googlesitemap.
	 *
	 * @param array $params
	 * @return void
	 */
	public function postProcessOutput(array $params) {
		$this->pObj = $params['pObj'];

		if ($params['mode'] !== 'BODY') {
			return;
		}

		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			$storageConfiguration = $this->pObj->getSphinxReader()->getStorage()->getConfiguration();
			$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
		} else {
			$basePath = PATH_site;
		}

		$this->maxDocuments = t3lib_utility_Math::forceIntegerInRange($params['config']['documentStructureMaxDocuments'], 1, 9999);
		$this->pageId = intval($this->pObj->cObj->data['pid']);
		$this->root = substr($params['documentRoot'], strlen($basePath));
		$this->flushCache();

		$this->extractLinks($params);
	}

	/**
	 * Extracts the links recursively.
	 *
	 * @param array $params
	 * @return void
	 */
	protected function extractLinks(array $params) {
		$jsonFile = substr($params['document'], 0, strlen($params['document']) - 1) . '.fjson';
		$filename = $params['documentRoot'] . $jsonFile;
		if (!is_file($filename)) {
			return;
		}

		$lastModification = filemtime($filename);
		$content = file_get_contents($filename);
		$checksum = md5($content);

		$data = array(
			'tstamp' => $GLOBALS['ACCESS_TIME'],
		);
		$cachedData = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'*',
			'tx_restdoc_toc',
			'pid=' . $this->pageId .
				' AND document=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($params['document'], 'tx_restdoc_toc')
		);
		$add = !is_array($cachedData);
		$refresh = (!$add && $cachedData['checksum'] !== $checksum);
		if ($add) {
			$modifications = array();
			$data['pid']      = $this->pageId;
			$data['root']     = $this->root;
			$data['document'] = $params['document'];
			$data['url']      = $this->pObj->getLink($params['document'], TRUE);
		} else {
			$modifications = t3lib_div::intExplode(',', $cachedData['lastmod'], TRUE);
		}
		if ($add || $refresh) {
			$modifications[] = $lastModification;
			$jsonData = json_decode($content, TRUE);
			$data['title'] = isset($jsonData['title']) ? $jsonData['title'] : '';
			$data['checksum'] = $checksum;

			$links = $this->processToc($content, $params);
			foreach ($links as $doc => $href) {
				if (!in_array($doc, $this->processedDocuments)) {
					$this->processedDocuments[] = $doc;
					if ($doc !== $params['document']) {
						$p2 = $params;
						$p2['document'] = $doc;
						$this->extractLinks($p2);
					}
				}
				if (count($this->processedDocuments) >= $this->maxDocuments) {
					break;
				}
			}
		}

		if (count($modifications) > self::MAX_ENTRIES) {
			$modifications = array_slice($modifications, -self::MAX_ENTRIES);
		}
		$data['lastmod'] = implode(',', $modifications);
		$data['crdate'] = $lastModification;

		if ($add) {
			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_restdoc_toc',
				$data
			);
		} elseif ($refresh) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tx_restdoc_toc',
				'pid=' . $this->pageId .
					' AND document=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($params['document'], 'tx_restdoc_toc'),
				$data
			);
		}

	}

	/**
	 * Processes the TOC and its subpages and store an absolute URL to the page.
	 *
	 * @param string $content
	 * @param array $params
	 * @return array
	 */
	protected function processToc($content, array $params) {
		$jsonData = json_decode($content, TRUE);
		$links = $this->extractToc($jsonData, $params);
		return $links;
	}

	/**
	 * Extracts the table of contents.
	 *
	 * @param array $jsonData
	 * @param array $params
	 * @return array
	 * @see tx_restdoc_pi1::generateTableOfContents()
	 */
	protected function extractToc(array $jsonData, array $params) {
		// Replace links in table of contents
		$toc = $this->replaceLinks($params['documentRoot'], $params['document'], $jsonData['toc']);
		// Remove empty sublevels
		$toc = preg_replace('#<ul>\s*</ul>#', '', $toc);
		// Fix TOC to make it XML compliant
		$toc = preg_replace_callback('# href="([^"]+)"#', function($matches) {
			$url = str_replace('&amp;', '&', $matches[1]);
			$url = str_replace('&', '&amp;', $url);
			return ' href="' . $url . '"';
		}, $toc);

		if (!$toc) {
			return array();
		}

		$links = $this->getLinksFromToc(Tx_Restdoc_Utility_Helper::xmlstr_to_array($toc));

		if (isset($jsonData['prev'])) {
			$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($params['documentRoot'] . $params['document'], '../' . $jsonData['prev']['link']);
			$document = substr($absolute, strlen($params['documentRoot']));
			if (!isset($links[$document])) {
				$links[$document] = $this->pObj->getLink($document, TRUE);
			}
		}

		if (isset($jsonData['next'])) {
			$document = $params['document'] === $this->pObj->getDefaultFile() . '/' ? $params['documentRoot'] : $params['documentRoot'] . $params['document'];
			$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($document, '../' . $jsonData['next']['link']);
			$document = substr($absolute, strlen($params['documentRoot']));
			if (!isset($links[$document])) {
				$links[$document] = $this->pObj->getLink($document, TRUE);
			}
		}

		return $links;
	}

	/**
	 * Returns a flat-list of menu entries along with the corresponding document name.
	 *
	 * @param array $entries
	 * @return array
	 * @see tx_restdoc_utility::getMenuData()
	 */
	public function getLinksFromToc(array $entries) {
		$menu = array();
		$entries = isset($entries['li'][0]) ? $entries['li'] : array($entries['li']);
		foreach ($entries as $entry) {
			$document = $entry['a']['@attributes']['data-document'];
			if (strpos($document, '#') !== FALSE) {
				$document = substr($document, 0, strpos($document, '#'));
			}
			$href = $entry['a']['@attributes']['href'];
			if (strpos($href, '#') !== FALSE) {
				$href = substr($href, 0, strpos($href, '#'));
			}
			$menu[$document] = $href;
			if (isset($entry['ul'])) {
				$submenu = $this->getLinksFromToc($entry['ul']);
				$menu = array_merge($menu, $submenu);
			}
		}

		return $menu;
	}

	/**
	 * Replaces links in a reST document using ABSOLUTE URL.
	 *
	 * @param string $root
	 * @param string $document
	 * @param string $content
	 * @return string
	 * @see tx_restdoc_pi1::replaceLinks()
	 */
	protected function replaceLinks($root, $document, $content) {
		$plugin = $this->pObj;
		$ret = preg_replace_callback('#(<a .*? href=")([^"]+)#', function($matches) use ($plugin, $root, $document) {
			/** @var $plugin tx_restdoc_pi1 */
			$anchor = '';
			if (preg_match('#^[a-zA-Z]+://#', $matches[2])) {
				// External URL
				return $matches[0];
			} elseif ($matches[2]{0} === '#') {
				$anchor = $matches[2];
			}

			if ($anchor !== '') {
				$document .= $anchor;
			} else {
				// $document's last part is a document, not a directory
				$document = substr($document, 0, strrpos(rtrim($document, '/'), '/'));
				$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($root . $document, $matches[2]);
				$document = substr($absolute, strlen($root));
			}
			$url = $plugin->getLink($document, TRUE);
			$url = str_replace('&amp;', '&', $url);
			$url = str_replace('&', '&amp;', $url);
			return $matches[1] . $url . '" data-document="' . $document;
		}, $content);
		return $ret;
	}

	/**
	 * Flushes cache of a given plugin if root changed.
	 *
	 * @return void
	 */
	protected function flushCache() {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_restdoc_toc',
			'pid=' . $this->pageId .
				' AND (root<>' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->root, 'tx_restdoc_toc') .
				' OR tstamp<=' . ($GLOBALS['ACCESS_TIME'] - self::CACHE_MAX_AGE) . ')'
		);
	}

}

?>