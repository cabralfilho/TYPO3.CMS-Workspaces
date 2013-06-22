<?php
namespace TYPO3\CMS\Backend\Utility;

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

class RecordUtility implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array
	 */
	protected $versionsPerPage = array();

	/**
	 * @var array
	 */
	protected $versionsTables = array();

	/**
	 * @return \TYPO3\CMS\Backend\Utility\RecordUtility
	 */
	static public function getInstance() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Backend\\Utility\\RecordUtility'
		);
	}

	/**
	 * @param integer $pageId
	 * @param integer $workspaceId
	 * @return bool
	 */
	public function hasVersions($pageId, $workspaceId = NULL) {
		if ($workspaceId === NULL) {
			$workspaceId = $this->getBackendUser()->workspace;
		}

		$pageId = (int) $pageId;
		$workspaceId = (int) $workspaceId;

		if ($workspaceId === 0) {
			return FALSE;
		}

		if (isset($this->versionsPerPage[$pageId][$workspaceId])) {
			return $this->versionsPerPage[$pageId][$workspaceId];
		}

		foreach ($GLOBALS['TCA'] as $tableName => $tableConfiguration) {
			if (empty($tableConfiguration['ctrl']['versioningWS']) || in_array($tableName, $this->versionsTables) || $tableName === 'pages') {
				continue;
			}

			$this->versionsTables[] = $tableName;

			$records = $this->getDatabase()->exec_SELECTgetRows(
				'B.uid as live_uid, B.pid as live_pid, A.uid as offline_uid',
				$tableName . ' A,' . $tableName . ' B',
				'A.pid=-1' . ' AND A.t3ver_wsid=' . $workspaceId . ' AND A.t3ver_oid=B.uid' .
					BackendUtility::deleteClause($tableName, 'A') . BackendUtility::deleteClause($tableName, 'B'),
				'live_pid'
			);

			if (is_array($records)) {
				foreach ($records as $record) {
					$recordPageId = $record['live_pid'];
					$this->versionsPerPage[$recordPageId][$workspaceId] = TRUE;
				}
			}

			if (!empty($this->versionsPerPage[$pageId][$workspaceId])) {
				return TRUE;
			}
		}

		$this->versionsPerPage[$pageId][$workspaceId] = FALSE;
		return FALSE;
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
