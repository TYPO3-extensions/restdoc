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

	// Initialize module
$GLOBALS['LANG']->includeLLFile('EXT:restdoc/Resources/Private/Language/locallang_mod_manual.xml');
$GLOBALS['BE_USER']->modAccess($MCONF, 1);		// This checks permissions and exits if the users has no permission for entry.

/**
 * Module 'Manuals' for the 'restdoc' extension.
 *
 * @category    Backend Module
 * @package     restdoc
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   2013 Causal Sàrl
 * @license     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class Tx_Restdoc_Modules_Manuals extends t3lib_SCbase {

	/**
	 * Initialisation of this backend module
	 *
	 * @return	void
	 * @access public
	 */
	public function init() {
		parent::init();
	}

	/**
	 * Main function of the module.
	 *
	 * @return void
	 */
	public function main() {
		//$this->content = $this->getSampleTransformation();

		$documentationPath = t3lib_extMgm::extPath('your-ext') . 'Documentation/build/json/';

		/** @var Tx_Restdoc_Reader_SphinxJson $sphinxReader */
		$sphinxReader = t3lib_div::makeInstance('Tx_Restdoc_Reader_SphinxJson');
		$sphinxReader->setPath($documentationPath);

		$document = t3lib_div::_GET('doc');
		if (empty($document)) {
			$document = 'index/';
		}

		$sphinxReader->setDocument($document);
		$sphinxReader->load();
		$this->content = $sphinxReader->getBody(
			array($this, 'getLink'),
			array($this, 'processImage')
		);
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
		$anchor = '';
		if (($pos = strrpos($document, '#')) !== FALSE) {
			$anchor = substr($document, $pos + 1);
			$document = substr($document, 0, $pos);
		}
		$link = '/typo3/mod.php?M=help_restdocManuals&amp;doc=' . urlencode($document);
		if ($anchor) {
			$link .= '#' . $anchor;
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
		return '<img src="../' . $data['src'] . '" alt="' . htmlspecialchars($data['alt']) . '" class="' . $data['class'] . '" />';
	}

	/**
	 * Returns a sample ReStructuredText transformation in HTML.
	 *
	 * @return string
	 */
	protected function getSampleTransformation() {
		/** @var $restParser Tx_Restdoc_Utility_RestParser */
		$restParser = t3lib_div::makeInstance('Tx_Restdoc_Utility_RestParser');

		$content = $restParser->transform(<<<REST
=============
Title level 1
=============

Hello, this is a simple text...

in two parts.

Title level 2
=============

Title level 3
-------------

Text with attributes: *italic* or **bold**.

- one
- two
- three

preformatted code::

	#include <stdio.h>
	int main(void)
	{
		[..]
		return 1;
	}

Show an image:

.. image:: ../typo3conf/ext/restdoc/ext_icon.gif

REST
		);

		return $content;
	}

	/**
	 * Echoes the HTML output of this module.
	 *
	 * @return	void
	 * @access public
	 */
	public function printContent() {
		echo $this->content;
	}

}

/** @var $SOBE Tx_Restdoc_Modules_Manuals */
$SOBE = t3lib_div::makeInstance('Tx_Restdoc_Modules_Manuals');
$SOBE->init();

	// Include files?
foreach($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

?>