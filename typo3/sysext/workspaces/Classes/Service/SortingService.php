<?php
namespace TYPO3\CMS\Workspaces\Service;

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
 * Sorting service for arrays.
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 */
class SortingService implements \TYPO3\CMS\Core\SingletonInterface {

	const TYPE_String = 'string';
	const TYPE_Integer = 'integer';

	const DIRECTION_ASCENDING = 'ASC';
	const DIRECTION_DESCENDING = 'DESC';

	/**
	 * @var string
	 */
	protected $field;

	/**
	 * @var string
	 */
	protected $direction = 'ASC';

	/**
	 * @param array $subject
	 * @param string $field
	 * @param string $type
	 * @param string $direction
	 * @return array
	 */
	public function sort(array $subject, $field, $type = self::TYPE_String, $direction = self::DIRECTION_ASCENDING) {
		$this->field = $field;
		$this->direction = $direction;
		usort($subject, array($this, $type . 'Sort'));
		return $subject;
	}

	/**
	 * Implements individual sorting for columns based on integer comparison.
	 *
	 * @param array $a
	 * @param array $b
	 * @return integer
	 */
	protected function integerSort(array $a, array $b) {
		$result = 0;

		if ($a[$this->field] < $b[$this->field]) {
			$result = -1;
		} elseif ($a[$this->field] > $b[$this->field]) {
			$result = 1;
		}

		if ($result && $this->direction === self::DIRECTION_DESCENDING) {
			$result *= -1;
		}

		return $result;
	}

	/**
	 * Implements individual sorting for columns based on string comparison.
	 *
	 * @param array $a
	 * @param array $b
	 * @return integer
	 */
	protected function stringSort($a, $b) {
		$result = strcasecmp($a[$this->field], $b[$this->field]);

		if ($result && $this->direction === self::DIRECTION_DESCENDING) {
			$result *= -1;
		}

		return $result;
	}

}


?>