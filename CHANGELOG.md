# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [3.1.2] - 2026-02-08

### Fixed

- Update README.

## [3.1.1] - 2026-02-08

### Fixed

- Do not auto-apply filters without specified column.

## [3.1.0] - 2026-02-07

_Stable release based on [3.1.0-rc.1]._

## [3.1.0-rc.1] - 2026-02-07

### Changed

- Add primary search box with configurable searchable columns.
- Show filters in overlay hidden by default.

## [3.0.9] - 2026-02-07

### Changed

- Add column and relation filter config keys and auto apply filters.

## [3.0.8] - 2026-02-07

### Fixed

- Fix register unaccent macro.

## [3.0.7] - 2026-02-07

### Fixed

- Do not show option filter with only one option.

## [3.0.6] - 2026-02-07

### Fixed

- URL filters clears other filters.

## [3.0.5] - 2026-02-07

### Fixed

- Rename filter buttons.

## [3.0.4] - 2026-02-07

### Fixed

- If search value contains accented characters, do an exact (accent-sensitive) match.

## [3.0.3] - 2026-02-07

### Added

- Add user per-page settings.
- `whereLikeUnaccented` query macro.

### Fixed

- Fix pagination number format.
- Design filters to be mobile-first.

## [3.0.2] - 2026-02-04

### Fixed

- Fix broken html structure.

## [3.0.1] - 2026-02-04

### Fixed

- Make some UI fixes.

## [3.0.0] - 2026-02-04

_Stable release based on [3.0.0-rc.1]._

## [3.0.0-rc.1] - 2026-02-04

### Changed

- Do not fetch all data from database at once use pagination, filtering and sorting on database level.
- Method from `model` attributte must return query builder instead of collection.

### Removed

- Remove default frontend filters.

## [2.0.6] - 2026-01-25

### Fixed

- Fix pushing workflow badges.

## [2.0.5] - 2026-01-25

### Fixed

- Fix github workflow is sometimes canceled.

## [2.0.4] - 2026-01-25

### Fixed

- Fix github workflow concurrecy.

## [2.0.3] - 2026-01-25

### Fixed

- Fix github workflow.

## [2.0.2] - 2026-01-25

### Changed

- Update license to CC BY-NC-SA 4.0.

## [2.0.1] - 2025-12-04

### Fixed

- Fix deprecated mb_convert_encoding.

## [2.0.0] - 2025-12-02

_Stable release based on [2.0.0-rc.1]._

## [2.0.0-rc.1] - 2025-12-02

### Changed

- Update laravel-common to v4.
- Update PHP requirement to ^8.4.

## [1.4.1] - 2025-11-14

### Changed

- Support laravel-common v3.

## [1.4.0] - 2025-08-25

_Stable release based on [1.4.0-rc.1]._

## [1.4.0-rc.1] - 2025-08-25

### Added

- Add danish translation.

## [1.3.0] - 2025-06-30

_Stable release based on [1.3.0-rc.1]._

## [1.3.0-rc.1] - 2025-06-30

### Changed

- Generate semantic export name.

## [1.2.0] - 2025-06-20

_Stable release based on [1.2.0-rc.1]._

## [1.2.0-rc.1] - 2025-06-20

## [1.1.2] - 2025-06-11

### Fixed

- Fix formatting composite keys.

## [1.1.1] - 2025-05-15

### Fixed

- Fix fuzzy match highlight.

## [1.1.0] - 2025-05-15

_Stable release based on [1.1.0-rc.1]._

## [1.1.0-rc.1] - 2025-05-15

### Changed

- Design empty cell background as empty string.
- Trim "fuzzy match" filter.

## [1.0.3] - 2025-05-15

### Fixed

- Support `laravel-common:^2`.

## [1.0.2] - 2025-05-14

### Fixed

- Export stripped values.

## [1.0.1] - 2025-05-14

### Fixed

- Export itemValue instead of stval.

## [1.0.0] - 2025-05-09

_Stable release based on [1.0.0-rc.1]._

## [1.0.0-rc.1] - 2025-05-09

### Changed

- Update `laravel-common` version to `^1`.

## [0.12.2] - 2025-04-25

### Fixed

- Fix empty data.

## [0.12.1] - 2025-04-25

### Fixed

- Change laravel-common version to `^0`.

## [0.12.0] - 2025-04-17

_Stable release based on [0.12.0-rc.1]._

## [0.12.0-rc.1] - 2025-04-17

### Changed

- Update laravel-common version to `^0.13`.

## [0.11.0] - 2025-04-16

_Stable release based on [0.11.0-rc.1]._

## [0.11.0-rc.1] - 2025-04-16

### Changed

- Update laravel-common version to `^0.12`.

## [0.10.0] - 2025-04-16

_Stable release based on [0.10.0-rc.1]._

## [0.10.0-rc.1] - 2025-04-16

### Changed

- Add quotation marks to support exact match in filter instead of fuzzy ansii match.
- Visualize empty cells with css instead of fallback to dash.

## [0.9.0] - 2025-04-16

_Stable release based on [0.9.0-rc.1]._

## [0.9.0-rc.1] - 2025-04-16

### Changed

- Update laravel-common version to `^0.11`.

## [0.8.6] - 2025-04-16

### Fixed

- Fix inactive sort arrow color.

## [0.8.5] - 2025-04-14

### Fixed

- Allow filter to dash (empty data).

## [0.8.4] - 2025-04-14

### Fixed

- Fix changing filter resets pagination.

## [0.8.3] - 2025-04-11

### Fixed

- Update laravel-common version to `^0.10`.

## [0.8.2] - 2025-04-09

### Fixed

- Update laravel-common version to `^0.9`.

## [0.8.1] - 2025-04-09

### Fixed

- Fix header cell has visible overflow.

## [0.8.0] - 2025-04-09

_Stable release based on [0.8.0-rc.1]._

## [0.8.0-rc.1] - 2025-04-09

### Added

- Set max height to table cell to 3 rows with ellipsis.
- Add `column-widths` livewire attribute to allow setting column widths.

### Changed

- Reimplement HTML table to CSS grid.

## [0.7.0] - 2025-04-07

_Stable release based on [0.7.0-rc.1]._

## [0.7.0-rc.1] - 2025-04-07

- Update laravel-common to `^0.8`.

## [0.6.5] - 2025-04-01

### Changed

- Update laravel-common to `^0.7`.

## [0.6.4] - 2025-03-14

### Fixed

- Fix filter button styles.

## [0.6.3] - 2025-03-14

### Changed

- Update laravel-common version to `0.6`.

## [0.6.3] - 2025-03-14

## [0.6.2] - 2025-03-13

### Changed

- Update laravel common version to `0.5`.

## [0.6.1] - 2025-03-13

### Fixed

- Make filter button more compact.

## [0.6.0] - 2025-03-04

_Stable release based on [0.6.0-rc.1]._

## [0.6.0-rc.1] - 2025-03-04

### Changed

- Do not run `->get()` when custom function is provided.

## [0.5.5] - 2025-03-03

### Fixed

- Fix sort and allow click only to sort ico.

## [0.5.4] - 2025-03-03

### Fixed

- Fix sort with default sort.

## [0.5.3] - 2025-03-03

### Fixed

- Fix filter select and styles.

## [0.5.2] - 2025-02-28

### Fixed

- Fix table view for empty data.

## [0.5.1] - 2025-02-27

### Fixed

- Fix styles for small screens.

## [0.5.0] - 2025-02-27

_Stable release based on [0.5.0-rc.1]._

## [0.5.0-rc.1] - 2025-02-27

### Added

- Allow to select filter column.

## [0.4.8] - 2025-02-26

### Fixed

- Fix sort with ascents.

## [0.4.7] - 2025-02-26

### Fixed

- Fix indicate only primary sort.

## [0.4.6] - 2025-02-26

### Fixed

- Fix indicate only primary sort.

## [0.4.5] - 2025-02-26

### Fixed

- Indicate only primary sort.

## [0.4.4] - 2025-02-25

### Fixed

- Fix custom sort comparator functions.

## [0.4.3] - 2025-02-25

### Fixed

- Support sort comparator function.

## [0.4.2] - 2025-02-24

### Fixed

- Fix missing filter highlight.

## [0.4.1] - 2025-02-24

### Fixed

- Fix base view.

## [0.4.0] - 2025-02-24

_Stable release based on [0.4.0-rc.1]._

## [0.4.0-rc.1] - 2025-02-24

### Added

- Allow multiple default sort entries applied after user sort.

### Changed

- Merge `deafultSortBy` and `defaultSortDirection` into `defaultSort` array attribute.

## [0.3.0] - 2025-02-23

_Stable release based on [0.3.0-rc.1]._

## [0.3.0-rc.1] - 2025-02-23

### Changed

- Refactor database filter, sort and pagination to PHP level.

## [0.2.3] - 2025-02-23

### Fixed

- Fix table extra margin.

## [0.2.2] - 2025-02-19

### Fixed

- Add missing fullscreen button styles, update look.

## [0.2.1] - 2025-02-19

### Fixed

- Fix laravel-common require version.

## [0.2.0] - 2025-02-19

_Stable release based on [0.2.0-rc.1]._

## [0.2.0-rc.1] - 2025-02-19

## [0.1.0] - 2024-10-21

_Stable release based on [0.1.0-rc.1]._

## [0.1.0-rc.1] - 2024-10-21

## [0.0.0] - 2024-10-21

### Added

- New changelog file.

[3.1.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.1.1...v3.1.2
[3.1.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.1.0...v3.1.1
[3.1.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.9...v3.1.0
[3.1.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v3.0.9
[3.0.9]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.8...v3.0.9
[3.0.8]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.7...v3.0.8
[3.0.7]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.6...v3.0.7
[3.0.6]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.5...v3.0.6
[3.0.5]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.4...v3.0.5
[3.0.4]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.3...v3.0.4
[3.0.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.2...v3.0.3
[3.0.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.1...v3.0.2
[3.0.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v3.0.0...v3.0.1
[3.0.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.6...v3.0.0
[3.0.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v2.0.6
[2.0.6]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.5...v2.0.6
[2.0.5]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.4...v2.0.5
[2.0.4]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.3...v2.0.4
[2.0.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.2...v2.0.3
[2.0.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.1...v2.0.2
[2.0.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v2.0.0...v2.0.1
[2.0.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.4.1...v2.0.0
[2.0.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v1.4.1
[1.4.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.4.0...v1.4.1
[1.4.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.3.0...v1.4.0
[1.4.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v1.3.0
[1.3.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.2.0...v1.3.0
[1.3.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v1.2.0
[1.2.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.1.2...v1.2.0
[1.2.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v1.1.2
[1.1.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.1.1...v1.1.2
[1.1.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.1.0...v1.1.1
[1.1.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.0.3...v1.1.0
[1.1.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v1.0.3
[1.0.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.0.2...v1.0.3
[1.0.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.0.1...v1.0.2
[1.0.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v1.0.0...v1.0.1
[1.0.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.12.2...v1.0.0
[1.0.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.12.2
[0.12.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.12.1...v0.12.2
[0.12.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.12.0...v0.12.1
[0.12.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.11.0...v0.12.0
[0.12.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.11.0
[0.11.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.10.0...v0.11.0
[0.11.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.10.0
[0.10.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.9.0...v0.10.0
[0.10.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.9.0
[0.9.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.6...v0.9.0
[0.9.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.8.6
[0.8.6]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.5...v0.8.6
[0.8.5]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.4...v0.8.5
[0.8.4]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.3...v0.8.4
[0.8.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.2...v0.8.3
[0.8.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.1...v0.8.2
[0.8.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.8.0...v0.8.1
[0.8.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.7.0...v0.8.0
[0.8.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.7.0
[0.7.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.6.5...v0.7.0
[0.7.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.6.5
[0.6.5]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.6.4...v0.6.5
[0.6.4]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.6.3...v0.6.4
[0.6.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.6.2...v0.6.3
[0.6.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.6.1...v0.6.2
[0.6.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.6.0...v0.6.1
[0.6.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.5.5...v0.6.0
[0.6.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.5.5
[0.5.5]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.5.4...v0.5.5
[0.5.4]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.5.3...v0.5.4
[0.5.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.5.2...v0.5.3
[0.5.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.5.1...v0.5.2
[0.5.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.5.0...v0.5.1
[0.5.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.8...v0.5.0
[0.5.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.4.8
[0.4.8]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.7...v0.4.8
[0.4.7]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.6...v0.4.7
[0.4.6]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.5...v0.4.6
[0.4.5]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.4...v0.4.5
[0.4.4]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.3...v0.4.4
[0.4.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.2...v0.4.3
[0.4.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.1...v0.4.2
[0.4.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.4.0...v0.4.1
[0.4.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.3.0...v0.4.0
[0.4.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.3.0
[0.3.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.2.3...v0.3.0
[0.3.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.2.3
[0.2.3]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.2.2...v0.2.3
[0.2.2]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.2.1...v0.2.2
[0.2.1]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.2.0...v0.2.1
[0.2.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.1.0...v0.2.0
[0.2.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.1.0
[0.1.0]: https://https://github.com/internetguru/laravel-model-browser/compare/v0.0.0...v0.1.0
[0.1.0-rc.1]: https://github.com/internetguru/laravel-model-browser/releases/tag/v0.0.0
[0.0.0]: git log v0.0.0
