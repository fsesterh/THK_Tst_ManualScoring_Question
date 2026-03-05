# UserInterfaceHook Plugin - TstManualScoringQuestion

## Table of contents

<!-- TOC -->
* [UserInterfaceHook Plugin - TstManualScoringQuestion](#userinterfacehook-plugin---tstmanualscoringquestion)
  * [Requirements](#requirements)
  * [Description](#description)
  * [Installation](#installation)
  * [Usage](#usage)
  * [Changelog](#changelog)
<!-- TOC -->

## Requirements

| Component | Version(s)                                                                                             | Link                      |
|-----------|--------------------------------------------------------------------------------------------------------|---------------------------|
| PHP       | ![](https://img.shields.io/badge/8.2-blue.svg) ![](https://img.shields.io/badge/8.3-blue.svg)          | [PHP](https://php.net)    |
| ILIAS     | ![](https://img.shields.io/badge/10-orange.svg) ![](https://img.shields.io/badge/10.999-orange.svg) | [ILIAS](https://ilias.de) |

## Description

Adds a second sub-tab for scoring by question. Users are able to score/correct up to 10 answers to a question per page without having to open up each answer first.

## Installation

1. Clone this repository to **public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TstManualScoringQuestion**
2. Install the Composer dependencies
   ```bash
   cd public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TstManualScoringQuestion
   composer install --no-dev
   ```
   Developers **MUST** omit the `--no-dev` argument.
3. Run ``composer install --no-dev`` in the ilias root directory!
4. Login to ILIAS with an administrator account (e.g. root)
5. Select **Plugins** in **Extending ILIAS** inside the **Administration** main menu.
6. Search for the **TstManualScoringQuestion** plugin in the list of plugin and choose **Install** from the **Actions**
   drop-down.
7. Choose **Activate** from the **Actions** drop-down.

## Usage

1. Enter a **Test** object with a user that has permission to manually score answers.
2. Go to the **Manual Scoring** tab.
3. Go to the new **Scoring by Question (Plugin)** subtab.

## Changelog
See [Changelog](CHANGELOG.md)