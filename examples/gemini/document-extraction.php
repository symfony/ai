<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Fixtures\Field;
use Symfony\AI\Fixtures\FrameworkDetails;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

// Showcase the document extraction and vision capabilities of Gemini models
// See: https://docs.cloud.google.com/vertex-ai/generative-ai/docs/bounding-box-detection

if (!extension_loaded('imagick')) {
    output()->writeln('<error>The Imagick extension is not installed. Please install it to generate annotated images.</error>');
    exit(1);
}
if (!shell_exec('command -v gs')) {
    output()->writeln('<error>Ghostscript (gs) is not installed. Please install it to enable PDF reading in Imagick.</error>');
    exit(1);
}

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());
$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

$documentPath = dirname(__DIR__, 2).'/fixtures/symfony_site_document.pdf';
$messages = new MessageBag(
    Message::forSystem(<<<'TEXT'
            You are a document extraction assistant. Extract the text content and metadata from the provided document.
            Provide the extracted text, fields with their page no and bounding boxes in a structured JSON format.
        TEXT),
    Message::ofUser(
        Document::fromFile($documentPath),
    ),
);
logger()->info('Invoking Gemini model for document extraction...', ['document' => $documentPath]);
$result = $platform->invoke('gemini-2.5-flash', $messages, [
    'response_format' => FrameworkDetails::class,
]);
logger()->info('Received response from Gemini model.', ['result' => $result->asObject(), 'raw_result' => $result->getRawResult()]);

$frameworkDetails = $result->asObject();
assert($frameworkDetails instanceof FrameworkDetails);
dump($frameworkDetails);

$fieldsToPlot = [
    'latestVersion' => $frameworkDetails->latestVersion,
    'programmingLanguage' => $frameworkDetails->programmingLanguage,
    'noOfDownloads' => $frameworkDetails->noOfDownloads,
    'noOfGithubStars' => $frameworkDetails->noOfGithubStars,
    'hasLTSVersion' => $frameworkDetails->hasLTSVersion,
    'upcomingConferences' => $frameworkDetails->upcomingConferences,
];

logger()->info('Generating annotated pdf with extracted field bounding boxes...');
$outputPath = generateAnnotatedPdf($documentPath, $fieldsToPlot);
echo sprintf('Annotated pdf saved to: "%s", open it to verify the source of the extracted values.'.\PHP_EOL, $outputPath);

/**
 * Generate an annotated image with bounding boxes for the given fields.
 *
 * @param string               $pdfPath Path to the original PDF file
 * @param array<string, Field> $fields  List of Field objects with labels and bounding boxes
 *
 * @return string Path to the generated annotated pdf
 */
function generateAnnotatedPdf(string $pdfPath, array $fields): string
{
    // Read all pages from the PDF at a decent resolution
    $src = new Imagick();
    $src->setResolution(150, 150);
    $src->readImage($pdfPath);

    $pageCount = $src->getNumberImages();
    if (0 === $pageCount) {
        output()->writeln('<error>No pages found in PDF: '.$pdfPath.'</error>');
        exit(1);
    }

    // Define colors for different fields
    $colors = ['#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6', '#bfef45', '#fabed4'];

    // Try to find a system font once
    $fontPath = null;
    $fcMatch = shell_exec('fc-match -f "%{file}" "sans-serif" 2>/dev/null');
    if ($fcMatch && file_exists(trim($fcMatch))) {
        $fontPath = trim($fcMatch);
    }

    // Prepare result Imagick to collect annotated pages
    $result = new Imagick();

    // Iterate pages
    for ($pageIndex = 0; $pageIndex < $pageCount; ++$pageIndex) {
        $src->setIteratorIndex($pageIndex);
        $pageImage = $src->getImage();
        $pageImage->setImageFormat('png');

        $width = $pageImage->getImageWidth();
        $height = $pageImage->getImageHeight();

        // Create a canvas for this page (same size)
        $canvas = new Imagick();
        $canvas->newImage($width, $height, 'white');
        $canvas->setImageFormat('png');
        $canvas->compositeImage($pageImage, Imagick::COMPOSITE_OVER, 0, 0);

        // Drawing object for this page
        $draw = new ImagickDraw();
        $draw->setFontSize(12);
        if ($fontPath) {
            $draw->setFont($fontPath);
        }

        // Draw bounding boxes and labels for fields that belong to this page
        $colorIndex = 0;
        foreach ($fields as $label => $field) {
            // Expecting pageNo to be 1-based
            if ($field->pageNo !== ($pageIndex + 1)) {
                continue;
            }

            $color = $colors[$colorIndex % count($colors)];
            ++$colorIndex;

            if ([] === $field->boundingBox2D) {
                continue;
            }

            $bbox = $field->boundingBox2D;
            // boundingBox2D assumed in [y_min, x_min, y_max, x_max] in thousandths
            $xMin = (int) (($bbox[1] / 1000) * $width);
            $yMin = (int) (($bbox[0] / 1000) * $height);
            $xMax = (int) (($bbox[3] / 1000) * $width);
            $yMax = (int) (($bbox[2] / 1000) * $height);

            // Draw bounding box
            $strokePixel = new ImagickPixel($color);
            $draw->setStrokeColor($strokePixel);
            $draw->setStrokeWidth(2);
            $draw->setStrokeOpacity(1);
            $draw->setFillOpacity(0);
            $draw->rectangle($xMin, $yMin, $xMax, $yMax);

            // Draw inline label if a font is available
            if ($fontPath) {
                $labelPadding = 6;
                $labelHeight = 16;
                $labelWidth = (int) (mb_strlen((string) $label) * 7) + ($labelPadding * 2);

                // Prefer placing label above the box with a small gap
                $labelX = $xMin;
                $labelBoxTop = $yMin - $labelHeight - 4;

                // If label would be off the top of the image, place it inside the box at the top
                if ($labelBoxTop < 0) {
                    $labelBoxTop = $yMin + 4;
                }

                // Draw label background
                $bgPixel = new ImagickPixel($color);
                $draw->setFillColor($bgPixel);
                $draw->setFillOpacity(0.85);
                $draw->setStrokeOpacity(0);
                $draw->rectangle($labelX, $labelBoxTop, $labelX + $labelWidth, $labelBoxTop + $labelHeight);

                // Draw label text
                $draw->setFillColor(new ImagickPixel('white'));
                $draw->setFillOpacity(1);
                $draw->annotation($labelX + $labelPadding, $labelBoxTop + $labelHeight - 4, (string) $label);
            }
        }

        // Apply the drawing to the canvas
        $canvas->drawImage($draw);

        // Add annotated page to result
        $result->addImage($canvas);

        // Clear temporary objects for this page
        $canvas->clear();
        $pageImage->clear();
        $draw->clear();
    }

    // Write out a multi-page PDF with annotated pages
    $outputPath = __DIR__.'/annotated_'.pathinfo($pdfPath, \PATHINFO_FILENAME).'.pdf';
    $result->setImageFormat('pdf');
    $result->writeImages($outputPath, true);

    // Cleanup
    $result->clear();
    $src->clear();

    return $outputPath;
}
