# UIHook Plugin - TstManualScoringQuestion

* PHP: [![Minimum PHP Version](https://img.shields.io/badge/Minimum_PHP-7.2.x-blue.svg)](https://php.net/) [![Maximum PHP Version](https://img.shields.io/badge/Maximum_PHP-7.4.x-blue.svg)](https://php.net/)

* ILIAS: [![Minimum ILIAS Version](https://img.shields.io/badge/Minimum_ILIAS-5.4-orange.svg)](https://ilias.de/) [![Maximum ILIAS Version](https://img.shields.io/badge/Maximum_ILIAS-6.x-orange.svg)](https://ilias.de/)

---

## Description

Adds a second sub-tab for scoring by question. Users are able to score/correct up to 10 answers to a question per page without having to open up each answer first.

It is highly recommended to rename the original scoring by question tab via the ILIAS language files to something different in order to avoid having to sub-tabs with the same name.

---

## Installation

1. Clone this repository to **Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TstManualScoringQuestion**
2. Install the Composer dependencies  
   ```bash
   cd Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TstManualScoringQuestion
   composer install --no-dev
   ```
   Developers **MUST** omit the `--no-dev` argument.


3. Login to ILIAS with an administrator account (e.g. root)
4. Select **Plugins** in **Extending ILIAS** inside the **Administration** main menu.
5. Search for the **TstManualScoringQuestion** plugin in the list of plugin and choose **Install** from the **Actions** drop down.
6. Choose **Activate** from the **Actions** dropdown.

## Usage

1. Enter a **Test** object with a user that has permission to manually score answers.
2. Go to the **Manual Scoring** Tab.

If answers are available they will be shown in the new design.

