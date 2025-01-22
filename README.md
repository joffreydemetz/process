# Process

A PHP package for tracking the execution time of processes, sections, and subsections. This package allows you to track and format the time taken for each part of your process, providing a detailed hierarchical view of the execution time.

## Installation

You can install the `jdz/process` package via Composer:

```bash
composer require jdz/process
```

## Usage

### Basic Example

Here is a basic example of how to use the Process class to track the execution time of a process, including sections and subsections. You can find the complete example in the file examples/example.php.

```php 
// autoload
use JDZ\Utils\Process;

$process = Process::singleton();

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
