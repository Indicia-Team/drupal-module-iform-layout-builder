# IForm layout builder

# Overview

This module allows custom data entry forms for Indicia wildlife recording to be built using the
Layout Builder functionality introduced in Drupal 8. This provides a simpler way for editors to
build custom survey forms than the existing customisable dynamic forms supported by the IForm
module.

As well as being entirely driven by the Drupal Layout Builder user interface, a key difference with
other methods of configuring Indicia recording forms is the server-side configuration of the survey
dataset and all custom attributes is all automated so there is no need to log into the warehouse
when creating forms.

## Installation

Before proceeding with the installation of the Drupal module, ensure:
* the `rest_api` module is installed and configured with at least the `jwtUser` authentication
  method available.
* the `scratchpad` module is installed as this is used to configure custom species checklists for
  recording against.

Install the IForm module in Drupal as usual then configure it to connect to your website
registration on the warehouse. Ensure that you've selected a master checklist on the configuration
pointing to a warehouse species list containing all available taxa. Then install the Indicia Layout
Builder module.

Ensure that your user profile has the **First name** and **Last name** fields filled in so your
account is linked to the warehouse correctly. You will need site editor rights on the warehouse.

Set up the Drupal private file system (file_private_path in settings.php). See
https://www.drupal.org/docs/8/modules/skilling/installation/set-up-a-private-file-path.

The Indicia Layout Builder module uses an authentication standard called Java Web Tokens to connect
to the Indicia warehouse. It depends on a pair of encryption keys - one private which can be used
to sign a message proving it came from your Drupal website and a public key which can be used to
check a signed message is from who it claims to be from. There are several methods of creating an
RSA private/public key pair, here are a couple of methods that have been tested with the Indicia
Layout Builder:

```bash
$ openssl genrsa -des3 -out private.key 2048
$ openssl rsa -in private.key -pubout > public.key
```
Or on Windows from Git bash:
```bash
winpty openssl genpkey -algorithm RSA -out private.key -pkeyopt rsa_keygen_bits:2048
winpty openssl rsa -pubout -in private.key -out rsa_public.pem
```

This will create 2 files, private.key and rsa_public.key. Save private.key in the Drupal
private file system folder you created earlier.

The contents of rsa_public.key needs to be saved into the website registration on the warehouse.

Also on the website registration on the warehouse, ensure that the website URL is set correctly,
e.g. https://www.example.com/.

## Getting Started

Once installed:

Before proceeding, please familiarise yourself with the Drupal Layout Builder module, added in
Drupal 8.5: https://www.drupal.org/docs/8/core/modules/layout-builder/layout-builder-overview.

* Click Content > Add Content > Indicia layout builder form.
* Enter a title for your form and ensure Survey Dataset is set to "-Create a new survey dataset-".
* Set form type to "Enter a single record".
* Click "Save". You should now have a basic recording form but with no method of inputting a
  species. Click the Layout tab to change the form.
* Click "Add block" in the section you would like to add the species input control to and in the
  "Choose a block" sidebar that appears, select "Single species". Save the block.
* Save the layout and you now have a very basic recording form.

Custom list of species can be configured using the scratchpad editing prebuilt forms.