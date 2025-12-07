# Process

A PHP package for tracking the execution time of processes, sections, and subsections. This package allows you to track and format the time taken for each part of your process, providing a detailed hierarchical view of the execution time.

## Features

- Track execution time of processes, sections, and subsections
- Hierarchical structure for sections and subsections
- Formatted time output
- Easy to use API

## Requirements

- PHP 8.1 or higher

## Installation

Install via Composer:

```bash
composer require jdz/process
```

## Quick Start

### Basic Example

Here is a basic example of how to use the Process class to track the execution time of a process, including sections and subsections. You can find the complete example in the file examples/example.php.

```php 
use JDZ\Utils\Process;

$process = Process::create();

// Start the main process
$process->startSection("Main Process");

// Start a subsection
$process->startSubsection("Loading Data");
// Simulate loading data with a delay
sleep(1);
$process->endSubsection();

// Start another subsection
$process->startSubsection("Processing Data");
// Simulate processing with a delay
sleep(2);
$process->endSubsection();

// End the main section
$process->endSection();

// Get the formatted time for the entire process
echo $process->getTime(); // Example output: "3 min 3 s 234 ms"

// Export the sections and subsections in a hierarchical format
print_r($process->toArray());
```

### Example Output

When calling toArray(), it will return an array representing the process, sections, and subsections:

```lua 
Total exec time : 3 min 3 s 234 ms
---- Main Process : 3 min 3 s 234 ms
|-- Loading Data : 1 s 234 ms
|-- Processing Data : 2 min 0 s 0 ms
```

## Methods Overview

- **`startSection(string $name)`**: Start a new section with the given name.
- **`startSubsection(string $name, bool $endPrevious = true)`**: Start a new subsection within the current section.
- **`endSection()`**: End the current section and close all its subsections.
- **`endSubsection()`**: End the current subsection and return to the parent section.
- **`close()`**: End the process and calculate the total execution time.
- **`getTime()`**: Get the formatted time for the entire process.
- **`toArray()`**: Export the sections and their execution times as an array of formatted strings.

## Time Format

The time is formatted as follows:

- **Minutes**: Only shown if greater than 0.
- **Seconds**: Only shown if greater than 0 or if minutes are shown.
- **Milliseconds**: Always shown if non-zero, or if minutes or seconds are shown.

Examples of formatted time:

- `3 min 3 s 234 ms`
- `2 s 150 ms`
- `0 ms`

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
composer test -- --coverage-html coverage

# Run specific test file
vendor/bin/phpunit tests/ProcessItemTest.php
vendor/bin/phpunit tests/ProcessTest.php

# Run with detailed output
vendor/bin/phpunit --testdox
```

- **ProcessItemTest**: 28 tests covering all aspects of the ProcessItem class, including:
  - Construction and initialization
  - Parent-child relationships
  - Time tracking and closure
  - Hierarchical export functionality
  - Time formatting

- **ProcessTest**: 25 tests covering all aspects of the Process class, including:
  - Singleton pattern
  - Section and subsection management
  - Time tracking and formatting
  - Complex nested workflows
  - Idempotent operations

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Changelog

### Version 2.0.0
- Removed deprecated singleton() method in favor of create() method.
- Added Unit tests
- Improved time formatting to handle zero durations correctly.

### Version 1.0
- Initial release with core functionality for tracking process execution time, sections, and subsections.
