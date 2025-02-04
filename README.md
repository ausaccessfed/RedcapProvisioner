RedcapProvisioner
# COmanage Plugin for REDCap User and Project Provisioning

## Description: COmanage Plugin - RedcapProvisioner

The **RedcapProvisioner** is a COmanage Plugin https://incommon.org/software/comanage.

This Plugin manages projects, users and their project roles on the REDCap platform https://www.REDCap.org.

This plugin provisions users to REDCap. Only user deprovisioning from REDCap projects is supported. A user is removed from a REDCap project when
- a user's status changes (active/suspended) 
- a user's role changes in COmanage (active/suspended)
- a user is removed from a REDCap COmanage role.

This plugin provisions projects and several default roles to REDCap, but does not delete projects if the related COmanage objects are removed. Project deletion in REDCap is left to the REDCap administrator to achieve using other methods.

This plugin manages users' assignment to projects via preefined REDcap project roles. The current predefined roles are:
- REDCap Project Admin
- REDCap Statistician
- REDCap Custom Role.
This is achieved by assigning a role to a CO Person via the customised COmanage Extended Types Attributes affiliation.

## Configuring This Plugin
_and associated COmanage objects_
### COmanage Extended Types
- update the COmanage Extended Types Attributes with the following additions.
- select **For Attribute** of type **Affiliation (CO Person Role)** and _FILTER_ for the *Affiliation* attribute list. 
- Add three **Extended Type Attributes** with the following _"Name"/"Display Name"_ pair values:
    - redcap-admin/REDCap Project Admin
    - redcap-statistician/REDCap Statistician
    - redcap-customrole/REDCap Custom Role

This Plugin uses the string values _redcap-admin_, _redcap-statistician_ and _redcap-customrole_. This list can be modified/extended by updating the COmanage **Affiliation** configuration and updating the static declaration $standardRolesForRedcap in _Model/CoRedcapProvisionerTarget.php_ with and associated _extendedType_ and _roleRights_ arrays.

### COmanage Server
- for each REDCap Server, add a COmanage Server object with the following configuration options:
    - Type: HTTP 
    - HTTP Authentication Type: Basic
    - Supply a Username (optional) and a local REDCap user's Super Token API with REDCap admin rights and API access.

## COmanage Regular Group
- Create or use a regular group to link a COmanage Server object to a REDCap COmanage Provisioning Target.

### COmanage Provisioning Targets
Thought it is possible to have more than REDCap Provisioner Plugin per REDCap server, it is strongly not adviseable. Each REDCap server should have a single REDCap Provisioner Target. 
- for each REDCap Server, add a _Provisioning Target_ with the following configuration options:
    - Plugin: REDCap
    - Target REDCap Server: select from the list of servers configured in *COmanage Server* configuration
    - CoPerson Identifier Type: to use as the REDCap primary user identifier (this should be a unique and persistent attribute for users - email address is not suitable because email addresses change)
    - Services linking group: Services (as projects) assigned to this group are provisioned by this Provisioning Target.

### COmanage Services Objects
- Add a COmanage Services Object with the following configuration options:
    - Name: this will be the REDCap *Project Title*
    - Description: A description of the REDCap project (optional)
    - COU: Select a COU - this will provision those users assocaited to this COU (via _Role Attributes_) to the REDCap Project
    - Service Group: Select a group that matches the *COmanage Provisioning Targets* _Services linking group_. 

### COmanage CO Person Objects
- COmanage CO Person objects are assigned roles to be provisioned to REDCap and to also be assigned to REDCap Projects
- Select a CO Person and for **Role Attributes** add a new role with the following configuration:
    - Select a child COU that contains **REDCap** in the name
    - Update **Affiliation** and select one of the pre-defined _affiliations_ from this list:
        - REDCap Custom Role
        - REDCap Statistician
        - REDCap Admin

## Versions
This COmanage Plugin has been tested with the following platform versions:
- COmanage: v4.3.3, v4.3.4, v4.4.0
- REDCap: version 14.3.x
 
