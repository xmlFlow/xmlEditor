<?php
/**
 * Test script for JATS Converter
 * Usage: php test_converter.php <input.xml>
 */

require_once __DIR__ . '/vendor/autoload.php';

use Withanage\JatsConverter\Factory\JatsConverterFactory;

if ($argc < 2) {
    echo "Usage: php test_converter.php <input.xml>\n";
    exit(1);
}

$inputFile = $argv[1];

if (!file_exists($inputFile)) {
    echo "Error: Input file not found: $inputFile\n";
    exit(1);
}

echo "Testing JATS Converter\n";
echo "=====================\n";
echo "Input file: $inputFile\n\n";

// Create output file
$outputFile = tempnam(sys_get_temp_dir(), 'jats_test_') . '.xml';

try {
    // Create converter with factory
    $factory = new JatsConverterFactory();
    $converter = $factory->create(
        '1.3',              // schema version
        null,               // schema path
        null,               // parser
        null,               // reference builder
        false,              // enable logging (use progress callback instead)
        true,               // split references
        true,               // reorder references
        false,              // enhance DOIs
        'crossref',         // DOI source
        null,               // DOI email
        [],                 // DOI config
        true                // process brackets
    );

    // Set reference check
    $converter->setCheckReferences(true);
    $converter->setVerbose(true);

    // Add progress callback
    $converter->onProgress(function($message) {
        echo "[Progress] $message\n";
    });

    echo "\nStarting conversion...\n\n";

    // Perform conversion
    $result = $converter->convert($inputFile, $outputFile);

    echo "\n--- Conversion Result ---\n";
    echo "Success: " . ($result->isSuccess() ? 'YES' : 'NO') . "\n";
    echo "\nMessages:\n";
    foreach ($result->getMessages() as $message) {
        echo "  - $message\n";
    }

    if ($result->isSuccess() && file_exists($outputFile)) {
        echo "\n--- Output File ---\n";
        echo "Location: $outputFile\n";
        echo "Size: " . filesize($outputFile) . " bytes\n";

        // Check if output has body content
        $outputXml = file_get_contents($outputFile);
        $doc = new DOMDocument();
        $doc->loadXML($outputXml);

        $bodyNodes = $doc->getElementsByTagName('body');
        echo "Body elements found: " . $bodyNodes->length . "\n";

        if ($bodyNodes->length > 0) {
            echo "Body content preview (first 500 chars):\n";
            echo substr($doc->saveXML($bodyNodes->item(0)), 0, 500) . "...\n";
        }

        echo "\nOutput file saved to: $outputFile\n";
        echo "View with: cat $outputFile\n";
    }

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
