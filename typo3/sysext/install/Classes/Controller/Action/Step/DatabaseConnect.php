<?php
namespace TYPO3\CMS\Install\Controller\Action\Step;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Kuhn <lolli@schwarzbu.ch>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Install\Controller\Action;

/**
 * Database connect step:
 * - Needs execution if database credentials are not set or fail to connect
 * - Renders fields for database connection fields
 * - Sets database credentials in LocalConfiguration
 * - Loads / unloads ext:dbal and ext:adodb if requested
 */
class DatabaseConnect extends Action\AbstractAction implements StepInterface {

	/**
	 * Execute database step:
	 * - Load / unload dbal & adodb
	 * - Set database connect credentials in LocalConfiguration
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		$result = array();

		/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
		$configurationManager = $this->objectManager->get('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');

		$postValues = $this->postValues['values'];
		if (isset($postValues['loadDbal'])) {
			$result[] = $this->executeLoadDbalExtension();
		} elseif ($postValues['unloadDbal']) {
			$result[] = $this->executeUnloadDbalExtension();
		} elseif ($postValues['setDbalDriver']) {
			$driver = $postValues['setDbalDriver'];
			switch ($driver) {
				case 'mssql':
				case 'odbc_mssql':
					$driverConfig = array(
						'useNameQuote' => TRUE,
						'quoteClob' => FALSE,
					);
					break;
				case 'oci8':
					$driverConfig = array(
						'driverOptions' => array(
							'connectSID' => '',
						),
					);
					break;
			}
			$config = array(
				'_DEFAULT' => array(
					'type' => 'adodb',
					'config' => array(
						'driver' => $driver,
					)
				)
			);
			if (isset($driverConfig)) {
				$config['_DEFAULT']['config'] = array_merge($config['_DEFAULT']['config'], $driverConfig);
			}
			$configurationManager->setLocalConfigurationValueByPath('EXTCONF/dbal/handlerCfg', $config);
		} else {
			$localConfigurationPathValuePairs = array();

			if ($this->isDbalEnabled()) {
				$config = $configurationManager->getConfigurationValueByPath('EXTCONF/dbal/handlerCfg');
				$driver = $config['_DEFAULT']['config']['driver'];
				if ($driver === 'oci8') {
					$configurationManager['_DEFAULT']['config']['driverOptions']['connectSID']
						= $postValues['type'] === 'sid' ? TRUE : FALSE;
				}
			}

			if (isset($postValues['username'])) {
				$value = $postValues['username'];
				if (strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/username'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database username not valid');
					$errorStatus->setMessage('Given username must be shorter than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (isset($postValues['password'])) {
				$value = $postValues['password'];
				if (strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/password'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database password not valid');
					$errorStatus->setMessage('Given password must be shorter than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (isset($postValues['host'])) {
				$value = $postValues['host'];
				if (preg_match('/^[a-zA-Z0-9_\\.-]+(:.+)?$/', $value) && strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/host'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database host not valid');
					$errorStatus->setMessage('Given host is not alphanumeric (a-z, A-Z, 0-9 or _-.:) or longer than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (isset($postValues['port'])) {
				$value = $postValues['port'];
				if (preg_match('/^[0-9]+(:.+)?$/', $value) && $value > 0 && $value <= 65535) {
					$localConfigurationPathValuePairs['DB/port'] = (int)$value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database port not valid');
					$errorStatus->setMessage('Given port is not numeric or within range 1 to 65535');
					$result[] = $errorStatus;
				}
			}

			if (isset($postValues['database'])) {
				$value = $postValues['database'];
				if (strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/database'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database name not valid');
					$errorStatus->setMessage('Given database name must be shorter than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (!empty($localConfigurationPathValuePairs)) {
				$configurationManager->setLocalConfigurationValuesByPathValuePairs($localConfigurationPathValuePairs);

				// After setting new credentials, test again and create an error message if connect is not successful
				// @TODO: This could be simplified, if isConnectSuccessful could be released from TYPO3_CONF_VARS
				// and feeded with connect values directly in order to obsolete the bootstrap reload.
				\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
					->populateLocalConfiguration()
					->setCoreCacheToNullBackend();
				if ($this->isDbalEnabled()) {
					require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('dbal') . 'ext_localconf.php');
					$GLOBALS['typo3CacheManager']->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
				}
				if (!$this->isConnectSuccessful()) {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database connect not successful');
					$errorStatus->setMessage('Connecting the database with given settings failed. Please check.');
					$result[] = $errorStatus;
				}
			}
		}

		return $result;
	}

	/**
	 * Step needs to be executed if database connection is not successful.
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		if (!$this->isConnectSuccessful()) {
			return TRUE;
		}
		if (!isset($GLOBALS['TYPO3_CONF_VARS']['DB']['host'])
			|| !isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port'])
		) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Render this step
	 *
	 * @return string
	 */
	public function handle() {
		$this->initialize();

		$isDbalEnabled = $this->isDbalEnabled();
		$this->view
			->assign('isDbalEnabled', $isDbalEnabled)
			->assign('username', $GLOBALS['TYPO3_CONF_VARS']['DB']['username'] ?: '')
			->assign('password', $GLOBALS['TYPO3_CONF_VARS']['DB']['password'] ?: '')
			->assign('host', $this->getConfiguredHost() ?: '127.0.0.1')
			->assign('port', $this->getConfiguredOrDefaultPort())
			->assign('database', $GLOBALS['TYPO3_CONF_VARS']['DB']['database'] ?: '');

		if ($isDbalEnabled) {
			$this->view->assign('selectedDbalDriver', $this->getSelectedDbalDriver());
			$this->view->assign('dbalDrivers', $this->getAvailableDbalDrivers());
			$this->setDbalInputFieldsToRender();
		} else {
			$this->view
				->assign('renderConnectDetailsUsername', TRUE)
				->assign('renderConnectDetailsPassword', TRUE)
				->assign('renderConnectDetailsHost', TRUE)
				->assign('renderConnectDetailsPort', TRUE);
		}

		return $this->view->render();
	}

	/**
	 * Render fields required for successful connect based on dbal driver selection.
	 * Hint: There is a code duplication in handle() and this method. This
	 * is done by intention to keep this code area easy to maintain and understand.
	 *
	 * @return void
	 */
	protected function setDbalInputFieldsToRender() {
		$driver = $this->getSelectedDbalDriver();
		switch($driver) {
			case 'mssql':
			case 'odbc_mssql':
			case 'postgres':
				$this->view
					->assign('renderConnectDetailsUsername', TRUE)
					->assign('renderConnectDetailsPassword', TRUE)
					->assign('renderConnectDetailsHost', TRUE)
					->assign('renderConnectDetailsPort', TRUE)
					->assign('renderConnectDetailsDatabase', TRUE);
				break;
			case 'oci8':
				$this->view
					->assign('renderConnectDetailsUsername', TRUE)
					->assign('renderConnectDetailsPassword', TRUE)
					->assign('renderConnectDetailsHost', TRUE)
					->assign('renderConnectDetailsPort', TRUE)
					->assign('renderConnectDetailsDatabase', TRUE)
					->assign('renderConnectDetailsOracleSidConnect', TRUE);
				$type = isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driverOptions']['connectSID'])
					? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driverOptions']['connectSID']
					: '';
				if ($type === TRUE) {
					$this->view->assign('oracleSidSelected', TRUE);
				}
				break;
		}
	}

	/**
	 * Render connect port and label
	 *
	 * @return integer Configured or default port
	 */
	protected function getConfiguredOrDefaultPort() {
		$configuredPort = (int)$this->getConfiguredPort();
		if (!$configuredPort) {
			if ($this->isDbalEnabled()) {
				$driver = $this->getSelectedDbalDriver();
				switch ($driver) {
					case 'postgres':
						$port = 5432;
						break;
					case 'mssql':
					case 'odbc_mssql':
						$port = 1433;
						break;
					case 'oci8':
						$port = 1521;
						break;
					default:
						$port = 3306;
				}
			} else {
				$port = 3306;
			}
		} else {
			$port = $configuredPort;
		}
		return $port;
	}

	/**
	 * Test connection with given credentials
	 *
	 * @return boolean TRUE if connect was successful
	 */
	protected function isConnectSuccessful() {
		/** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
		$databaseConnection = $this->objectManager->get('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');

		if ($this->isDbalEnabled()) {
			// Set additional connect information based on dbal driver. postgres for example needs
			// database name already for connect.
			if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['database'])) {
				$databaseConnection->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
			}
		}

		$username = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['username'] : '';
		$databaseConnection->setDatabaseUsername($username);
		$password = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['password']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['password'] : '';
		$databaseConnection->setDatabasePassword($password);
		$databaseConnection->setDatabaseHost($this->getConfiguredHost());
		$databaseConnection->setDatabasePort($this->getConfiguredPort());

		$result = FALSE;
		if (@$databaseConnection->sql_pconnect()) {
			$result = TRUE;
		}
		return $result;
	}

	/**
	 * Returns a list of database drivers that are available on current server.
	 *
	 * @return array
	 */
	protected function getAvailableDbalDrivers() {
		$supportedDrivers = $this->getSupportedDbalDrivers();
		$availableDrivers = array();
		$selectedDbalDriver = $this->getSelectedDbalDriver();
		foreach ($supportedDrivers as $abstractionLayer => $drivers) {
			foreach ($drivers as $driver => $info) {
				if (isset($info['combine']) && $info['combine'] === 'OR') {
					$isAvailable = FALSE;
				} else {
					$isAvailable = TRUE;
				}
				// Loop through each PHP module dependency to ensure it is loaded
				foreach ($info['extensions'] as $extension) {
					if (isset($info['combine']) && $info['combine'] === 'OR') {
						$isAvailable |= extension_loaded($extension);
					} else {
						$isAvailable &= extension_loaded($extension);
					}
				}
				if ($isAvailable) {
					if (!isset($availableDrivers[$abstractionLayer])) {
						$availableDrivers[$abstractionLayer] = array();
					}
					$availableDrivers[$abstractionLayer][$driver] = array();
					$availableDrivers[$abstractionLayer][$driver]['driver'] = $driver;
					$availableDrivers[$abstractionLayer][$driver]['label'] = $info['label'];
					$availableDrivers[$abstractionLayer][$driver]['selected'] = FALSE;
					if ($selectedDbalDriver === $driver) {
						$availableDrivers[$abstractionLayer][$driver]['selected'] = TRUE;
					}
				}
			}
		}
		return $availableDrivers;
	}

	/**
	 * Returns a list of DBAL supported database drivers, with a
	 * user-friendly name and any PHP module dependency.
	 *
	 * @return array
	 */
	protected function getSupportedDbalDrivers() {
		$supportedDrivers = array(
			'Native' => array(
				'mssql' => array(
					'label' => 'Microsoft SQL Server',
					'extensions' => array('mssql')
				),
				'oci8' => array(
					'label' => 'Oracle OCI8',
					'extensions' => array('oci8')
				),
				'postgres' => array(
					'label' => 'PostgreSQL',
					'extensions' => array('pgsql')
				)
			),
			'ODBC' => array(
				'odbc_mssql' => array(
					'label' => 'Microsoft SQL Server',
					'extensions' => array('odbc', 'mssql')
				)
			)
		);
		return $supportedDrivers;
	}

	/**
	 * Get selected dbal driver if any
	 *
	 * @return string Dbal driver or empty string if not yet selected
	 */
	protected function getSelectedDbalDriver() {
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driver'])) {
			return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driver'];
		}
		return '';
	}

	/**
	 * Adds dbal and adodb to list of loaded extensions
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function executeLoadDbalExtension() {
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('adodb')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::loadExtension('adodb');
		}
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::loadExtension('dbal');
		}
		/** @var $errorStatus \TYPO3\CMS\Install\Status\WarningStatus */
		$warningStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\WarningStatus');
		$warningStatus->setTitle('Loaded database abstraction layer');
		return $warningStatus;
	}

	/**
	 * Remove dbal and adodb from list of loaded extensions
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function executeUnloadDbalExtension() {
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('adodb')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::unloadExtension('adodb');
		}
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::unloadExtension('dbal');
		}
		// @TODO: Remove configuration from TYPO3_CONF_VARS['EXTCONF']['dbal']
		/** @var $errorStatus \TYPO3\CMS\Install\Status\WarningStatus */
		$warningStatus = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\WarningStatus');
		$warningStatus->setTitle('Removed database abstraction layer');
		return $warningStatus;
	}

	/**
	 * Returns configured host with port split off if given
	 *
	 * @return string
	 */
	protected function getConfiguredHost() {
		$host = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['host']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['host'] : '';
		$port = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['port'] : '';
		if (strlen($port) < 1 && strpos($host, ':') > 0) {
			list($host) = explode(':', $host);
		}
		return $host;
	}

	/**
	 * Returns configured port. Gets port from host value if port is not yet set.
	 *
	 * @return integer
	 */
	protected function getConfiguredPort() {
		$host = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['host']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['host'] : '';
		$port = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['port'] : '';
		if (!strlen($port) > 0 && strpos($host, ':') > 0) {
			$hostPortArray = explode(':', $host);
			$port = $hostPortArray[1];
		}
		return (int)$port;
	}
}
?>
