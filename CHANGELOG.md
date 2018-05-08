# Changelog
All Notable changes to `flipboxdigital\craft-sortable-associations` will be documented in this file

## 1.0.1 - 2018-05-07
### Fixed
- Saving an association record may trigger the save operation twice.

### Added
- SortableAssociations service allows the 'sortOrder' attribute to defined via a constant

## 1.0.0 - 2018-04-23
### Changed
- `SortableAssociations` service no longer uses a constant to identify the record alias.
- `SortableFields` service no longer uses a constant to identify the record alias.

## 1.0.0-rc.1 - 2018-03-23
### Changed
- `SortableAssociation` record must implement an association service.

## 1.0.0-rc
- Initial release!
