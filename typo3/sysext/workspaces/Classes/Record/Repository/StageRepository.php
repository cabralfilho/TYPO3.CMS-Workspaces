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
 * Repository for stage records
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class StageRepository implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array
	 */
	protected $stages;

	/**
	 * @var array
	 */
	protected $workspaceStages = array();

	/**
	 * Finds the property value of a given stage.
	 *
	 * @param integer $stageId
	 * @param string $propertyName
	 * @return NULL|string
	 */
	public function getPropertyValue($stageId, $propertyName) {
		$propertyValue = NULL;
		$stage = $this->findById($stageId);

		if (isset($stage[$propertyName])) {
			$propertyValue = $stage[$propertyName];
		}

		return $propertyValue;
	}

	/**
	 * @return array
	 */
	public function findAll() {
		if (!isset($this->stages)) {
			$this->stages = $this->getDatabase()->exec_SELECTgetRows(
				'*',
				'sys_workspace_stage',
				'pid=0' . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('sys_workspace_stage'),
				'',
				'sorting',
				'',
				'uid'
			);

			if (!is_array($this->stages)) {
				$this->stages = array();
			}
		}

		return $this->stages;
	}

	/**
	 * @param integer $stageId
	 * @return array|NULL
	 */
	public function findById($stageId) {
		$stage = NULL;
		$this->findAll();

		if (!empty($this->stages[$stageId])) {
			$stage = $this->stages[$stageId];
		}

		return $stage;
	}

	/**
	 * @param integer $workspaceId
	 * @return array
	 */
	public function findByWorkspace($workspaceId) {
		if (!isset($this->workspaceStages[$workspaceId])) {
			$this->workspaceStages[$workspaceId] = array();

			foreach ($this->findAll() as $stage) {
				if ($stage['parentid'] == $workspaceId && $stage['parenttable'] === 'sys_workspace') {
					$stageId = $stage['uid'];
					$this->workspaceStages[$workspaceId][$stageId] = $stage;
				}
			}
		}

		return $this->workspaceStages[$workspaceId];
	}

	/**
	 * @return \TYPO3\CMS\Dbal\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}

}


?>