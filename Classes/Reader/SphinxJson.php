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
 * Sphinx JSON reader.
 *
 * @category    Reader
 * @package     TYPO3
 * @subpackage  tx_restdoc
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   Causal Sàrl
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class Tx_Restdoc_Reader_SphinxJson {

	/** @var string */
	protected $path = NULL;

	/** @var string */
	protected $document = NULL;

	/** @var string */
	protected $jsonFilename = NULL;

	/** @var array */
	protected $data = array();

	/**
	 * Sets the root path to the documentation.
	 *
	 * @param string $path
	 * @return $this
	 */
	public function setPath($path) {
		$this->path = rtrim($path, '/') . '/';
		return $this;
	}

	/**
	 * Returns the root path to the documentation.
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Sets the current document.
	 * Format is expected to be URI segments such as Path/To/Chapter/
	 *
	 * @param string $document
	 * @return $this
	 */
	public function setDocument($document) {
		$this->document = $document;
		return $this;
	}

	/**
	 * Returns the current document.
	 *
	 * @return string
	 */
	public function getDocument() {
		return $this->document;
	}

	/**
	 * Returns the JSON file name relative to $this->path.
	 *
	 * @return string
	 */
	public function getJsonFilename() {
		return $this->jsonFilename;
	}

	/**
	 * @return array
	 * @deprecated Data should not be needed from outside
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Loads the current document.
	 *
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws RuntimeException
	 */
	public function load() {
		if (empty($this->path) || !is_dir($this->path)) {
			throw new RuntimeException('Invalid path: ' . $this->path, 1365165151);
		}
		if (empty($this->document) || substr($this->document, -1) !== '/') {
			throw new RuntimeException('Invalid document: ' . $this->document, 1365165369);
		}

		$this->jsonFilename = substr($this->document, 0, strlen($this->document) - 1) . '.fjson';
		$filename = $this->path . $this->jsonFilename;

		// Security check
		$fileExists = is_file($filename);
		if ($fileExists && substr(realpath($filename), 0, strlen(realpath($this->path))) !== realpath($this->path)) {
			$fileExists = FALSE;
		}
		if (!$fileExists) {
			throw new RuntimeException('File not found: ' . $this->jsonFilename, 1365165515);
		}

		$content = file_get_contents($filename);
		$this->data = json_decode($content, TRUE);

		return $this->data !== NULL;
	}

}

?>