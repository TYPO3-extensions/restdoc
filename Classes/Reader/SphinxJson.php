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

	/** @var \TYPO3\CMS\Core\Resource\ResourceStorage */
	protected $storage = NULL;

	/** @var string */
	protected $path = NULL;

	/** @var string */
	protected $document = NULL;

	/** @var string */
	protected $jsonFilename = NULL;

	/** @var boolean */
	protected $keepPermanentLinks = FALSE;

	/** @var boolean */
	protected $fallbackToDefaultFile = FALSE;

	/** @var string */
	protected $defaultFile = 'index';

	/** @var array */
	protected $data = array();

	/**
	 * Sets the storage.
	 *
	 * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
	 * @return $this
	 */
	public function setStorage(/* \TYPO3\CMS\Core\Resource\ResourceStorage */ $storage) {
		$this->storage = $storage;
		return $this;
	}

	/**
	 * Returns the storage.
	 *
	 * @return \TYPO3\CMS\Core\Resource\ResourceStorage
	 */
	public function getStorage() {
		return $this->storage;
	}

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
	 * Sets whether permanent links to sections in BODY should be kept.
	 *
	 * @param boolean $active
	 * @return $this
	 */
	public function setKeepPermanentLinks($active) {
		$this->keepPermanentLinks = $active;
		return $this;
	}

	/**
	 * Returns whether permanent links to sections in BODY should be kept.
	 *
	 * @return boolean
	 */
	public function getKeepPermanentLinks() {
		return $this->keepPermanentLinks;
	}

	/**
	 * Silently falls back to default document instead of throwing an
	 * error if an invalid document is specified.
	 *
	 * @return $this
	 */
	public function enableDefaultDocumentFallback() {
		$this->fallbackToDefaultFile = TRUE;
		return $this;
	}

	/**
	 * Sets the default file (e.g., 'index').
	 *
	 * @param string $defaultFile
	 * @return $this
	 */
	public function setDefaultFile($defaultFile) {
		$this->defaultFile = $defaultFile;
		return $this;
	}

	/**
	 * Returns the default file.
	 *
	 * @return string
	 */
	public function getDefaultFile() {
		return $this->defaultFile;
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
		if (!$fileExists && $this->fallbackToDefaultFile) {
			$defaultDocument = $this->getDefaultFile() . '/';
			$defaultJsonFilename = substr($defaultDocument, 0, strlen($defaultDocument) - 1) . '.fjson';
			$defaultFilename = $this->path . $defaultJsonFilename;
			if (is_file($defaultFilename)) {
				$this->document = $defaultDocument;
				$this->jsonFilename = $defaultJsonFilename;
				$filename = $defaultFilename;
				$fileExists = TRUE;
			}
		}
		if (!$fileExists) {
			throw new RuntimeException('File not found: ' . $this->jsonFilename, 1365165515);
		}

		$content = file_get_contents($filename);
		$this->data = json_decode($content, TRUE);

		return $this->data !== NULL;
	}

	/**
	 * Enforces that current document is loaded.
	 *
	 * @throws RuntimeException
	 */
	protected function enforceIsLoaded() {
		if (empty($this->data)) {
			throw new RuntimeException('Document is not loaded: ' . $this->document, 1365170112);
		}
	}

	/**
	 * Returns the BODY of the documentation.
	 *
	 * @param callback $callbackLinks Callback to generate Links in current context
	 * @param callback $callbackImages function to process images in current context
	 * @return string
	 * @throws RuntimeException
	 */
	public function getBody($callbackLinks, $callbackImages) {
		$this->enforceIsLoaded();
		$callableName = '';
		if (!is_callable($callbackLinks, FALSE, $callableName)) {
			throw new RuntimeException('Invalid callback for links: ' . $callableName, 1365172111);
		}
		if (!is_callable($callbackImages, FALSE, $callableName)) {
			throw new RuntimeException('Invalid callback for images: ' . $callableName, 1365630854);
		}

		$body = $this->data['body'];
		if (!$this->keepPermanentLinks) {
			// Remove permanent links in body
			$body = preg_replace('#<a class="headerlink" [^>]+>[^<]+</a>#', '', $body);
		}

		// Replace links in body
		$body = $this->replaceLinks($body, $callbackLinks);

		// Replace images in body
		$body = $this->replaceImages($body, $callbackImages);

		return $body;
	}

	/**
	 * Returns the Table Of Contents (TOC) of the documentation.
	 *
	 * @param callback $callbackLinks Callback to generate Links in current context
	 * @return string
	 * @throws RuntimeException
	 */
	public function getTableOfContents($callbackLinks) {
		$this->enforceIsLoaded();
		$callableName = '';
		if (!is_callable($callbackLinks, FALSE, $callableName)) {
			throw new RuntimeException('Invalid callback for links: ' . $callableName, 1365172117);
		}

		// Replace links in table of contents
		$toc = $this->replaceLinks($this->data['toc'], $callbackLinks);
		// Remove empty sublevels
		$toc = preg_replace('#<ul>\s*</ul>#', '', $toc);
		// Fix TOC to make it XML compliant
		$toc = preg_replace_callback('# href="([^"]+)"#', function($matches) {
			$url = str_replace('&amp;', '&', $matches[1]);
			$url = str_replace('&', '&amp;', $url);
			return ' href="' . $url . '"';
		}, $toc);

		return $toc;
	}

	/**
	 * Replaces links in a ReStructuredText document.
	 *
	 * @param string $content
	 * @param callback $callbackLinks function to generate Links in current context
	 * @return string
	 */
	protected function replaceLinks($content, $callbackLinks) {
		$self = $this;
		$ret = preg_replace_callback('#(<a .*? href=")([^"]+)#', function($matches) use ($self, $callbackLinks) {
			$document = $self->getDocument();
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
				$defaultDocument = $self->getDefaultFile() . '/';
				if ($document === $defaultDocument || t3lib_div::isFirstPartOfStr($matches[2], '../')) {
					// $document's last part is a document, not a directory
					$document = substr($document, 0, strrpos(rtrim($document, '/'), '/'));
				}
				$absolute = Tx_Restdoc_Utility_Helper::relativeToAbsolute($self->getPath() . $document, $matches[2]);
				$document = substr($absolute, strlen($self->getPath()));
			}
			$url = call_user_func($callbackLinks, $document);
			$url = str_replace('&amp;', '&', $url);
			$url = str_replace('&', '&amp;', $url);
			return $matches[1] . $url;
		}, $content);
		return $ret;
	}

	/**
	 * Replaces images in a reST document.
	 *
	 * @param string $content
	 * @param callback $callbackImages function to process images in current context
	 * @return string
	 * @link http://w-shadow.com/blog/2009/10/20/how-to-extract-html-tags-and-their-attributes-with-php/
	 */
	protected function replaceImages($content, $callbackImages) {
		$self = $this;
		$root = $this->path . $this->document;
		// $root's last part is a document, not a directory
		$root = substr($root, 0, strrpos(rtrim($root, '/'), '/'));
		$tagPattern =
			'@<img                      # <img
			(?P<attributes>\s[^>]+)?    # attributes, if any
			\s*/>                       # />
			@xsi';
		$attributePattern =
			'@
				(?P<name>\w+)                                           # attribute name
				\s*=\s*
				(
					(?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    # a quoted value
					|                                                   # or
					(?P<value_unquoted>[^\s"\']+?)(?:\s+|$)             # an unquoted value (terminated by whitespace or EOF)
				)
			@xsi';

		$ret = preg_replace_callback($tagPattern, function($matches) use ($self, $root, $attributePattern, $callbackImages) {
			// Parse tag attributes, if any
			$attributes = array();
			if (!empty($matches['attributes'][0])) {
				if (preg_match_all($attributePattern, $matches['attributes'], $attributeData, PREG_SET_ORDER)) {
					// Turn the attribute data into a name->value array
					foreach ($attributeData as $attr) {
						if (!empty($attr['value_quoted'])) {
							$value = $attr['value_quoted'];
						} elseif (!empty($attr['value_unquoted'])) {
							$value = $attr['value_unquoted'];
						} else {
							$value = '';
						}

						$value = html_entity_decode($value, ENT_QUOTES, 'utf-8');
						$attributes[$attr['name']] = $value;
					}
				}
			}
			$src = Tx_Restdoc_Utility_Helper::relativeToAbsolute($root, $attributes['src']);
			if (version_compare(TYPO3_version, '6.0.0', '>=')) {
				$storageConfiguration = $self->getStorage()->getConfiguration();
				$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
				$fileIdentifier = substr($src, strlen($basePath) - 1);
				$attributes['src'] = $self->getStorage()->getUid() . ':' . $fileIdentifier;
			} else {
				$attributes['src'] = substr($src, strlen(PATH_site));
			}
			return call_user_func($callbackImages, $attributes);
		}, $content);

		return $ret;
	}

}

?>