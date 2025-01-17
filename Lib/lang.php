<?php
/**
 * COmanage Registry API Provisioner Plugin Language File
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
 * @since         COmanage Registry v4.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
  
global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_redcap_provisioner_texts['en_US'] = array(
  // Titles, per-controller
  'ct.co_redcap_provisioner_targets.1'  => 'REDCap Provisioner Target',
  'ct.co_redcap_provisioner_targets.pl' => 'REDCap Provisioner Targets',
  
  // Error messages
  'er.redcapprovisioner.id.none'      => 'No identifier of type %1$s found for CO Person',
  'er.coservier.id.none'              => 'No id found for this service',
  'er.service.group.none'             => 'This provisioner is not assigned to this service/project',
  'er.coperson.group.none'            => 'This provisioner is not assigned to this person',
  'er.redcapprovisioner.notfound'     => 'REDCap Server Not found',
  'er.redcapprovisioner.token.none'   => 'Project API Token not saved',
  'er.redcapprovisioner.deleted'      => 'Project deleted on REDCap Server, no longer available',
  
  // Plugin texts
  'pl.redcapprovisioner.co_group_id'                 => 'Services linking group',
  'pl.redcapprovisioner.co_group_id.desc'            => 'Services assigned to this group are provisioned by this Provisioning Target.',
  'pl.redcapprovisioner.identifier_type'             => 'CoPerson Identifier Type',
  'pl.redcapprovisioner.identifier_type.desc'        => 'The CO Person Identifier of this type will be used for the REDCap username. Chose carefully as REDCap usernames cannot be changed or updated after provisioning to REDCap!',
  'pl.redcapprovisioner.server'                      => 'Target REDCap Server',
  'pl.redcapprovisioner.server.desc'                 => 'Select a target REDCap Server.',
  'pl.redcapprovisioner.redcap_username_prefix'        => 'REDCap username prefix',
  'pl.redcapprovisioner.redcap_username_prefix.desc'   => 'This prefix is appended to the CILogon identifier used for REDCap usernames. <br>Max 6 characters, can be empty.',
  'pl.redcapprovisioner.usage'                       => 'REDCap Provisioner Plugin requirements: v0.0.3 - features',
  'pl.redcapprovisioner.usage.desc'                  => 'Service Config conditions necessary for REDCap project creation: <br>
                                                      [Service][status] == Active <br>
                                                      [Service][cou_id] NOT empty <br>
                                                      [Service][co_group_id] matches the Provisioning Target [co_group_id] <br>
                                                      [Service][short_label] NOT empty'

    //'pl.redcapprovisioner.mode'                        => 'Protocol Mode',  
);
