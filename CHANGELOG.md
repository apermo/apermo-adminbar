# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0
### Changed
- Renamed hook "apermo-adminbar-statusses" to "apermo_adminbar_statuses"
- Renamed hook "apermo-adminbar-statusbox-entries" to "apermo_adminbar_statusbox_entries"
- Renamed hook "apermo-adminbar-caps" to "apermo_adminbar_caps"
- Renamed hook "apermo-adminbar-watermark" to "apermo_adminbar_watermark"
- Renamed hook "apermo-adminbar-statusbox" to "apermo_adminbar_statusbox"
- Renamed hook "apermo-adminbar-keycodes" to "apermo_adminbar_keycodes"
- Renamed hook "apermo-adminbar-types" to "apermo_adminbar_types"
- Renamed hook "apermo-adminbar-sites" to "apermo_adminbar_sites"
- Renamed hook "apermo-adminbar-colors" to "apermo_adminbar_colors"
- Reworked file structure

### Fixed
- PHP Notice for `$dmtable` in `$wpdb`

## [1.1.2] - 2016-12-14
### Changed
- Keyboard shortcuts had to be changed as they collided with windows standards
- Hide Watermark: Mac: CMD + CTRL + W - Win/Linux: ALT + SHIFT + W
- Hide Adminbar: Mac: CMD + CTRL + A - Win/Linux: ALT + SHIFT + A

### Fixed
- Backend color scheme was overwritten if being set by a user prior to plugin activation

## [1.1.1] - 2016-12-07
### Changed
- Made status icons bigger & clearer, changed color for scheduled status

### Fixed
- Keyboard shortcut for watermark is now CTRL + D

## 1.1.0
* added: keyboard shortcut CTRL + E to hide/show the adminbar in frontend
* added: watermark for draft/scheduled post, to remind an editor of the current post status
* added: keyboard shortcut CTRL + W to hide/show the watermark
* added: the statusbox, a box containing useful information about the post, directly visible in the frontend

## 1.0.0
* fixed: do not add a spacer if no quicklinks are added
* added: option to hide stages by whitelisting the stage for given user ids
* added: option to set the default capability needed to use the quicklinks

## 0.9.11 - 2016-10-28
* fixed: css from admin_bar was loaded late, so &gt;a&lt; tags mostly where miscolored.

## 0.9.10 - 2016-10-26
* disable all options if filter is used
* fixed: robots.txt defaults were ignored

## 0.9.9 - 2016-10-22
* added support for multisite domain mapping
* added support for robots.txt

## 0.9.6
* fixed typos

## 0.9.5 - 2016-06-30
* fixed bug for subfolder installations

## 0.9.4 - 2016-06-30
* added an export and import option
* minor improvements

## 0.9.3 - 2016-06-29
* Removed Scheme URL from saved options
* added filter 'apermo-adminbar-sites' to give the option of saving the settings in a theme

## [0.9.2] - 2016-06-29
* Some minor code improvements

## [0.9.1] - 2016-06-28
* Bug fixes and optimizations - Thanks to @kau-boy for the help

## [0.9.0] - 2016-06-28
* Initial Release

[1.1.2]: https://github.com/apermo/apermo-adminbar/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/apermo/apermo-adminbar/compare/v1.1.0...v1.1.1
[0.9.2]: https://github.com/apermo/apermo-adminbar/compare/v0.9.1...v0.9.2
[0.9.1]: https://github.com/apermo/apermo-adminbar/compare/v0.9.0...v0.9.1
[0.9.0]: https://github.com/apermo/apermo-adminbar/tree/v0.9.0
