<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "restdoc".
 *
 * Auto generated 04-04-2013 14:38
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'reST Documentation Viewer',
	'description' => 'Integrates a reStructuredText documentation (generated as JSON with Sphinx) into a TYPO3 website.',
	'category' => 'plugin',
	'author' => 'Xavier Perseguers',
	'author_company' => 'Causal Sàrl',
	'author_email' => 'xavier@causal.ch',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'version' => '1.3-dev',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-6.1.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:30:{s:9:"ChangeLog";s:4:"a67a";s:16:"ext_autoload.php";s:4:"873e";s:12:"ext_icon.gif";s:4:"d42b";s:17:"ext_localconf.php";s:4:"80c3";s:14:"ext_tables.php";s:4:"06ff";s:14:"ext_tables.sql";s:4:"11cb";s:51:"Classes/ContentObject/RestMetadataContentObject.php";s:4:"683f";s:47:"Classes/Controller/Pi1/class.tx_restdoc_pi1.php";s:4:"1e6c";s:55:"Classes/Controller/Pi1/class.tx_restdoc_pi1_wizicon.php";s:4:"b618";s:32:"Classes/Hook/TableOfContents.php";s:4:"3041";s:39:"Classes/Hook/TxDdgooglesitemapPages.php";s:4:"fe34";s:34:"Classes/Hook/TxRealurlAutoconf.php";s:4:"951a";s:44:"Classes/Utility/class.tx_restdoc_utility.php";s:4:"f910";s:26:"Classes/Utility/Helper.php";s:4:"8fa3";s:40:"Configuration/FlexForms/flexform_pi1.xml";s:4:"bbc9";s:38:"Configuration/TypoScript/constants.txt";s:4:"fb2c";s:34:"Configuration/TypoScript/setup.txt";s:4:"3f8c";s:43:"Resources/Private/Examples/center-images.ts";s:4:"7392";s:36:"Resources/Private/Examples/footer.ts";s:4:"bb9a";s:40:"Resources/Private/Language/locallang.xlf";s:4:"b082";s:40:"Resources/Private/Language/locallang.xml";s:4:"3eb3";s:43:"Resources/Private/Language/locallang_db.xlf";s:4:"4231";s:43:"Resources/Private/Language/locallang_db.xml";s:4:"fd57";s:40:"Resources/Public/Icons/pi1_ce_wizard.png";s:4:"7240";s:39:"Resources/Public/JavaScript/doctools.js";s:4:"5ff5";s:42:"Resources/Public/JavaScript/searchtools.js";s:4:"494b";s:41:"Resources/Public/JavaScript/underscore.js";s:4:"b538";s:14:"doc/manual.sxw";s:4:"e84e";s:20:"static/constants.txt";s:4:"665b";s:16:"static/setup.txt";s:4:"463c";}',
	'suggests' => array(
	),
);

?>