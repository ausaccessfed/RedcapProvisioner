<?php
/*
 * COmanage Registry CO Dataverse Provisioner Target Model
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry-plugin
 * @since         COmanage Registry v4.3.4
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses("CoProvisionerPluginTarget", "Model");
App::uses("CoService", "Model");

class CoRedcapProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoRedcapProvisionerTarget";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $belongsTo = array(
    "CoProvisioningTarget",
    "Server",
    "CoGroup"
  );
  
  // public $hasMany = array('CoRedcapProjects.id');
  public $hasMany = array(
    "CoRedcapProjects" => array(
      'foreignKey' => 'id'
    ),
    "CoRedcapUsers" => array(
      'foreignKey' => 'id'
    )
  );

  // Default display field for cake generated views
  public $displayField = "server_id";
  
  // Request Http servers
  public $cmServerType = ServerEnum::HttpServer;
  
  // Instance of CoHttpClient for Redcap server
  protected $Http = null;

  // Validation rules for table elements
  public $validate = array(
    'co_provisioning_target_id' => array(
      'rule' => 'numeric',
      'required' => true        // add  ", 'allowEmpty' => false"    ??
    ),
    'server_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => true,
        'unfreeze' => 'CO'      // add  ", 'allowEmpty' => false"    ??
      )
    ),

    'identifier_type' => array(
      'content' => array(
        'rule' => array('validateExtendedType',
                        array('attribute' => 'Identifier.type',
                              'default' => array(IdentifierEnum::ePPN,
                                                 IdentifierEnum::ePTID,
                                                 IdentifierEnum::ePUID,
                                                 IdentifierEnum::Mail,
                                                 IdentifierEnum::OIDCsub,
                                                 IdentifierEnum::OpenID,
                                                 IdentifierEnum::ORCID,
                                                 IdentifierEnum::SamlPairwise,
                                                 IdentifierEnum::SamlSubject,
                                                 IdentifierEnum::UID))),
        'required' => true,
        'allowEmpty' => false
      )
    ),

    'co_group_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true,
        'unfreeze' => 'CO'
      )
    )
  );

  // apiadminrole within only minimal sufficient Admin rights to manage users and roles
  static $apiadminrole = array(   // SHOULD always be the apiadminrole
    'extendedType'   => '',       // should NEVER need an extendedType since this role should NEVER be allocated to an individual!!
    'roleRights' => array(
      'role_label'  => 'API Admin',
      'logging'     => '1',
      'user_rights' => '1',
      'api_export'  => '1',
      'api_import'  => '1',
    )
  );

  // Several pre-defined REDCap Roles with 'role_labels' and 'roleRights' mapped to predefined 'extendedTypes';
  static $standardRolesForRedcap = array(
    0 => array(
      'extendedType'   => 'redcap-admin',
      'roleRights' => array(
        'role_label'                 => 'Project Admin',
        'data_access_group'          => '1',
        'data_export_tool'           => '1',
        'mobile_app'                 => '1',
        'mobile_app_download_data'   => '1',
        'lock_records_all_forms'     => '1',
        'lock_records'               => '1',
        'lock_records_customization' => '1',
        'record_delete'              => '1',
        'record_rename'              => '1',
        'record_create'              => '1',
        'api_import'                 => '1',
        'api_export'                 => '1',
        'api_modules'                => '1',
        'data_quality_execute'       => '1',
        'data_quality_create'        => '1',
        'file_repository'            => '1',
        'logging'                    => '1',
        'data_comparison_tool'       => '1',
        'data_import_tool'           => '1',
        'calendar'                   => '1',
        'stats_and_charts'           => '1',
        'reports'                    => '1',
        'user_rights'                => '1',
        'design'                     => '1',
      )
    ),
    1 => array(
      'extendedType'   => 'redcapstatistician',
      'roleRights' => array(
        'role_label'  => 'Statistician',
        'logging'     => '1',
        'user_rights' => '1',
        'api_export'  => '1',
        'api_import'  => '1',
      )
    ),
    2   => array(
      'extendedType'   => 'redcapcustomrole',
      'roleRights' => array(
        'role_label'           => 'Custom Role',
        'data_comparison_tool' => '1',
        'stats_and_charts'     => '1',
        'reports'              => '1',
        'forms'                => array('form_1' => 2),
      )
    )
  );

  public function beforeSave($options = array()) {
    return true;
  } 

  /*
   * Provision for the specified CO Person or CO Service.
   *  
   * @since  COmanage Registry v4.3.4
   * @param  ProvisioningActionEnum $op                       Registry trans action type triggering provisioning
   * @param  Array                  $coProvisioningTargetData CO Provisioning Target data
   * @param  Array                  $provisioningData         Provisioning data, populated with ['CoPerson'] or ['CoService']
   * @return Boolean True on success
   * 
  */
  
  public function provision($coProvisioningTargetData, $op, $provisioningData) {
    $this->log("FUNCTION provision - OP = " . print_r($op, true));
    //$this->log("FUNCTION provision: coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("FUNCTION provision: provisioningData: " . print_r($provisioningData, true));

    $deleteGroup = false;
    $syncGroup = false;
    $deletePerson = false;
    $syncPerson = false;
    $syncService = false;

    switch($op) {
      case ProvisioningActionEnum::CoGroupAdded:
      case ProvisioningActionEnum::CoGroupUpdated:
      case ProvisioningActionEnum::CoGroupReprovisionRequested:
        //$syncGroup = true;
        break;
      case ProvisioningActionEnum::CoGroupDeleted:
        //$deleteGroup = true;
        break;
      case ProvisioningActionEnum::CoPersonAdded:
      case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
      case ProvisioningActionEnum::CoPersonExpired:
      case ProvisioningActionEnum::CoPersonPetitionProvisioned:
      case ProvisioningActionEnum::CoPersonPipelineProvisioned:
      case ProvisioningActionEnum::CoPersonReprovisionRequested:
      case ProvisioningActionEnum::CoPersonUnexpired:
      case ProvisioningActionEnum::CoPersonUpdated:
        if ($provisioningData['CoPerson']['status'] == StatusEnum::Deleted) {
          $deletePerson = true;
        } else {
          $syncPerson = true;
        }
        break;
      case ProvisioningActionEnum::CoPersonDeleted:
        $deletePerson = true;
        break;
      case ProvisioningActionEnum::CoServiceUpdated:
      case ProvisioningActionEnum::CoServiceAdded:
      case ProvisioningActionEnum::CoServiceReprovisionRequested:
      //case ProvisioningActionEnum::CoServiceDeleted:            //NOT deleting Projects REDCap
        $syncService = true;
        break;
      default:
        // Ignore all other actions. Note: group membership changes
        // are typically handled as CoPersonUpdated events.
        return false;
        break;
    }

    // determine which actions require updates to REDCap
    if ($syncService || $syncPerson) {  
      $valid = $this -> createHttpClient($coProvisioningTargetData);
      if (!$valid) {
        return false;
      }
    }

     if ($deletePerson) {
      // not allowing account deleteion in REDCap, but removing users from projects and REDCap
      $provData = array();
      $this -> cleanupUserRoles($coProvisioningTargetData, $provData, $provisioningData['CoPerson']['id']); 
      return false;
     }
    
    if($syncService) {
      $this -> syncProject($coProvisioningTargetData, $provisioningData);
    }
    
    if ($syncPerson) {
      $provData = array();  // Remove user from roles in REDCap projects
      if ($provisioningData['CoPerson']['status'] == StatusEnum::Active) {
        $this -> syncPerson($coProvisioningTargetData, $provisioningData);
        $provData = $provisioningData['CoPersonRole']; 
      }
      $this -> cleanupUserRoles($coProvisioningTargetData, $provData, $provisioningData['CoPerson']['id']); 
    }
    return true;
  }
  
  /*
  * Add defined DEFAULT roles to a REDCap project
  *
  * @since   COmanage Registry v4.4.0
  * @param   string            $projectApiToken           Project API Token
  * @throws  RuntimeException  $response -> reasonPhrase  HTTP reasonPhrase
  *
  */

  protected function addDefaultProjectRoles($projectApiToken) {    
    //$this->log("FUNCTION addDefaultProjectRoles");

    $standardRoles = CoRedcapProvisionerTarget::$standardRolesForRedcap;
    $standardRoles[] = CoRedcapProvisionerTarget::$apiadminrole;
    
    $data = json_encode(array_column($standardRoles, 'roleRights'));
    //$this->log("data: " . print_r($data, true));
    
    $fields = array(
      'token'   => $projectApiToken,
      'content' => 'userRole',
      'data'    => $data,
    );
    $response = $this -> readWriteApi($fields);
    if ($response -> code < 200 || $response -> code > 299) {
      throw new RuntimeException($response -> reasonPhrase);
    }
  }

  /*
  * Assign the REDPCap API user (as the owner and creator) to APIADMIN project role.
  *
  * @since   COmanage Registry v4.4.0
  * @param   String   $username         Username of user to assign to REDCap project roles
  * @param   String   $projectApiToken  Project API Token
  * @throws  RuntimeException           if project not found
  *
  */

  protected function assignApiAdmin($userName, $projectApiToken) {
    $this -> log("FUNCTION assignApiUser");

    $roleLabel = CoRedcapProvisionerTarget::$apiadminrole['roleRights']['role_label'];
    //$this -> log("roleLabel: " . print_r($roleLabel, true));

    $redcapRoles = json_decode($this -> exportProjectRoles($projectApiToken), true);
    //retreive REDCap unique_role_labels everytime we add a user to a role to ensure 
    //that the name matches what CILogon may need to write the unique_role_labels.

    $redcapRoleLabel = array_column($redcapRoles, 'role_label');
    $uniqueRoleKey = array_search($roleLabel, $redcapRoleLabel);

    //$this -> log("uniqueRoleKey -------------->: " . print_r($uniqueRoleKey, true));
    //$this->log("FUNCTION assignApiUser role_label: " . print_r($redcapRoles[$uniqueRoleKey]['role_label'], true));
    //$this->log("FUNCTION assignApiUser unique_role_name: " . print_r($redcapRoles[$uniqueRoleKey]['unique_role_name'], true));

    $roleAssignment = array(
      'username'         => $userName,
      'unique_role_name' => $redcapRoles[$uniqueRoleKey]['unique_role_name'],
    );

    $data = "[" . json_encode($roleAssignment) . "]";
    // REDcap only accepts a json string enclosed in square brackets '[' and ']'.
    //$this->log("data: " . print_r($data, true));

    $fields = array(
      'token'       => $projectApiToken,
      'content'     => 'userRoleMapping',
      'action'      => 'import',
      'data'        => $data,
    );
    $response = $this -> readWriteApi($fields);

    if ($response -> code < 200 || $response -> code > 299) {
      throw new RuntimeException($response -> reasonPhrase);
    }
  }

  /*
  * Assign/determine what a user's REDCap Project role should be, but only if the role is a managed and known role.
  * If is a CM user has a Custom Role, then the Project admin may assign them to a project manager created role (within REDCap),
  * but not the default defined roles in CM.
  *
  * @since  COmanage Registry v4.4.0
  * @param  string  $userRedcapRoleDetails   User Role mapping
  * @return boolean $updateRedcap            True|False
  *
  */

  protected function assignUserToRoleCalc($userRedcapRoleDetails) {
    $this->log("FUNCTION assignUserToRole");

    $coPersonRoleLabel = $userRedcapRoleDetails['coPersonRoleLabel'];
    $projectRoleName= $userRedcapRoleDetails['currentRoleName'];

    $definedRoles = array();
    $definedRoles = CoRedcapProvisionerTarget::$standardRolesForRedcap;
    $definedRoles['apiadminrole'] = CoRedcapProvisionerTarget::$apiadminrole;

    $updateRedcap = false;

    if ($coPersonRoleLabel != $projectRoleName) {
      $updateRedcap = true;
      //$this->log("FUNCTION assignUserToRole - updateRedcapA: " . print_r($updateRedcap, true));
      $removeRoleIndex = array_search('Custom Role', array_column(array_column($definedRoles, 'roleRights'), 'role_label'));
      $subsetDefinedRoles = $definedRoles;
      unset($subsetDefinedRoles[$removeRoleIndex]);
      $managedRoles = array_column(array_column($subsetDefinedRoles, 'roleRights'), 'role_label');
      $managedRoles[] = null;  //addition of an empty role is necessary to manage users not in a REDCap project role.

      $excludeRole = in_array($projectRoleName, $managedRoles, true);
      //$this->log("excludeRole: " . print_r($excludeRole, true));
      if ( $coPersonRoleLabel == "Custom Role" ) { 
        if ( !($excludeRole) ) {
          $updateRedcap = false;
          //$this->log("FUNCTION assignUserToRole - updateRedcapB: " . print_r($updateRedcap, true));
        }
      }
    }
    //$this->log("FUNCTION assignUserToRole - updateRedcapC: " . print_r($updateRedcap, true));
    return $updateRedcap;
  }

  /*
  * Setup HTTP conection to REDCap.
  *
  * @since   COmanage Registry v4.4.0
  * @param   Array  $coProvisioningTargetData   CO Provisioner Target data
  * @throws  InvalidArgumentException
  *
  */

  protected function createHttpClient($coProvisioningTargetData) {
    $this->log("FUNCTION createHttpClient");
    $args = array();
    $args['conditions']['Server.id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['server_id'];
    $args['conditions']['Server.status'] = SuspendableStatusEnum::Active;
    $args['contain'] = array('HttpServer');
    
    $CoProvisioningTarget = new CoProvisioningTarget();
    $srvr = $CoProvisioningTarget->Co->Server->find('first', $args);
    
    if (empty($srvr)) {
      throw new InvalidArgumentException(_txt('er.redcapprovisioner.notfound'));
      //throw new InvalidArgumentException(_txt('er.redcapprovisioner.notfound', array(_txt('ct.http_servers.1'), $coProvisioningTargetData['CoRedcapProvisionerTarget']['server_id'])));
      //investigate alternate
      return false;
    }

    $this -> Http = new CoHttpClient();
    $this -> Http->setConfig($srvr['HttpServer']);
    $this -> Http->setConfig($srvr['HttpServer']['username'] = null);   // Enforce use of REDCap API token, not username & password - check if it still works without
    $this -> Http->setConfig($srvr['HttpServer']['password'] = null);

    $this -> Http->setRequestOptions(array(
      'header' => array(
        'Accept'        => "application/json"
      )
    ));
    return true;
  }

  /*
  * Create project in REDCap
  *
  * @since   COmanage Registry v4.4.0
  * @param   Array $coProvisioningTargetData  CO Provisioner Target data
  * @param   Array $provisioningData          CO Service Provisioning data
  * @throws  RuntimeException
  *
  * // TODO review $data light of other examples in this code
    // Issue URL: https://github.com/ausaccessfed/RedcapProvisioner/issues/23
    Issue URL: https://github.com/ausaccessfed/RedcapProvisioner/issues/22
  */

  protected function createProject($coProvisioningTargetData, $provisioningData) {
    $this->log("FUNCTION createProject");
    //$this->log("createProject-coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("createProject-provisioningData: " . print_r($provisioningData, true));

    // check COservice object & Provisioning Target are linked by the same group id otherwise skip
    if ($coProvisioningTargetData['CoRedcapProvisionerTarget']['co_group_id'] == $provisioningData['CoService']['co_group_id'] ) {
      $serverDetails = $this -> getRedcapServerDetails($coProvisioningTargetData);
      //$this->log("FUNCTION createProject-serverDetails: " . print_r($serverDetails, true));
    }

    if (!empty($serverDetails)) {
      $superApiToken = $serverDetails['HttpServer']['password'];
      $projectTitle = $provisioningData['CoService']['name'];
      $projectDescription = $provisioningData['CoService']['description'];
      $userName = $serverDetails['HttpServer']['username'];
    } else {
      throw new RuntimeException(_txt('er.redcapprovisioner.notfound'));
      return;
    }

    $record = array(
          'project_title' => $projectTitle,
          'purpose'       => '0',
          'project_notes' => $projectDescription,
    );
    // REDCAp's 'Create Project' API requires a json string encapsulated in square brackets'[' ']'. 
    // This is the only string that the REDCap API is willing to accept.
    $data = "[" . json_encode($record, true) . "]" ;
    // $this->log("data: " . print_r($data, true));

    $fields = array(
      'token'   => $superApiToken,
      'content' => 'project',
      'data'    => $data,
    );
    $response = $this -> readWriteApi($fields);
    $newProjectApiToken = $response['body'];
    //$this->log("FUNCTION createProject-newProjectApiToken: " . print_r($newProjectApiToken, true));

    $projectDetails = $this -> getRedcapProjectDetails($newProjectApiToken); // Get project details for the PID.
    //$this->log("FUNCTION createProject-projectDetails: " . print_r($projectDetails, true));

    $redcapProjectDetails = array();
    $redcapProjectDetails['project_pid'] = $projectDetails['project_id'];
    $redcapProjectDetails['project_api'] = $newProjectApiToken;
    //$this->log("FUNCTION createProject-redcapProjectDetails: " . print_r($redcapProjectDetails, true));
    
    $coRedcapProjectId = $this -> saveProjectApiToken($coProvisioningTargetData, $provisioningData, $redcapProjectDetails);  
    
    if (!empty($coRedcapProjectId)) {
      $this -> addDefaultProjectRoles($newProjectApiToken);
      $this -> assignApiAdmin($userName, $newProjectApiToken);
    }
  }

  /*
  * Remove user from REDCap Projects
  *
  * @since  COmanage Registry v4.4.0
  * @param  Array  $coProvisioningTargetData  CO Provisioner Target data
  * @param  Array  $provisioningData          CO Person[Role] data 
  * @param  String $coPersonId                CO Person id
  * @throw  TODO // InvalidArgumentException  ??
    Issue URL: https://github.com/ausaccessfed/RedcapProvisioner/issues/21
  *
  */

  protected function cleanupUserRoles($coProvisioningTargetData, $coPersonRole, $coPersonId) {
    $this->log("FUNCTION cleanupUserRoles");
    //$this->log("FUNCTION cleanupUserRoles: coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("FUNCTION cleanupUserRoles: provisioningData: " . print_r($provisioningData['CoPersonRole'], true));
    
    $coPersonCouList = array();
    if (!(empty($coPersonRole))) {
      $coPersonCouList = array_column($coPersonRole, "cou_id");
    }
    //$this->log("FUNCTION cleanupUserRoles: coPersonCouList: " . print_r($coPersonCouList, true));
    
    $args = array();
    $args['conditions']['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
    $args['conditions']['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
    $args['conditions']['co_person_id'] = $coPersonId;
    $storedUserRoles = $this -> CoRedcapUsers->find('all', $args);

    //$this->log("FUNCTION cleanupUserRoles: storedUserRoles: " . print_r($storedUserRoles, true));
    
    $storeCoRedcapUsers = array_column($storedUserRoles, "CoRedcapUsers");
    $storeUserCous = array_column($storeCoRedcapUsers, "co_cous_id");

    //$this->log("FUNCTION cleanupUserRoles: storeUserCous: " . print_r($storeUserCous, true));

    $diffList = array_diff($storeUserCous, $coPersonCouList);
    //$this->log("FUNCTION cleanupUserRoles: diffList: " . print_r($diffList, true));
    
    if (!empty($diffList)){
      foreach ($diffList as $removeRole) {
        $key = array_search($removeRole, $storeUserCous);
        //$this->log("FUNCTION cleanupUserRoles: key: " . print_r($key, true));

        $item = $storeCoRedcapUsers[$key];
        //$this->log("FUNCTION cleanupUserRoles: item: " . print_r($item, true));

        $args = array();
        $args['conditions']['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
        $args['conditions']['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
        $args['conditions']['co_cous_id'] = $item['co_cous_id'];
        $args['conditions'][] = 'CoRedcapProjects.project_deleted IS NULL';
        $args['conditions'][] = 'CoRedcapProjects.project_api_token IS NOT NULL';
        $storedProject = $this -> CoRedcapProjects->find('first', $args);

        //$this->log("FUNCTION cleanupUserRoles: storedProject: " . print_r($storedProject, true));
        //$this->log("FUNCTION cleanupUserRoles: storedUserRoles: " . print_r($storedUserRoles[$key], true));

        $response = $this -> removeUserFromProject($storedProject['CoRedcapProjects']['project_api_token'], $storedUserRoles[$key]['CoRedcapUsers']['nif_id']);

        $args = array();
        $args['id'] = $storedUserRoles[$key]['CoRedcapUsers']['id'];
        $args['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
        $args['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
        $args['co_person_id'] = $coPersonId;

        $this -> CoRedcapUsers->delete($args);
          
        if (!($response -> code >= 200 || $response -> code < 299)) {
          // throw error and ask the user to check/confirm REDCap to clean up the user manually.
          $this->log("FUNCTION cleanupUserRoles: storedUserRoles: " . print_r($response, true));
          // Removing the user/role from the CM tracking database needs to happen regardless of the error from REDCap!
          // TODO
        }
      }
    }
  }  

  /*
  * Get Project Roles from REDCap for a project
  *
  * @since   COmanage Registry v4.4.0
  * @param   String  $projectApiToken  Project API Token
  * @return  Array   $userRoles        All of a Project's Roles
  *
  */

  protected function exportProjectRoles($projectApiToken) {
    $this->log("FUNCTION exportProjectRoles");
    
    $fields = array(
      'token'        => $projectApiToken,
      'content'      => 'userRole',
    );
    $userRoles = $this -> readWriteApi($fields);
    return $userRoles;
  }

  /*
  * Get a REDCap user's roles (only for a single project)
  *
  * @since   COmanage Registry v4.4.0
  * @param   String   $username                     REDCap user
  * @param   String   $projectApiToken              Project API Token
  * @returns Array    $currentRedcapUserRole|null   User's project role or null
  *
  */

  protected function exportRedcapUserRoles($username, $projectApiToken) {
    $this->log("FUNCTION exportRedcapUserRoles: " . print_r($username . " - " . $projectApiToken, true));

    $fields = array(
      'token'   => $projectApiToken,
      'content' => 'userRoleMapping',
    );
    
    $response = json_decode($this -> readWriteApi($fields), true);
    //$this->log("FUNCTION exportRedcapUserRoles-response: " . print_r($response, true));

    if (!empty($response)) {
      foreach ($response as $currentRedcapUserRole) {
        if ($currentRedcapUserRole['username'] == strtolower($username)) {
          //$this->log("FUNCTION exportRedcapUserRoles-username+role: " . print_r($currentRedcapUserRole, true));
          return $currentRedcapUserRole['unique_role_name'];
          // Since a user can have ONLY one REDCap role, return the 'unique_role_name' for the first match of username.
        }
      }
    } else {
      //the $response from REDCap shouild never be empty because an admin_api user should always be assigned to the Admin Role, however....
      return;
    }
  }

  /*
  * Get project's details from REDCap
  * @since  COmanage Registry v4.4.0
  * @param  Array  $coProvisioningTargetData  CO Provisioner Target data
  * @param  String $projectApiToken           Project's API Token
  * @return Array  $projectDetails            REDCap Project Details
  */

  protected function getRedcapProjectDetails($projectApiToken) {
    // If a REDCap project has been deleted (ie: in the bin but not completely deleted), REDCap will still respond with a code 200 and details of the project.
    // No clear way to determine if project is available until the project is updated, at which point REDCap returns an error.
    $this->log("FUNCTION getRedcapProjectDetails");
    //$this->log("FUNCTION getRedcapProjectDetails - projectApiToken: " . print_r($projectApiToken, true));
    
    $projectDetails = array();
    $fields = array(
      'token'   => $projectApiToken,
      'content' => 'project',
    );
    $response = $this -> readWriteApi($fields);
    $projectDetails = json_decode($response, true);

    $this->log("FUNCTION getRedcapProjectDetails - response: " . print_r($response, true));
    $this->log("FUNCTION getRedcapProjectDetails - projectDetails: " . print_r($projectDetails, true));
    return $projectDetails;
  }

  /*
  * Get REDCap server details, url and superToken from CM
  @
  * @since   COmanage Registry v4.4.0
  * @param   Array  $coProvisioningTargetData  CO Provisioner Target data
  * @param   Array  $provisioningData          CO Service Provisioning data
  * @return  Array  $srvr                      server details
  * @throws  InvalidArgumentException          if no server details in CM
  *
  */
  
  protected function getRedcapServerDetails($coProvisioningTargetData) {
    $this->log("FUNCTION getRedcapServerDetails");
    //$this->log("FUNCTION getRedcapServerDetails - coProvisioningTargetData" . print_r($coProvisioningTargetData, true));
    $args = array();
    $args['conditions']['Server.id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['server_id'];
    $args['conditions']['Server.status'] = SuspendableStatusEnum::Active;
    $args['contain'] = array('HttpServer');
    $CoProvisioningTarget = new CoProvisioningTarget();
    $srvr = $CoProvisioningTarget->Co->Server->find('first', $args);
    
    if (empty($srvr)) {
      throw new InvalidArgumentException(_txt('er.redcapprovisioner.notfound', array(_txt('ct.http_servers.1'), $coProvisioningTargetData['CoRedcapProvisionerTarget']['server_id'])));
    }
    return $srvr;
  }

  /*
  * Get stored Project details from co_redcap_projects table
  * @since   COmanage Registry v4.4.0
  * @param   Array $coProvisioningTargetData  CO Provisioner Target data
  * @param   Array $provisioningData CO Service Provisioning data
  * @return  Array $srvr server details
  * @throws  // InvalidArgumentException   ??
  *
  */
  /*  
      TODO is this still in use??????
      Issue URL: https://github.com/ausaccessfed/RedcapProvisioner/issues/20
*/
  protected function getStoredProjects($coProvisioningTargetData, $provisioningData) {
    $this->log("FUNCTION getStoredProjects");

    $args = array();
    $args['conditions']['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
    $args['conditions']['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
    $args['conditions'][] = 'CoRedcapProjects.project_deleted IS NULL';
    $args['conditions'][] = 'CoRedcapProjects.project_api_token IS NOT NULL';
    $storedProjects = $this -> CoRedcapProjects->find('all', $args);

    $storedCouIds = array_column(array_column($storedProjects, 'CoRedcapProjects'),'co_cous_id');
    //$this->log("FUNCTION getStoredProjects-storedProjects: " . print_r($storedProjects, true));
    $this->log("FUNCTION getStoredProjects-storedProjects: " . print_r($storedCouIds, true));
    
    return $storedCouIds;
  }

  /*
  * Mark a REDCap project as unmanageable in COmanage table cm_co_redcap_projects
  *
  * @since   COmanage Registry v4.4.0
  * @param   String $projectApiToken  REDCap Project API Token
  * @return  Void|string              VOID if no project; CoRedcapProjects->id if Project marked deleted is successful
  * @throws  RuntimeException
  *
  */

  protected function markProjectDeleted($projectApiToken) {
    $this->log("FUNCTION markProjectDeleted");

    $args = array();
    $args['conditions']['project_api_token'] = $projectApiToken;
    $storedProject = $this -> CoRedcapProjects->find('first', $args);
    
    if(empty($storedProject)) {
      //nothting to do.
      return;
    }
    $tableId = $storedProject['CoRedcapProjects']['id'];

    $args['CoRedcapProjects']['id'] = $tableId;
    $args['CoRedcapProjects']['project_deleted'] = date('Y-m-d H:i:s', time());
    //$this->log("saving ARGS: " . print_r($args, true));
    $this -> CoRedcapProjects->clear();
    $this -> CoRedcapProjects->save($args);

    //$this->log("this: " . print_r($this -> CoRedcapProjects -> id, true));
    if (!empty($this -> CoRedcapProjects->id)) { 
      return $this -> CoRedcapProjects->id;
    } else {
      // If no id, record not saved.
      throw new RuntimeException(_txt('er.redcapprovisioner.token.none'));
    }
  }

  /*
  * Read/Write to REDCap API as a standard function
  *
  * @since  COmanage Registry v4.4.0
  * @param  String  $fields   REDCap API token
  * @return Array   $response Array containing the HTTP response
  * @throws RuntimeException
  *
  */

  protected function readWriteApi($fields) {
    //$this->log("FUNCTION readWriteApi");

    $response = array();
    $fields['format'] = 'json';
    $fields['returnFormat'] = 'json';
    //$this->log("FUNCTION readWriteApi - fields: " . print_r($fields, true));

    $message = http_build_query($fields, "", '&');
    $response = $this -> Http->post("/api/" , $message);
    
    //$this->log("FUNCTION readWriteApi - response code: " . print_r($response -> code, true)); 
    //$this->log("FUNCTION: readWriteApi - response body: " . print_r($response -> body, true)); 

    if ($response -> code < 200 || $response -> code > 299) {
      throw new RuntimeException($response -> reasonPhrase);
    }
    return $response;
  }

  /*
  * Remove user from REDCap Project
  *
  * @since   COmanage Registry v4.4.0
  * @param   String $apiToken  Project API Token
  * @param   String $username  User to remove from project
  * @return  String $response  HTTP reponse
  *
  */

  protected function removeUserFromProject($apiToken, $username) {
    $this->log("FUNCTION removeUserFromProject");

    $data = array();
    $data[] = strtolower($username);

    $fields = array(
      'token'       => $apiToken,
      'content'     => 'user',
      'action'      => 'delete',
      'users'       => $data,
    );
    $this->log("fields: " . print_r($fields, true));
    $response = $this -> readWriteApi($fields);
    return $response;
  }

  /*
  * Save REDCap Project API Token and other details to the co_redcap_projects table
  *
  * @since   COmanage Registry v4.4.0
  * @param   Array        $coProvisioningTargetData  CO Provisioner Target data
  * @param   Array        $provisioningData          CO Service Provisioning data
  * @param   String       $newProjectApiToken        Project API Token
  * @return  string|null  CoRedcapProjects->id       Project Table id if record created
  * @throws  RuntimeException       
  *
  */

  protected function saveProjectApiToken($coProvisioningTargetData, $provisioningData, $redcapProjectDetails) {
    $this->log("FUNCTION saveProjectApiToken");
    //$this->log("FUNCTION saveProjectApiToken-coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("FUNCTION saveProjectApiToken-provisioningData: " . print_r($provisioningData, true));

    $args = array();
    $args['CoRedcapProjects']['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
    $args['CoRedcapProjects']['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
    $args['CoRedcapProjects']['project_api_token'] = $redcapProjectDetails['project_api'];
    $args['CoRedcapProjects']['project_pid'] = $redcapProjectDetails['project_pid'];
    $args['CoRedcapProjects']['co_services_id'] = $provisioningData['CoService']['id'];
    $args['CoRedcapProjects']['co_cous_id'] = $provisioningData['CoService']['cou_id'];

    $this -> CoRedcapProjects->clear();
    $this -> CoRedcapProjects->save($args);

    // $this->log("this: " . print_r($this -> CoRedcapProjects -> id, true));
    if (!empty($this -> CoRedcapProjects->id)) {
      return $this -> CoRedcapProjects->id;
    } else {
      throw new RuntimeException(_txt('er.redcapprovisioner.token.none'));
      return;
    }
  }

  /*
  * Save CoPerson Role mappings to cm_co_redcap_users table.
  *
  * @since  COmanage Registry v4.4.0
  * @param  Array $detailsForSaving   User details and roles
  *
  */

  protected function saveUserDetails($detailsForSaving) {
    $this->log("FUNCTION saveUserDetails - details: " . print_r($detailsForSaving, true));

    $args = array();
    $args['conditions']['CoRedcapUsers.co_provisioning_target_id'] = $detailsForSaving['co_provisioning_target_id'];
    $args['conditions']['CoRedcapUsers.co_id'] = $detailsForSaving['co_id'];
    $args['conditions']['CoRedcapUsers.co_person_id'] = $detailsForSaving['co_person_id'];
    $args['conditions']['CoRedcapUsers.nif_id'] = $detailsForSaving['nif_id'];
    $args['conditions']['CoRedcapUsers.co_services_id'] = $detailsForSaving['co_services_id'];
    $args['conditions']['CoRedcapUsers.co_cous_id'] = $detailsForSaving['co_cous_id'];
    
    ///$this->log("FUNCTION saveUserDetails - args: " . print_r($args, true));

    $userRoles = $this -> CoRedcapUsers->find('first', $args);

    //$this->log("FUNCTION saveUserDetails - FOUND userRoles: " . print_r($userRoles, true));

    if (empty($userRoles)) {
      $args = array();
      $args['CoRedcapUsers']['co_provisioning_target_id'] = $detailsForSaving['co_provisioning_target_id'];
      $args['CoRedcapUsers']['co_id'] = $detailsForSaving['co_id'];
      $args['CoRedcapUsers']['co_person_id'] = $detailsForSaving['co_person_id'];
      $args['CoRedcapUsers']['nif_id'] = $detailsForSaving['nif_id'];
      $args['CoRedcapUsers']['co_services_id'] = $detailsForSaving['co_services_id'];
      $args['CoRedcapUsers']['co_cous_id'] = $detailsForSaving['co_cous_id'];
      $this -> CoRedcapUsers->clear();
      $this -> CoRedcapUsers->save($args);

      //$this->log("CoRedcapUsers->id: " . print_r($this -> CoRedcapUsers->id, true));
      //$this -> CoRedcapUsers->id
    }
  }

  /*
  * Sync CO Person to REDCap
  * @since  COmanage Registry v4.4.0
  * @param  Array $coProvisioningTargetData  CO Provisioner Target data
  * @param  Array $provisioningData          CO Person Provisioning data
  * @throw RuntimeException                  for missing Co Person ID
  * @throw RuntimeException                  projectInfo from CoRedcapProjects table marked deleted
  *
  */

  protected function syncPerson($coProvisioningTargetData, $provisioningData) {
    $this->log("FUNCTION syncPerson");
    //$this->log("FUNCTION syncPerson-coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("FUNCTION syncPerson-provisioningData: " . print_r($provisioningData['CoPersonRole'], true));

    $identifier = null;
    $identifierType = $coProvisioningTargetData['CoRedcapProvisionerTarget']['identifier_type'];
    $ids = Hash::extract($provisioningData['Identifier'], '{n}[type='.$identifierType.']');
    if (empty($ids)) {
      throw new RuntimeException(_txt('er.redcapprovisioner.id.none', array($identifierType)));
    }

    $username = $ids[0]['identifier'];
    //$this->log("NIF identifier: " . print_r($username, true));

    $definedRoles = array();
    $definedRoles = CoRedcapProvisionerTarget::$standardRolesForRedcap;
    $definedRoles['apiadminrole'] = CoRedcapProvisionerTarget::$apiadminrole;
    $extendedType = array_column(CoRedcapProvisionerTarget::$standardRolesForRedcap, 'extendedType');
    //$this->log("FUNCTION syncPerson-extendedType: " . print_r($extendedType, true));

    // include check for role status active
    if (!empty($provisioningData['CoPersonRole'])) {
      foreach ($provisioningData['CoPersonRole'] as $role) {
        // loop through CO Person roles and match defined REDCap roles
        if (in_array($role['affiliation'], $extendedType)) {
          
          $index = array_search($role['affiliation'], array_column($definedRoles, 'extendedType'), true);

          //$this->log("index: " . print_r($index, true));
          $coPersonRoleLabel = $definedRoles[$index]['roleRights']['role_label'];
          //$this->log("FUNCTION syncPerson - Selected ROLE: " . print_r($role['cou_id'] . " + ". $role['Cou']['name'] . " + ". $role['affiliation'] . " + ". $coPersonRoleLabel, true));

          $trackCou[] = $role['cou_id'];

          $args = array();
          $args['conditions']['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
          $args['conditions']['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
          $args['conditions'][] = 'CoRedcapProjects.project_deleted IS NULL';
          //$args['conditions'][] = 'CoRedcapProjects.project_api_token IS NOT NULL';
          $args['conditions']['co_cous_id'] = $role['cou_id'];
          $projectInfo = $this -> CoRedcapProjects->find('first', $args);
          //$this->log("projectInfo from CM: " . print_r($projectInfo, true));
                    
          if (!empty($projectInfo)) {
            if (empty($projectInfo['project_deleted'])) { //project not marked unmanageable in CM

              $projectRedcapRoles = json_decode($this -> exportProjectRoles($projectInfo['CoRedcapProjects']['project_api_token']), true);
              //$this->log("projectRedcapRoles: " . print_r($projectRedcapRoles, true));
              
              $currentRedcapUserRole = $this -> exportRedcapUserRoles($username, $projectInfo['CoRedcapProjects']['project_api_token']);
              //$this->log("currentRedcapUserRole: " . print_r($currentRedcapUserRole, true));

              // calculate CoPerson role details
              $role_label_list = array_column($projectRedcapRoles, 'role_label');   //aka: list of role_name
              $indexCoPerson = array_search($coPersonRoleLabel, $role_label_list);
              $coPersonUniqueRoleName = $projectRedcapRoles[$indexCoPerson]['unique_role_name'];
              //$this->log("role_label_list: " . print_r($role_label_list, true));
              
              $projectRoleName = null;
              if (!empty($currentRedcapUserRole)) {
                // calculate user's REDCap Project role details
                $unique_role_names_list = array_column($projectRedcapRoles, 'unique_role_name');  //unique_role_names_list
                $indexRedcapUser = array_search($currentRedcapUserRole, $unique_role_names_list);  //index for unique_role_names_list
                $projectRoleName = $projectRedcapRoles[$indexRedcapUser]['role_label'];
                //$this->log("unique_role_names_list: " . print_r($unique_role_names_list, true));
              }

              $userRedcapRoleDetails = array(
                'username'               => $username,
                'coPersonRoleLabel'      => $coPersonRoleLabel,
                'coPersonRoleUniqueName' => $coPersonUniqueRoleName,
                'currentRoleName'        => $projectRoleName
              );
              //$this->log("FUNCTION syncPerson - userRedcapRoleDetails: " . print_r($userRedcapRoleDetails, true));

              $detailsForSaving = array(
                'co_provisioning_target_id' => $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'],
                'co_id'                     => $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'],
                'co_person_id'              => $provisioningData['CoPerson']['id'],
                'nif_id'                    => $username,
                'co_services_id'            => $projectInfo['CoRedcapProjects']['co_services_id'],
                'co_cous_id'                => $role['cou_id']
              );
              $this->log("FUNCTION syncPerson - comparison: " . print_r($currentRedcapUserRole . " + " . $coPersonRoleLabel . " + " . $projectRoleName, true));
              
              $updateRedcap = $this -> assignUserToRoleCalc($userRedcapRoleDetails);

              if ($updateRedcap == true) {
                $this->log("FUNCTION syncPerson - UPDATE USER");
                $response = $this -> updateUser($projectInfo['CoRedcapProjects']['project_api_token'], $userRedcapRoleDetails);
                //$this->log("syncPerson response: " . print_r($response, true));
                if ($response -> code >= 200 || $response -> code < 300) {
                  //$this->log("FUNCTION syncPerson - detailsForSaving" . print_r($detailsForSaving, true));
                  $this -> saveUserDetails($detailsForSaving);
                  throw new RuntimeException($response -> reasonPhrase);
                }
              }
            } else {
              $this->log("projectInfo from CoRedcapProjects marked delted!");
              // projectInfo from CoRedcapProjects table marked deleted
              throw new RuntimeException(_txt('er.redcapprovisioner.deleted'));
            }
          }
        }
      }
    }
  }

  /*
  * Sync Project info from COmanage to REDCap
  * @since   COmanage Registry v4.4.0
  * @param   Array $coProvisioningTargetData  CO Provisioner Target data
  * @param   Array $provisioningData          CO Service Provisioning data
  * @return  Void
  * @throws  InvalidArgumentException         Missing or mismatched CoService Grp with Proivsioning Target Group
  *
  **/

  protected function syncProject($coProvisioningTargetData, $provisioningData) {
    $this->log("FUNCTION syncProject");
    //$this->log("coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("provisioningData: " . print_r($provisioningData, true));

    // When manually running a provision of a Service, throw error if Service group does not match the Provisioning Target's Service Group.
    if (!($provisioningData['CoService']['co_group_id'] == $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_group_id'])) {
      throw new InvalidArgumentException(_txt('er.service.group.none'));
      return;
    }

    // CoService conditions necessary for REDCap project creation
    if ( !($provisioningData['CoService']['status'] == SuspendableStatusEnum::Active) ||
         !($provisioningData['CoService']['cou_id']) ||
         !($provisioningData['CoService']['identifier_type'] == $coProvisioningTargetData['CoRedcapProvisionerTarget']['identifier_type']) ) { //||
         //!($provisioningData['CoService']['short_label']) ) {
      return;
    }

    //Find existing stored details
    $args = array();
    $args['conditions']['co_provisioning_target_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_provisioning_target_id'];
    $args['conditions']['co_services_id'] = $provisioningData['CoService']['id'];
    $args['conditions']['co_id'] = $coProvisioningTargetData['CoRedcapProvisionerTarget']['co_id'];
    $storedProject = $this -> CoRedcapProjects->find('first', $args);
    $this->log("FUNCTION syncProject-Details on stored project: " . print_r($storedProject, true));

    if ( !empty($storedProject) && !empty($storedProject['CoRedcapProjects']['project_api_token']) ) {
      //Cannot update a project without a project api token, create a new REDCap Project instead.
      if ($storedProject['CoRedcapProjects']['project_deleted']) {
        throw new InvalidArgumentException(_txt('er.redcapprovisioner.deleted'));
        return;
      }
      // update REDCap project if changed in CM
      $projectApiToken = $storedProject['CoRedcapProjects']['project_api_token'];
      //$this->log("Project was provisioned for COService with id: " . print_r($storedProject['CoRedcapProjects']['co_services_id'], true));
      $this -> updateProject($coProvisioningTargetData, $provisioningData , $projectApiToken);
    } else {
      $this -> createProject($coProvisioningTargetData, $provisioningData);
    }
  }

  /*
  * Update a REDCap project's Name and description (only).
  * No check of roles is performed.
  *
  * @since  COmanage Registry v4.4.0
  * @param  Array $coProvisioningTargetData   CO Provisioner Target data
  * @param  Array $provisioningData           CO Service Provisioning data
  * @param  String $projectApiToken           Project's API Token
  *
  * TODO - check default roles are still present and recreate if missing.
    Issue URL: https://github.com/ausaccessfed/RedcapProvisioner/issues/19
  */
  
  protected function updateProject($coProvisioningTargetData, $provisioningData, $projectApiToken) {
    $this->log("FUNCTION updateProject");
    //$this->log("updateProject-coProvisioningTargetData: " . print_r($coProvisioningTargetData, true));
    //$this->log("updateProject-provisioningData: " . print_r($provisioningData, true));
    
    $cmProjectName = $provisioningData['CoService']['name'];
    $cmProjectDescription = $provisioningData['CoService']['description'];
    
    $projectInfoRedcap = $this -> getRedcapProjectDetails($projectApiToken); //Get current project details - since the project api token is available.
    //$this->log("FUNCTION updateProject-projectInfoRedcap: " . print_r($projectInfoRedcap, true));
    // Any failure to retrieve project details from REDCap can be ignored until there is a failure when updating REDCap.

    if ( $cmProjectName != $projectInfoRedcap['project_title'] || $cmProjectDescription != $projectInfoRedcap['project_notes'] ) {
      $data = array(
        'project_title' => $cmProjectName,
        'project_notes' => $cmProjectDescription,
      );
      $fields = array(
        'token' => $projectApiToken,
        'content' => 'project_settings',
        'data' => json_encode($data),
      );
      $response = $this -> readWriteApi($fields);
      //$this->log("response: " . print_r($response, true));
      if ((str_contains($response -> body, "because it was deleted")) ||   //project deledted but still avialble in REDCap.
        (str_contains($response -> body, "You do not have permissions to use the API")) ) {  //project deledted and NOT avialble in REDCap.
          $this -> markProjectDeleted($projectApiToken);
      }
    }
  }

  /*
  * Update a user in a REDCap project.
  *
  * @since  COmanage Registry v4.4.0
  * @param  string $projectApiToken        Project API token
  * @param  Array  $userRedcapRoleDetails  Username and role details
  * @return array  $response               HTTP response
  *
  */

  protected function updateUser($projectApiToken, $userRedcapRoleDetails) {
    $this->log("FUNCTION updateUser");
    //$this->log("updateUser-project_api_token: " . print_r($projectApiToken, true));
    //$this->log("updateUser-userRedcapRoleDetails: " . print_r($userRedcapRoleDetails, true));
    
    $roleAssignment = array(
      'username'         => $userRedcapRoleDetails['username'],
      'unique_role_name' => $userRedcapRoleDetails['coPersonRoleUniqueName'],
    );
    $data = "[" . json_encode($roleAssignment) . "]";     // REDcap only wants an array type object with one element!
    //$this->log("data: " . print_r($data, true));

    $fields = array(
      'token'       => $projectApiToken,
      'content'     => 'userRoleMapping',
      'action'      => 'import',
      'data'        => $data,
    );
    $response = $this -> readWriteApi($fields);
    return $response;
  }

}
