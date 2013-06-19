<?php
namespace TYPO3\CMS\Workspaces\Record\Repository;

/***************************************************************
 * Copyright notice
 *
 * (c) 2013 Oliver Hader <oliver.hader@typo3.org>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Repository for workspace records
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class WorkspaceRepository implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array
	 */
	protected $workspaces;

	/**
	 * @var array
	 */
	protected $recordViewUrls = array();

	/**
	 * Finds the property value of a given workspace.
	 *
	 * @param integer $workspaceId
	 * @param string $propertyName
	 * @return NULL|string
	 */
	public function getPropertyValue($workspaceId, $propertyName) {
		$propertyValue = NULL;
		$workspace = $this->findById($workspaceId);

		if (isset($workspace[$propertyName])) {
			$propertyValue = $workspace[$propertyName];
		}

		return $propertyValue;
	}

	/**
	 * @param string $orderBy
	 * @return array
	 */
	public function findAll($orderBy = 'uid') {
		if (!isset($this->workspaces)) {
			$this->workspaces = $this->getDatabase()->exec_SELECTgetRows(
				'*',
				'sys_workspace',
				'pid=0' . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('sys_workspace'),
				'',
				'',
				'',
				'uid'
			);

			if (!is_array($this->workspaces)) {
				$this->workspaces = array();
			}
		}

		$workspaces = $this->workspaces;

		if ($orderBy !== 'uid') {
			$workspaces = $this->getSortingService()->sort(
				$workspaces,
				$orderBy
			);
		}

		return $workspaces;
	}

	/**
	 * @param integer $workspaceId
	 * @return array|NULL
	 */
	public function findById($workspaceId) {
		$workspace = NULL;
		$this->findAll();

		if (!empty($this->workspaces[$workspaceId])) {
			$workspace = $this->workspaces[$workspaceId];
		}

		return $workspace;
	}

	/**
	 * @return \TYPO3\CMS\Workspaces\Service\SortingService
	 */
	protected function getSortingService() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Workspaces\\Service\\SortingService'
		);
	}

	/**
	 * @return \TYPO3\CMS\Dbal\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}

}


?>