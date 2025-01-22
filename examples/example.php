<?php
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