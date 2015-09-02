<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
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
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

class CRM_Volunteer_BAO_Project extends CRM_Volunteer_DAO_Project {

  /**
   * Array of attributes on the related entity, translated to a common vocabulary.
   *
   * For example, an event's 'start_date' property is standardized to
   * 'start_time.'
   *
   * @see CRM_Volunteer_BAO_Project::getEntityAttributes()
   * @var array
   */
  private $entityAttributes = array();

  /**
   * The ID of the flexible Need for this Project. Accessible via __get method.
   *
   * @var int
   */
  private $flexible_need_id;

  /**
   * Array of associated Needs. Accessible via __get method.
   *
   * @var array
   */
  private $needs = array();

  /**
   * Array of associated Roles. Accessible via __get method.
   *
   * @var array Role labels keyed by IDs
   */
  private $roles = array();

  /**
   * Array of open needs. Open means:
   * <ol>
   *   <li>that the number of volunteer assignments associated with the need is
   *    fewer than quantity specified for the need</li>
   *   <li>that the need does not start in the past</li>
   *   <li>that the need is active</li>
   *   <li>that the need is visible</li>
   *   <li>that the need has a start_time (i.e., is not flexible)</li>
   * </ol>
   * Accessible via __get method.
   *
   * @var array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private $open_needs = array();

  /**
   * The start_date of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $start_date;

  /**
   * The end_date of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $end_date;

  /**
   * class constructor
   */
  function __construct() {
    parent::__construct();
  }

  /**
   * Implementation of PHP's magic __get() function.
   *
   * @param string $name The inaccessible property
   * @return mixed Result of fetcher method
   */
  function __get($name) {
    $f = "_get_$name";
    if (method_exists($this, $f)) {
      return $this->$f();
    }
  }

  /**
   * Implementation of PHP's magic __isset() function.
   *
   * @param string $name The inaccessible property
   * @return boolean
   */
  function __isset($name) {
    $result = FALSE;
    $f = "_get_$name";
    if (method_exists($this, $f)) {
      $v = $this->$f();
      $result = !empty($v);
    }
    return $result;
  }

  /**
   * Gets related contacts of a specified type for a project.
   *
   * @param int $projectId
   * @param mixed $relationshipType
   *   Use either the value or the machine name for the optionValue
   * @return array
   *   Array of contact IDs
   */
  public static function getContactsByRelationship($projectId, $relationshipType) {
    $contactIds = array();

    $api = civicrm_api3('VolunteerProjectContact', 'get', array(
      'project_id' => $projectId,
      'relationship_type_id' => $relationshipType,
    ));
    foreach ($api['values'] as $rel) {
      $contactIds[] = $rel['contact_id'];
    }

    return $contactIds;
  }

  /**
   * Create a Volunteer Project
   *
   * Takes an associative array and creates a Project object. This function is
   * invoked from within the web form layer and also from the API layer. Allows
   * the creation of project contacts, e.g.:
   *
   * $params['project_contacts'] = array(
   *   $relationship_type_name_or_id => $arr_contact_ids,
   * );
   *
   * @param array   $params      an assoc array of name/value pairs
   *
   * @return CRM_Volunteer_BAO_Project object
   * @access public
   * @static
   */
  static function create(array $params) {
    $projectId = CRM_Utils_Array::value('id', $params);
    $op = empty($projectId) ? CRM_Core_Action::ADD : CRM_Core_Action::UPDATE;

    if (!empty($params['check_permissions']) && !CRM_Volunteer_Permission::checkProjectPerms($op, $projectId)) {
      CRM_Utils_System::permissionDenied();

      // FIXME: If we don't return here, the script keeps executing. This is not
      // what I expect from CRM_Utils_System::permissionDenied().
      return FALSE;
    }

    // check required params
    if (!self::dataExists($params)) {
      CRM_Core_Error::fatal('Not enough data to create volunteer project object.');
    }

    // default to active unless explicitly turned off
    $params['is_active'] = CRM_Utils_Array::value('is_active', $params, TRUE);

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);

    $project->save();

    $projectContacts = CRM_Utils_Array::value('project_contacts', $params, array());
    foreach ($projectContacts as $relationshipType => $contactIds) {
      foreach ($contactIds as $id) {
        civicrm_api3('VolunteerProjectContact', 'create', array(
          'contact_id' => $id,
          'project_id' => $project->id,
          'relationship_type_id' => $relationshipType,
        ));
      }
    }

    return $project;
  }

  /**
   * Find out if a project is active
   *
   * @param $entityId
   * @param $entityTable
   * @return boolean|null Boolean if project exists, null otherwise
   */
  static function isActive($entityId, $entityTable) {
    $params['entity_id'] = $entityId;
    $params['entity_table'] = $entityTable;
    $projects = self::retrieve($params);

    if (count($projects) === 1) {
      $p = current($projects);
      return $p->is_active;
    }
    return NULL;
  }

  /**
   * Get a list of Projects matching the params.
   *
   * This function is invoked from within the web form layer and also from the
   * API layer. Params keys are either column names of civicrm_volunteer_project, or
   * 'project_contacts.' @see CRM_Volunteer_BAO_Project::create() for details on
   * this parameter.
   *
   * NOTE: This method does not return data re project_contacts; however,
   * this parameter can be used to filter the list of Projects that is returned.
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  static function retrieve(array $params) {
    $result = array();

    $projectIds = array();
    if (!empty($params['project_contacts'])) {
      foreach ($params['project_contacts'] as $relType => $contactIds) {
        $api = civicrm_api3('VolunteerProjectContact', 'get', array(
          'contact_id' => array("IN" => (array) $contactIds),
          'relationship_type_id' => $relType,
        ));
        foreach ($api['values'] as $data) {
          $projectIds[] = $data['project_id'];
        }
      }
      unset($params['project_contacts']);
    }

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);

    if (!empty($projectIds)) {
      $valuesSql = implode(', ', array_unique($projectIds));
      $project->whereAdd(" id IN ({$valuesSql}) ");
    }

    $project->find();

    while ($project->fetch()) {
      $result[(int) $project->id] = clone $project;
    }

    $project->free();

    return $result;
  }

  /**
   * Wrapper method for retrieve
   *
   * @param mixed $id Int or int-like string representing project ID
   * @return CRM_Volunteer_BAO_Project
   */
  static function retrieveByID($id) {
    $id = (int) CRM_Utils_Type::validate($id, 'Integer');

    $projects = self::retrieve(array('id' => $id));

    if (!array_key_exists($id, $projects)) {
      CRM_Core_Error::fatal("No project with ID $id exists.");
    }

    return $projects[$id];
  }

  /**
   * Check if there is absolute minimum of data to add the object
   *
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return boolean
   * @access public
   */
  public static function dataExists($params) {
    if (CRM_Utils_Array::value('id', $params)) {
      return TRUE;
    }

    if (
      CRM_Utils_Array::value('entity_id', $params) &&
      CRM_Utils_Array::value('entity_table', $params) &&
      CRM_Utils_Array::value('title', $params)
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns TRUE if value represents an "off" value, FALSE otherwise
   *
   * @param type $value
   * @return boolean
   * @access public
   */
  public static function isOff($value) {
    if (in_array($value, array(FALSE, 0, '0'), TRUE)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Given an associative array of name/value pairs, extract all the values
   * that belong to this object and initialize the object with said values. This
   * override adds a little data massaging prior to calling its parent.
   *
   * @param array $params (reference ) associative array of name/value pairs
   *
   * @return boolean      did we copy all null values into the object
   * @access public
   */
  public function copyValues(&$params) {
    if (array_key_exists('is_active', $params)) {
      /*
       * don't force is_active to have a value if none was set, to allow searches
       * where the is_active state of Projects is irrelevant
       */
      $params['is_active'] = CRM_Volunteer_BAO_Project::isOff($params['is_active']) ? 0 : 1;
    }
    return parent::copyValues($params);
  }

  /**
   * Fetches attributes for the associated entity and puts them in
   * $this->entityAttributes, using a common vocabulary defined in $arrayKeys.
   *
   * @see CRM_Volunteer_BAO_Project::$entityAttributes
   * @return array
   */
  public function getEntityAttributes() {
    if (!$this->entityAttributes) {
      $arrayKeys = array('start_time', 'title');
      $this->entityAttributes = array_fill_keys($arrayKeys, NULL);

      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $result = civicrm_api3('Event', 'getsingle', array(
              'id' => $this->entity_id,
            ));
            $this->entityAttributes['title'] = $result['title'];
            $this->entityAttributes['start_time'] = $result['start_date'];
            break;
        }
      }
    }
    return $this->entityAttributes;
  }

  /**
   * Given project_id, return ID of flexible Need
   *
   * @param int $project_id
   * @return mixed Integer on success, else NULL
   */
  public static function getFlexibleNeedID ($project_id) {
    $result = NULL;

    if (is_int($project_id) || ctype_digit($project_id)) {
      $flexibleNeed = civicrm_api('volunteer_need', 'getvalue', array(
        'is_active' => 1,
        'is_flexible' => 1,
        'project_id' => $project_id,
        'return' => 'id',
        'version' => 3,
      ));
      if (CRM_Utils_Array::value('is_error', $flexibleNeed) !== 1) {
        $result = (int) $flexibleNeed;
      }
    }

    return $result;
  }

  /**
   * Sets and returns the start date of the entity associated with this Project
   *
   * @access private
   */
  private function _get_start_date() {
    if (!$this->start_date) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $params = array(
              'id' => $this->entity_id,
              'return' => array('start_date'),
            );
            $result = civicrm_api3('Event', 'get', $params);
            $this->start_date = $result['values'][$this->entity_id]['start_date'];
            break;
        }
      }
    }
    return $this->start_date;
  }

  /**
   * Sets and returns the end date of the entity associated with this Project
   *
   * @access private
   */
  private function _get_end_date() {
    if (!$this->end_date) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $params = array(
              'id' => $this->entity_id,
              'return' => array('end_date'),
            );
            $result = civicrm_api3('Event', 'get', $params);
            $this->end_date = CRM_Utils_Array::value('end_date', $result['values'][$this->entity_id]);
            break;
        }
      }
    }
    return $this->end_date;
  }

  /**
   * Sets $this->needs and returns the Needs associated with this Project. Delegate of __get().
   * Note: only active, visible needs are returned.
   *
   * @return array Needs as returned by API
   */
  private function _get_needs() {
    if (empty($this->needs)) {
      $result = civicrm_api3('VolunteerNeed', 'get', array(
        'is_active' => '1',
        'project_id' => $this->id,
        'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
        'options' => array(
          'sort' => 'start_time',
          'limit' => 0,
        ),
      ));
      $this->needs = $result['values'];
      foreach (array_keys($this->needs) as $need_id) {
        $this->needs[$need_id]['quantity_assigned'] = CRM_Volunteer_BAO_Need::getAssignmentCount($need_id);
      }
    }

    return $this->needs;
  }

  /**
   * Sets $this->roles and returns the Roles associated with this Project. Delegate of __get().
   * Note: only roles for active, visible needs are returned.
   *
   * @return array Roles, labels keyed by IDs
   */
  private function _get_roles() {
    if (empty($this->roles)) {
      $roles = array();

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      foreach ($this->needs as $need) {
        if (CRM_Utils_Array::value('is_flexible', $need) == '1') {
          $roles[CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID] = CRM_Volunteer_BAO_Need::getFlexibleRoleLabel();
        } else {
          $role_id = CRM_Utils_Array::value('role_id', $need);
          $roles[$role_id] = CRM_Core_OptionGroup::getLabel(
            CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP,
            $role_id
          );
        }
      }
      asort($roles);
      $this->roles = $roles;
    }

    return $this->roles;
  }

  /**
   * Sets and returns $this->open_needs. Delegate of __get().
   *
   * @return array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private function _get_open_needs() {
    if (empty($this->open_needs)) {

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      foreach ($this->needs as $id => $need) {
        if (
          !empty($need['start_time'])
          && ($need['quantity'] > $need['quantity_assigned'])
          && (strtotime($need['start_time']) > time())
        ) {
          $this->open_needs[$id] = array(
            'label' => CRM_Volunteer_BAO_Need::getTimes($need['start_time'], CRM_Utils_Array::value('duration', $need)),
            'role_id' => $need['role_id'],
          );
        }
      }
    }

    return $this->open_needs;
  }

  /**
   * Sets and returns $this->flexible_need_id. Delegate of __get().
   *
   * @return mixed Integer if project has a flexible need, else NULL
   */
  private function _get_flexible_need_id() {
    return self::getFlexibleNeedID($this->id);
  }
}