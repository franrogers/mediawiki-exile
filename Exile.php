<?php
if (!defined('MEDIAWIKI')) {
	exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Exile',
	'author' => 'Fran McCrory',
	'url' => 'http://www.mediawiki.org/wiki/Extension:Exile',
	'description' => 'Allows sysops to exile users to Siberia, for varying definitions of "Siberia."',
	'descriptionmsg' => 'exile-desc',
	'version' => '1.2',
);
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['Exile'] = $dir . 'Exile_body.php';
$wgExtensionMessagesFiles['Exile'] = $dir . 'Exile.i18n.php';
$wgExtensionAliasesFiles['Exile'] = $dir . 'Exile.alias.php';
$wgSpecialPages['Exile'] = 'Exile';
$wgSpecialPageGroups ['Exile'] = 'users';
$wgHooks['BeforePageDisplay'][] = 'fnExileBeforePageDisplayHook';

function fnExileBeforePageDisplayHook(&$out, &$sk) {
	global $wgUser;
	if ($wgUser->isBlocked() && wfIsBlockExiled($wgUser->getBlockID())) {
		header('Location: ' . wfGetBlockExiledTo($wgUser->getBlockID()));
		exit;
	}
	return true;
}

function wfExileBlock($blockID, $url) {
	$dbr = wfGetDB(DB_MASTER);
	$ts = Block::newFromID($blockID)->mTimestamp;
	if (!wfIsBlockExiled($blockID)) {
		$dbr->insert('page_props',
					 array('pp_page'	 => 0,
						   'pp_propname' => "exile-$blockID-$ts",
						   'pp_value'	 => $url),
					 'wfExileBlock');
	} else {
		$dbr->update('page_props',
					 array('pp_value' => $url),
					 array('pp_page'     => 0,
						   'pp_propname' => "exile-$blockID-$ts"),
					 'wfExileBlock');
	}
	return (bool)$dbr->affectedRows();
}

function wfUnExileBlock($blockID) {
	$dbr = wfGetDB(DB_MASTER);
	$ts = Block::newFromID($blockID)->mTimestamp;
	$dbr->delete('page_props',
					 array('pp_page'     => 0,
						   'pp_propname' => "exile-$blockID-$ts"),
					 'wfExileBlock');
	return (bool)$dbr->affectedRows();
}

function wfIsBlockExiled($blockID) {
	$url = wfGetBlockExiledTo($blockID);
	return (bool)($url != null);
}

function wfGetBlockExiledTo($blockID) {
	$dbr = wfGetDB(DB_SLAVE);
	$ts = Block::newFromID($blockID)->mTimestamp;
	$url = $dbr->selectField('page_props', 'pp_value', "pp_page = 0 AND pp_propname = \"exile-$blockID-$ts\"", 'wfGetBlockExiledTo');

	return $url ? $url : $null;
}
