<?php
if (!defined('MEDIAWIKI')) {
	exit(1);
}

class Exile extends SpecialPage {
	function __construct() {
		parent::__construct('Exile');
		wfLoadExtensionMessages('Exile');
	}
 
	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;
		$this->setHeaders();
		
		if (!$wgUser->isAllowed('block')) { #TODO
            $this->displayRestrictionError();
			return;
		}
		
		if ($par) {
			$exileUser = Title::newFromDBKey($par)->getFullText();
		} else {
			$exileUser = $wgRequest->getText('wpExileUser');
		}
		$exileURL = $wgRequest->getText('wpExileURL');
		
		$token = htmlspecialchars($wgUser->editToken());
		$action = Title::makeTitle(NS_SPECIAL, 'Exile')->escapeLocalURL("action=submit");
		
		if ($wgRequest->getBool('wpExileSubmit') &&
				$wgRequest->wasPosted() &&
				$wgUser->matchEditToken($wgRequest->getVal('wpEditToken'))) {
			$address = User::isIP($exileUser) ? $exileUser : '';
			$userObj = User::newFromName($exileUser);
			$user = $userObj ? $userObj->getId() : 0;
			if ($user) {
				$address = $exileUser;
			}
			
			if ($exileUser != '' && ($address || $user) && (!$userObj || !$userObj->isAllowed('block'))) {
				if ($exileURL != '') {
					$block = Block::newFromDB($address, $user);
					$newBlock = false;
					
					if (!$block) {
						$block = new Block($address, $user, $wgUser->getId(), 
										   wfMsg('exile-blockreason', $exileURL),
										   wfTimestampNow(), 0, 'infinity', 0, 1, 1, 0, 1, 0);
						$block->insert();
						$block = Block::newFromDB($address, $user);
						$newBlock = true;
					}
					if (!preg_match('$^(\\w+:|/)$', $exileURL)) {
						$exileURL = "http://{$exileURL}";
					}
		
					if ($block->getId() && wfExileBlock($block->getId(), $exileURL)) {
						$log = new LogPage('block');
						$log->addEntry(($newBlock ? 'block' : 'reblock'), Title::makeTitle(NS_USER, $address),
									   wfMsg('exile-blockreason', $exileURL),
									   array($block->mExpiry));

						$wgOut->addHTML(wfMsg('exile-done', htmlspecialchars($exileUser), htmlspecialchars($exileURL)));
						return;
					} else {
						Exile::outputError(wfMsg('exile-error'));
					}
				} else {
					Exile::outputError(wfMsg('exile-badurl'));
				}
			} else {
				if ($userObj && $userObj->isAllowed('block')) {
					Exile::outputError(wfMsg('exile-errorsysop', $address));
				} else {
					Exile::outputError(wfMsg('exile-baduser'));
				}
			}
		} else if ($wgRequest->getBool('wpUnExileSubmit') &&
				   $wgRequest->wasPosted() &&
				   $wgUser->matchEditToken($wgRequest->getVal('wpEditToken'))) {
			$address = User::isIP($exileUser) ? $exileUser : '';
			$userObj = User::newFromName($exileUser);
			$user = $userObj ? $userObj->getId() : 0;
			if ($user) {
				$address = $exileUser;
			}
			
			if ($exileUser != '' && ($address || $user)) {
				$block = Block::newFromDB($address, $user);
				
				if ($block && wfUnExileBlock($block->getId())) {
					$log = new LogPage('block');
					$log->addEntry('reblock', Title::makeTitle(NS_USER, $address),
										   wfMsg('exile-blockrundo', $exileURL),
										   array($block->mExpiry));
					
					$wgOut->addHTML(wfMsg('exile-doneundo', htmlspecialchars($exileUser), htmlspecialchars($exileURL)));
					return;
				} else {
					Exile::outputError(wfMsg('exile-errorundo'));
				}
			} else {
				Exile::outputError(wfMsg('exile-baduser'));
			}
		}
		
		if (!$exileURL) {
			$exileURL = wfMsg('exile-defaulturl');
		}
		
		$wgOut->addHTML("<p>" . wfMsg('exile-text') . "</p>
		<form id=\"exile\" method=\"post\" action=\"{$action}\">
		<table border='0'>
		<tr>
			<td align='right'>" . wfMsg('exile-user') . "</td>
			<td align='left'>
				<input type='text' size='40' name=\"wpExileUser\" value=\"" . htmlspecialchars($exileUser) . "\" />
			</td>
		</tr><tr>
			<td align='right'>" . wfMsg('exile-url') . "</td>
			<td align='left'>
				<input type='text' size='40' name=\"wpExileURL\" value=\"" . htmlspecialchars($exileURL) . "\" />
			</td>
		</tr><tr>
			<td>&nbsp;</td><td align='left'>
				<input type='submit' name=\"wpExileSubmit\" value=\"" . wfMsg('exile-submit') . "\" />
				<input type='submit' name=\"wpUnExileSubmit\" value=\"" . wfMsg('exile-submitundo') . "\" />
			</td></tr></table>
			<input type='hidden' name='wpEditToken' value=\"{$token}\" />
		</form>\n");
		return;
	}
	
	function outputError($err) {
		global $wgOut;
		$wgOut->setSubtitle(wfMsg("formerror"));
		$wgOut->addHTML( "<p class='error'>{$err}</p>\n" );
	}
}
