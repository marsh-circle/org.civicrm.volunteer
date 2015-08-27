<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * File for the CiviCRM APIv3 Volunteer Project Contact functions
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Project_Contact
 * @copyright CiviCRM LLC (c) 2004-2015
 */


/**
 * Create or update a project contact
 *
 * @param array $params  Associative array of properties
 *                       name/value pairs to insert in new 'project contact'
 * @example
 *
 * @return array api result array
 * {@getfields volunteer_project_contact_create}
 * @access public
 */
function civicrm_api3_volunteer_project_contact_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_project_contact_create_spec(&$params) {
  $params['project_id']['api.required'] = 1;
  $params['contact_id']['api.required'] = 1;
  $params['relationship_type_id']['api.required'] = 1;
}

/**
 * Returns array of project contacts matching a set of one or more properties
 *
 * @param array $params  Array of one or more valid
 *                       property_name=>value pairs.
 *
 * @return array  Array of matching project contacts
 * {@getfields volunteer_project_contact_get}
 * @access public
 */
function civicrm_api3_volunteer_project_contact_get($params) {
  $result = _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
  if (!empty($result['values'])) {
    foreach ($result['values'] as &$projectContact) {
      $optionValue = civicrm_api3('OptionValue', 'getsingle', array(
        'option_group_id' => CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
        'value' => $projectContact['relationship_type_id'],
      ));

      $projectContact['relationship_type_label'] = $optionValue['label'];
      $projectContact['relationship_type_name'] = $optionValue['name'];
    }
  }
  return $result;

}

/**
 * Delete an existing project contact
 *
 * This method is used to delete the relationship(s) between a contact and a
 * project.
 *
 * @param array $params  array containing id of the project
 *                       to be deleted
 *
 * @return array  returns flag true if successfull, error
 *                message otherwise
 * {@getfields volunteer_project_delete}
 * @access public
 */
function civicrm_api3_volunteer_project_contact_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}