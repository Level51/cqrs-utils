# Change log
All notable changes to this project will be documented in this file.

## [Unreleased]
### Added
- Base algorithm for handling manifest entries
- Config over Code implementation
- Functionality for checking if read and write databases are in sync
- read() method in Redis handler
- host, port and default_db for Redis handler maintainable via config api
- Handler for elasticsearch

### Fixed
- Error Collection logic for nested structures