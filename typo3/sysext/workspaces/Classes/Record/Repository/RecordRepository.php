<?php
namespace TYPO3\CMS\Workspaces\Record\Repository;

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
class RecordRepository {

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var array
	 */
	protected $registeredRecordIds = array();

	/**
	 * @var array
	 */
	protected $records;

	public function __construct($tableName) {
		$this->tableName = $tableName;
	}

	public function registerId($recordId) {
		$recordId = intval($recordId);

		if (empty($recordId)) {
			return FALSE;
		}

		$this->registeredRecordIds[] = $recordId;

		return TRUE;
	}
	public function findAll() {
		if (!isset($this->records)) {
			$where = '1=1';
			if (count($this->registeredRecordIds) > 0) {
				$this->registeredRecordIds = array_unique($this->registeredRecordIds);
				$where = 'uid IN (' . implode(',', $this->registeredRecordIds) . ')';
			}

			$this->records = $this->getDatabase()->exec_SELECTgetRows(
				$this->getFields(),
				$this->tableName,
				$where . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause($this->tableName),
				'',
				'',
				'',
				'uid'
			);

			if (!is_array($this->records)) {
				$this->records = array();
			}
		}

		return $this->records;
	}

	public function findById($recordId) {
		$record = NULL;
		$this->findAll();

		if (!empty($this->records[$recordId])) {
			$record = $this->records[$recordId];
		}

		return $record;
	}

	protected function getFields() {
		$fields = array(
			'uid',
			'pid',
			't3ver_oid',
			't3ver_id',
			't3ver_wsid',
			't3ver_label',
			't3ver_state',
			't3ver_stage',
			't3ver_count',
			't3ver_tstamp',
		);

		$tcaControlKeys = array(
			'crdate',
			'cruser_id',
			'deleted',
			'origUid',
			'transOrigPointerField',
			'tstamp',
			'type',
			'label',
			'label_alt',
		);

		if ($GLOBALS['TCA'][$this->tableName]['ctrl']['versioningWS'] == 2) {
			$fields[] = 't3ver_move_id';
		}

		foreach ($tcaControlKeys as $tcaControlKey) {
			if (!empty($GLOBALS['TCA'][$this->tableName]['ctrl'][$tcaControlKey])) {
				$fields = array_merge(
					$fields,
					\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
						',',
						$GLOBALS['TCA'][$this->tableName]['ctrl'][$tcaControlKey],
						TRUE
					)
				);
			}
		}

		if (!empty($GLOBALS['TCA'][$this->tableName]['ctrl']['enablecolumns'])) {
			$fields = array_merge(
				$fields,
				array_values(
					(array) $GLOBALS['TCA'][$this->tableName]['ctrl']['enablecolumns']
				)
			);
		}

		$fields = array_unique($fields);

		return implode(',', $fields);
	}

	/**
	 * @return \TYPO3\CMS\Dbal\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}

}


?>
