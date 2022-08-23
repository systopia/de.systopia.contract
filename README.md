# Contracts

[![CircleCI](https://circleci.com/gh/systopia/de.systopia.contract.svg?style=svg)](https://circleci.com/gh/systopia/de.systopia.contract)

## Downloading contract files

The membership_contract custom field in membership_general custom group can hold
a contract reference. This contract reference is matched to a file located in:
`sites/default/files/civicrm/custom/contracts/{reference}.tif`

If the file does not exist, it will not be available for download and the
contract reference will not be shown as a link.

## Configuration

You must create a directory or symlink in CiviCRM customFilesUploadDir
"contracts". eg. `sites/default/files/civicrm/custom/contracts` for Drupal
environments.


## Customisation

To allow customisation to your organisation, the contract extension currently offers
the following Symfony events:

| event name                                       | purpose                                                                                                                                   | since version |
|--------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| ``de.contract.rapidcreateform``                  | Supply an optimised "rapid create" form, that let's you efficiently register new members, i.e. creates a new contact and a new membership | 1.4           |
| ``de.contract.renderchangedisplay``              | Render custom titles for displaying change activities in the review section of the contracts                                              | 1.4           |
| ``de.contract.renderchangesubject``              | Render custom subject lines for the contract change activities                                                                            | 1.4           |
| ``de.contract.eligible_campaigns``               | Define the eligible campaigns for a contract                                                                                              | 1.4           |
| ``de.contract.suppress_system_activity_types``   | Change the list of (automatically generated) acitivies to be suppressed                                                                   | 1.4           |

more to come...