<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1'] = 'layout,select_key,pages';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(array(
	'LLL:EXT:restdoc/Resources/Private/Language/locallang_db.xml:tt_content.list_type_pi1',
	$_EXTKEY . '_pi1',
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'ext_icon.gif'
), 'list_type');

// Register the FlexForms
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY . '_pi1'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($_EXTKEY . '_pi1', 'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/flexform_pi1.xml');

// Initialize static extension templates
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($GLOBALS['_EXTKEY'], 'Configuration/TypoScript/', 'Sphinx/reST Documentation Viewer');

if (TYPO3_MODE === 'BE') {
	$TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['tx_' . $_EXTKEY . '_pi1_wizicon'] = t3lib_extMgm::extPath($_EXTKEY) . 'Classes/Controller/Pi1/class.tx_restdoc_pi1_wizicon.php';
}
