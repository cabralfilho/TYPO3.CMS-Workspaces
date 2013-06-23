<?php
namespace TYPO3\CMS\Workspaces\Service;

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
 * Workspace service
 *
 * @author Workspaces Team (http://forge.typo3.org/projects/show/typo3v4-workspaces)
 */
class RecordService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array|\TYPO3\CMS\Workspaces\Record\Repository\RecordRepository[]
	 */
	protected $repositories = array();

	/**
	 * @param string $tableName
	 * @return \TYPO3\CMS\Workspaces\Record\Repository\RecordRepository
	 */
	public function getRepository($tableName) {
		if (!isset($this->repositories[$tableName])) {
			$this->repositories[$tableName] = $this->createRecordRepository($tableName);
		}
		return $this->repositories[$tableName];
	}

	public function destroyRepository($tableName) {
		if (isset($this->repositories[$tableName])) {
			unset($this->repositories[$tableName]);
		}
	}

	/**
	 * @param string $tableName
	 * @return \TYPO3\CMS\Workspaces\Record\Repository\RecordRepository
	 */
	protected function createRecordRepository($tableName) {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Workspaces\\Record\\Repository\\RecordRepository',
			$tableName
		);
	}

}


?>