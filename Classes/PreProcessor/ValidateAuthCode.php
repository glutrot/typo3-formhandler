<?php
namespace Typoheads\Formhandler\PreProcessor;
    /*                                                                        *
     * This script is part of the TYPO3 project - inspiring people to share!  *
     *                                                                        *
     * TYPO3 is free software; you can redistribute it and/or modify it under *
     * the terms of the GNU General Public License version 2 as published by  *
     * the Free Software Foundation.                                          *
     *                                                                        *
     * This script is distributed in the hope that it will be useful, but     *
     * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
     * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
     * Public License for more details.                                       *
     *                                                                        */

/**
 * A pre processor validating an auth code generated by Finisher_GenerateAuthCode.
 *
 * @author    Reinhard Führicht <rf@typoheads.at>
 */
class ValidateAuthCode extends AbstractPreProcessor
{

    /**
     * The main method called by the controller
     *
     * @param array $gp The GET/POST parameters
     * @param array $settings The defined TypoScript settings for the finisher
     * @return array The probably modified GET/POST parameters
     */
    public function process()
    {
        if (strlen(trim($this->gp['authCode'])) > 0) {

            try {
                $authCode = trim($this->gp['authCode']);
                $table = trim($this->gp['table']);
                if ($this->settings['table']) {
                    $table = $this->utilityFuncs->getSingle($this->settings, 'table');
                }
                $uidField = trim($this->gp['uidField']);
                if ($this->settings['uidField']) {
                    $uidField = $this->utilityFuncs->getSingle($this->settings, 'uidField');
                }
                if (strlen($uidField) === 0) {
                    $uidField = 'uid';
                }
                $uid = trim($this->gp['uid']);

                if (!(strlen($table) > 0 && strlen($uid) > 0)) {
                    $this->utilityFuncs->throwException('validateauthcode_insufficient_params');
                }

                $uid = $GLOBALS['TYPO3_DB']->fullQuoteStr($uid, $table);

                //Check if table is valid
                $existingTables = array_keys($GLOBALS['TYPO3_DB']->admin_get_tables());
                if (!in_array($table, $existingTables)) {
                    $this->utilityFuncs->throwException('validateauthcode_insufficient_params');
                }

                //Check if uidField is valid
                $existingFields = array_keys($GLOBALS['TYPO3_DB']->admin_get_fields($table));
                if (!in_array($uidField, $existingFields)) {
                    $this->utilityFuncs->throwException('validateauthcode_insufficient_params');
                }

                $hiddenField = 'disable';
                if ($this->settings['hiddenField']) {
                    $hiddenField = $this->utilityFuncs->getSingle($this->settings, 'hiddenField');
                } elseif ($TCA[$table]['ctrl']['enablecolumns']['disable']) {
                    $hiddenField = $TCA[$table]['ctrl']['enablecolumns']['disable'];
                }
                $selectFields = '*';
                if ($this->settings['selectFields']) {
                    $selectFields = $this->utilityFuncs->getSingle($this->settings, 'selectFields');
                }
                $hiddenStatusValue = 1;
                if (isset($this->settings['hiddenStatusValue'])) {
                    $hiddenStatusValue = $this->utilityFuncs->getSingle($this->settings, 'hiddenStatusValue');
                }
                $hiddenStatusValue = $GLOBALS['TYPO3_DB']->fullQuoteStr($hiddenStatusValue, $table);
                $enableFieldsWhere = '';
                if (intval($this->utilityFuncs->getSingle($this->settings, 'showDeleted')) !== 1) {
                    $enableFieldsWhere = $this->cObj->enableFields($table, 1);
                }
                $query = $GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $table, $uidField . '=' . $uid . ' AND ' . $hiddenField . '=' . $hiddenStatusValue . $enableFieldsWhere);
                $this->utilityFuncs->debugMessage('sql_request', [$query]);
                $res = $GLOBALS['TYPO3_DB']->sql_query($query);
                if ($GLOBALS['TYPO3_DB']->sql_error()) {
                    $this->utilityFuncs->debugMessage('error', [$GLOBALS['TYPO3_DB']->sql_error()], 3);
                }
                if (!$res || $GLOBALS['TYPO3_DB']->sql_num_rows($res) === 0) {
                    $this->utilityFuncs->throwException('validateauthcode_no_record_found');
                }

                $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                $GLOBALS['TYPO3_DB']->sql_free_result($res);
                $this->utilityFuncs->debugMessage('Selected row: ', [], 1, $row);

                $localAuthCode = \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($row), 'formhandler');

                $this->utilityFuncs->debugMessage('Comparing auth codes: ', [], 1, ['Calculated:' => $localAuthCode, 'Given:' => $authCode]);
                if ($localAuthCode !== $authCode) {
                    $this->utilityFuncs->throwException('validateauthcode_invalid_auth_code');
                }
                $activeStatusValue = 0;
                if (isset($this->settings['activeStatusValue'])) {
                    $activeStatusValue = $this->utilityFuncs->getSingle($this->settings, 'activeStatusValue');
                }
                $res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $uidField . '=' . $uid, [$hiddenField => $activeStatusValue]);
                if (!$res) {
                    $this->utilityFuncs->throwException('validateauthcode_update_failed');
                }

                $this->utilityFuncs->doRedirectBasedOnSettings($this->settings, $this->gp);
            } catch (Exception $e) {
                $redirectPage = $this->utilityFuncs->getSingle($this->settings, 'errorRedirectPage');
                if ($redirectPage) {
                    $this->utilityFuncs->doRedirectBasedOnSettings($this->settings, $this->gp, 'errorRedirectPage');
                } else {
                    throw new \Exception($e->getMessage());
                }
            }
        }
        return $this->gp;
    }

}