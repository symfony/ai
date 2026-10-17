<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function writePdf(string $filename, bool $withMetadata): void
{
    $xmp = <<<'XML'
        <x:xmpmeta xmlns:x="adobe:ns:meta/">
          <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
            <rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">
              <dc:title><rdf:Alt><rdf:li xml:lang="x-default"> XMP title </rdf:li></rdf:Alt></dc:title>
              <dc:creator><rdf:Seq><rdf:li> Alice </rdf:li><rdf:li>Bob</rdf:li></rdf:Seq></dc:creator>
              <dc:description>XMP description</dc:description>
              <dc:subject><rdf:Bag><rdf:li>one</rdf:li><rdf:li>two</rdf:li></rdf:Bag></dc:subject>
              <xmp:CreateDate>2024-01-02T03:04:05Z</xmp:CreateDate>
              <xmp:ModifyDate>2024-02-03T04:05:06Z</xmp:ModifyDate>
            </rdf:Description>
          </rdf:RDF>
        </x:xmpmeta>
        XML;
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R'.($withMetadata ? ' /Metadata 10 0 R' : '').' >>',
        '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R] /Count 3 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Resources << /Font << /F1 9 0 R >> >> /Contents 4 0 R >>',
        '',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Contents 6 0 R >>',
        "<< /Length 0 >>\nstream\n\nendstream",
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Resources << /Font << /F1 9 0 R >> >> /Contents 8 0 R >>',
        '',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    foreach ([3 => 'BT /F1 12 Tf 30 250 Td (First page) Tj 0 -30 Td (Second paragraph) Tj ET', 7 => 'BT /F1 12 Tf 30 250 Td (Third page) Tj ET'] as $index => $text) {
        $objects[$index] = '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream";
    }
    if ($withMetadata) {
        $objects[] = '<< /Type /Metadata /Subtype /XML /Length '.strlen($xmp)." >>\nstream\n".$xmp."\nendstream";
        $objects[] = '<< /Title ( Embedded title ) /Subject ( ) >>';
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R'.($withMetadata ? ' /Info 11 0 R' : '')." >>\nstartxref\n".$xref."\n%%EOF\n";
    file_put_contents(__DIR__.'/'.$filename, $pdf);
}

writePdf('controlled.pdf', true);
writePdf('untitled.pdf', false);
