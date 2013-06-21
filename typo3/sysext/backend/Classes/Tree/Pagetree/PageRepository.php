<?php
namespace TYPO3\CMS\Backend\Tree\Pagetree;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Oliver Hader <oliver.hader@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Repository for page records
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class PageRepository {

	const PAGES_Fields = 'uid,pid,tstamp,sorting,deleted,hidden,doktype,title,nav_title,t3ver_oid,t3ver_id,t3ver_wsid,t3ver_label,t3ver_state,t3ver_stage,t3ver_count,t3ver_tstamp,t3ver_move_id,t3_origuid,perms_userid,perms_groupid,perms_user,perms_group,perms_everybody,is_siteroot,storage_pid,backend_layout_next_level,TSconfig';
	const CONSTRAINTS_And = 'AND';
	const CONSTRAINTS_Or = 'OR';
	const OPERATOR_Contains = 'contains';
	const OPERATOR_Constraints = 'constraints';

	/**
	 * @var array
	 */
	protected $pages;

	/**
	 * @var boolean
	 */
	protected $usePagePermissions;

	/**
	 * @var string
	 */
	protected $fields = '';

	public function __construct() {
		$this->setFields(self::PAGES_Fields);
	}

	/**
	 * @param boolean $usePagePermissions
	 */
	public function setUsePagePermissions($usePagePermissions) {
		$this->usePagePermissions = (bool) $usePagePermissions;
	}

	/**
	 * @return boolean
	 */
	public function getUsePagePermissions() {
		return $this->usePagePermissions;
	}

	/**
	 * @return string
	 */
	public function getFields() {
		return $this->fields;
	}

	/**
	 * @param string $fields
	 */
	public function setFields($fields) {
		$this->fields = (string) $fields;
	}

	/**
	 * @return array
	 */
	public function findAll() {
		if (!isset($this->pages)) {
			if ($this->getUsePagePermissions()) {
				$pagePermissions = $this->getBackendUser()->getPagePermsClause(1);
			} else {
				$pagePermissions = '1=1';
			}

			$this->pages = $this->getDatabase()->exec_SELECTgetRows(
				$this->getFields(),
				'pages',
				$pagePermissions . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('pages'),
				'',
				'sorting',
				'',
				'uid'
			);

			if (!is_array($this->pages)) {
				$this->pages = array();
			}
		}

		return $this->pages;
	}

	/**
	 * @param integer $pageId
	 * @return array|NULL
	 */
	public function findById($pageId) {
		$page = NULL;
		$this->findAll();

		if (!empty($this->pages[$pageId])) {
			$page = $this->pages[$pageId];
		}

		return $page;
	}

	/**
	 * @param array $constraints
	 * @return array
	 */
	public function findByConstraints(array $constraints) {
		$pages = array();
		$this->findAll();

		foreach ($this->pages as $page) {
			if ($this->isValid($page, $constraints)) {
				$uid = $page['uid'];
				$pages[$uid] = $page;
			}
		}

		return $pages;
	}

	/**
	 * @param integer $pageId
	 * @param boolean $unsetMovePointers
	 * @param integer $workspaceId
	 * @return array|boolean|NULL
	 */
	public function getWorkspaceOverlay($pageId, $unsetMovePointers = FALSE, $workspaceId = -99) {
		$page = $this->findById($pageId);

		if ($workspaceId == -99) {
			$workspaceId = $this->getBackendUser()->workspace;
		}

		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('version') || $workspaceId === 0 || !is_array($page)) {
			return $page;
		}

		$orig_uid = $page['uid'];
		$orig_pid = $page['pid'];
		$movePldSwap = $this->getMovePlaceholderOverlay($page);

		$wsAlt = $this->getWorkspaceVersion($page['uid'], $workspaceId);

		// If version was found, swap the default record with that one.
		if (is_array($wsAlt)) {
			// Check if this is in move-state:
			if (!$movePldSwap && $unsetMovePointers) {
				// Only for WS ver 2... (moving)
				// If t3ver_state is not found, then find it... (but we like best if it is here...)
				$state = $wsAlt['t3ver_state'];
				if ((int) $state === 4) {
					// TODO: Same problem as frontend in versionOL(). See TODO point there.
					return FALSE;
				}
			}
			// Always correct PID from -1 to what it should be
			if (isset($wsAlt['pid'])) {
				// Keep the old (-1) - indicates it was a version.
				$wsAlt['_ORIG_pid'] = $wsAlt['pid'];
				// Set in the online versions PID.
				$wsAlt['pid'] = $page['pid'];
			}
			// For versions of single elements or page+content, swap UID and PID
			$wsAlt['_ORIG_uid'] = $wsAlt['uid'];
			$wsAlt['uid'] = $page['uid'];
			// Backend css class:
			$wsAlt['_CSSCLASS'] = 'ver-element';
			// Changing input record to the workspace version alternative:
			$page = $wsAlt;
		}

		// If the original record was a move placeholder, the uid and pid of that is preserved here:
		if ($movePldSwap) {
			$page['_MOVE_PLH'] = TRUE;
			$page['_MOVE_PLH_uid'] = $orig_uid;
			$page['_MOVE_PLH_pid'] = $orig_pid;
			// For display; To make the icon right for the placeholder vs. the original
			$page['t3ver_state'] = 3;
		}

		return $page;
	}

	/**
	 * Select the workspace version of a page, if exists
	 *
	 * @param integer $pageId Page ID for which to find workspace version.
	 * @param integer $workspaceId Workspace ID
	 * @return array If found, return record, otherwise FALSE
	 */
	public function getWorkspaceVersion($pageId, $workspaceId) {
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('version') || $workspaceId === 0) {
			return FALSE;
		}

		$constraints = array(
			array('pid', '==', '-1'),
			array('t3ver_oid', '==', (int) $pageId),
			array('t3ver_wsid', '==', (int) $workspaceId),
		);

		$pages = $this->findByConstraints($constraints);

		if (count($pages)) {
			reset($pages);
			return current($pages);
		}

		return FALSE;
	}

	/**
	 * Checks if record is a move-placeholder (t3ver_state==3) and if so it will set $row to be the pointed-to live record (and return TRUE)
	 *
	 * @param array $page Row (passed by reference) - must be online record!
	 * @return boolean TRUE if overlay is made.
	 * @see PageRepository::movePlhOl()
	 */
	public function getMovePlaceholderOverlay(array &$page) {
		$moveID = $page['t3ver_move_id'];
		$state = $page['t3ver_state'];

		// Find pointed-to record.
		if ((int) $state === 3 && $moveID) {
			if ($originalPage = $this->findById($moveID)) {
				$page = $originalPage;
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Find page-tree PID for versionized record
	 * Will look if the "pid" value of the input record is -1 and if the table supports versioning - if so, it will translate the -1 PID into the PID of the original record
	 * Used whenever you are tracking something back, like making the root line.
	 * Will only translate if the workspace of the input record matches that of the current user (unless flag set)
	 * Principle; Record offline! => Find online?
	 *
	 * @param array $page Record array passed by reference. As minimum, "pid" and "uid" fields must exist! "t3ver_oid" and "t3ver_wsid" is nice and will save you a DB query.
	 * @param boolean $ignoreWorkspaceMatch Ignore workspace match
	 * @return void (Passed by ref). If the record had its pid corrected to the online versions pid, then "_ORIG_pid" is set to the original pid value (-1 of course). The field "_ORIG_pid" is used by various other functions to detect if a record was in fact in a versionized branch.
	 * @see PageRepository::fixVersioningPid()
	 */
	public function fixVersioningPid(&$page, $ignoreWorkspaceMatch = FALSE) {
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('version')) {
			return;
		}
		// Check that the input record is an offline version from a table that supports versioning:
		if (is_array($page) && $page['pid'] == -1) {
			// If "t3ver_oid" is already a field, just set this:
			$oid = $page['t3ver_oid'];
			$wsid = $page['t3ver_wsid'];
			// If ID of current online version is found, look up the PID value of that:
			if ($oid && ($ignoreWorkspaceMatch || !strcmp((int) $wsid, $this->getBackendUser()->workspace))) {
				$oidRec = $this->findById($oid);
				if (is_array($oidRec)) {
					$page['_ORIG_pid'] = $page['pid'];
					$page['pid'] = $oidRec['pid'];
				}
			}
		}
	}

	/**
	 * Returns what is called the 'RootLine'. That is an array with information about the page records from a page id ($uid) and back to the root.
	 * By default deleted pages are filtered.
	 * This RootLine will follow the tree all the way to the root. This is opposite to another kind of root line known from the frontend where the rootline stops when a root-template is found.
	 *
	 * @param integer $uid Page id for which to create the root line.
	 * @param boolean $workspaceOL If TRUE, version overlay is applied. This must be requested specifically because it is usually only wanted when the rootline is used for visual output while for permission checking you want the raw thing!
	 * @return array Root line array, all the way to the page tree root (or as far as $clause allows!)
	 */
	public function getRootLine($uid, $workspaceOL = FALSE) {
		$output = array();

		$loopCheck = 100;
		$theRowArray = array();

		while ($uid != 0 && $loopCheck) {
			$loopCheck--;
			$row = ($workspaceOL ? $this->getWorkspaceOverlay($uid) : $this->findById($uid));
			if (is_array($row)) {
				$this->fixVersioningPid($row);
				$uid = $row['pid'];
				$theRowArray[] = $row;
			} else {
				break;
			}
		}
		if ($uid == 0) {
			$theRowArray[] = array('uid' => 0, 'title' => '');
		}
		$c = count($theRowArray);
		foreach ($theRowArray as $val) {
			$c--;
			$output[$c] = array(
				'uid' => $val['uid'],
				'pid' => $val['pid'],
				'title' => $val['title'],
				'TSconfig' => $val['TSconfig'],
				'is_siteroot' => $val['is_siteroot'],
				'storage_pid' => $val['storage_pid'],
				't3ver_oid' => $val['t3ver_oid'],
				't3ver_wsid' => $val['t3ver_wsid'],
				't3ver_state' => $val['t3ver_state'],
				't3ver_stage' => $val['t3ver_stage'],
				'backend_layout_next_level' => $val['backend_layout_next_level']
			);
			if (isset($val['_ORIG_pid'])) {
				$output[$c]['_ORIG_pid'] = $val['_ORIG_pid'];
			}
		}

		return $output;
	}

	/**
	 * @param array $page
	 * @param array $constraints
	 * @param string $type
	 * @return boolean
	 * @throws \RuntimeException
	 */
	protected function isValid(array $page, array $constraints, $type = self::CONSTRAINTS_And) {
		$isValid = TRUE;

		foreach ($constraints as $constraint) {
			list($left, $operator, $right) = $constraint;

			switch ($operator) {
				case '==':
					$isValid = ($page[$left] == $right);
					break;
				case '!=':
					$isValid = ($page[$left] != $right);
					break;
				case '<=';
					$isValid = ($page[$left] <= $right);
					break;
				case '>=';
					$isValid = ($page[$left] >= $right);
					break;
				case '<':
					$isValid = ($page[$left] < $right);
					break;
				case '>':
					$isValid = ($page[$left] > $right);
					break;
				case self::OPERATOR_Contains:
					$isValid = (stripos($page[$left], $right) !== FALSE);
					break;
				case self::OPERATOR_Constraints:
					$isValid = $this->isValid($page, $left, $right);
					break;
				default:
					throw new \RuntimeException(
						'Invalid operator "' . $operator . '"',
						1371833747
					);
			}

			if ($type === self::CONSTRAINTS_And && $isValid === FALSE || $type === self::CONSTRAINTS_Or && $isValid === TRUE) {
				break;
			}
		}

		return $isValid;
	}

	/**
	 * @return string
	 */
	protected function getDeleteField() {
		return $GLOBALS['TCA']['pages']['ctrl']['delete'];
	}

	/**
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return \TYPO3\CMS\Dbal\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}

}


?>
