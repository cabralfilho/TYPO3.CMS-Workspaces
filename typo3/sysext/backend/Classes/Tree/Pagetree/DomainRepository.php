<?php
namespace TYPO3\CMS\Backend\Tree\Pagetree;

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
 * Repository for domain records
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class DomainRepository implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array
	 */
	protected $domains;

	/**
	 * @param string $orderBy
	 * @return array
	 */
	public function findAll() {
		if (!isset($this->domains)) {
			$this->domains = $this->getDatabase()->exec_SELECTgetRows(
				'*',
				'sys_domain',
				'1=1' . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('sys_domain') . \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields('sys_domain'),
				'',
				'sorting',
				'',
				'uid'
			);

			if (!is_array($this->domains)) {
				$this->domains = array();
			}
		}

		return $this->domains;
	}

	/**
	 * @param integer $domainId
	 * @return array|NULL
	 */
	public function findById($domainId) {
		$workspace = NULL;
		$this->findAll();

		if (!empty($this->domains[$domainId])) {
			$workspace = $this->domains[$domainId];
		}

		return $workspace;
	}

	/**
	 * @param integer $pageId
	 * @return array
	 */
	public function findByPageId($pageId) {
		$domains = array();
		$this->findAll();

		foreach ($this->domains as $domain) {
			if ($domain['pid'] == $pageId) {
				$domains[] = $domain;
			}
		}

		return $domains;
	}

	/**
	 * @return \TYPO3\CMS\Dbal\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}

}


?>