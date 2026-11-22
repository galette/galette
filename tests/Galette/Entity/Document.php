<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\glob;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\tempnam;
use function Safe\unlink;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Document extends GaletteTestCase
{
    protected int $seed = 20240312213127;

    /**
     * Test document object
     */
    public function testObject(): void
    {
        $document = new \Galette\Entity\Document($this->zdb);

        //getters only
        $this->assertSame('', $document->getDocumentFilename());
        $this->assertSame($document->getDestDir(), $document->getURL());
        $this->assertNull($document->getID());

        //setters and getters
        $this->assertSame('', $document->getType());
        $this->assertInstanceOf(\Galette\Entity\Document::class, $document->setType('mytype'));
        $this->assertSame('mytype', $document->getType());

        $this->assertNull($document->getComment());
        $this->assertInstanceOf(\Galette\Entity\Document::class, $document->setComment('any comment'));
        $this->assertSame('any comment', $document->getComment());
    }

    /**
     * Get an uploaded file from a temporary copy of given content
     *
     * @param string $name    Client file name
     * @param string $content File content
     *
     * @return array{document_file: \Slim\Psr7\UploadedFile}
     */
    private function getUploadedFile(string $name, string $content): array
    {
        $tmpfile = tempnam(sys_get_temp_dir(), 'galette_doc');
        file_put_contents($tmpfile, $content);
        return [
            'document_file' => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: $tmpfile,
                name: $name,
                size: strlen($content),
                error: UPLOAD_ERR_OK
            )
        ];
    }

    /**
     * Test files a web server could run are refused
     */
    public function testHandleFiles(): void
    {
        $dest = sys_get_temp_dir() . '/galette_documents_' . uniqid() . '/';
        mkdir($dest);
        $pdf = file_get_contents(GALETTE_TESTS_PATH . '/fixtures/status.pdf');
        $php = '<?php echo "pwned";';
        //no allowed list: the files a web server could run are refused all the same
        $this->preferences->pref_upload_extensions = '';
        $GLOBALS['preferences'] = $this->preferences;

        try {
            //PHP file is refused, whatever its content
            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertSame(
                ['File does not comply with requirements.'],
                $document->handleFiles($this->getUploadedFile('shell.php', $php))
            );
            $this->assertFileDoesNotExist($dest . 'shell.php');

            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertSame(
                ['File does not comply with requirements.'],
                $document->handleFiles($this->getUploadedFile('shell.phtml', $pdf))
            );
            $this->assertFileDoesNotExist($dest . 'shell.phtml');

            //PHP code is refused on its content, whatever its extension
            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertSame(
                ['Mime-Type not allowed'],
                $document->handleFiles($this->getUploadedFile('shell.pdf', $php))
            );
            $this->assertFileDoesNotExist($dest . 'shell.pdf');

            $this->expectLogEntry(\Analog\Analog::ERROR, 'Refused file shell.php, a web server could run it.');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Refused file shell.phtml, a web server could run it.');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Mimetype `text/x-php` not allowed');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been thew attempting to edit/store a document file');

            //a real PDF is accepted, and known with the name it has on disk
            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertTrue($document->handleFiles($this->getUploadedFile('Status.PDF', $pdf)));
            $this->assertFileExists($dest . 'Status.PDF');
            $this->assertSame('Status.PDF', $document->getDocumentFilename());

            //any other file too
            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertTrue($document->handleFiles($this->getUploadedFile('minutes.zip', "PK\x03\x04")));
            $this->assertFileExists($dest . 'minutes.zip');
            $this->assertSame('minutes.zip', $document->getDocumentFilename());
            $this->expectNoLogEntry();

            //a refused file does not replace the existing one, even on a retry without file
            $this->assertSame(
                ['File does not comply with requirements.'],
                $document->handleFiles($this->getUploadedFile('shell.php', $php))
            );
            $this->assertSame('minutes.zip', $document->getDocumentFilename());
            $this->assertTrue($document->handleFiles([
                'document_file' => new \Slim\Psr7\UploadedFile(
                    fileNameOrStream: 'php://memory',
                    error: UPLOAD_ERR_NO_FILE
                )
            ]));
            $this->assertSame('minutes.zip', $document->getDocumentFilename());
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Refused file shell.php, a web server could run it.');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been thew attempting to edit/store a document file');
        } finally {
            unset($GLOBALS['preferences']);
            array_map(unlink(...), glob($dest . '*'));
            rmdir($dest);
        }
    }

    /**
     * Test documents follow the allowed extensions
     */
    public function testAllowedExtensions(): void
    {
        $dest = sys_get_temp_dir() . '/galette_documents_' . uniqid() . '/';
        mkdir($dest);
        $this->preferences->pref_upload_extensions = 'pdf odt';
        $GLOBALS['preferences'] = $this->preferences;

        try {
            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertSame(
                ['File extension is not allowed, only pdf, odt files are.'],
                $document->handleFiles($this->getUploadedFile('minutes.zip', "PK\x03\x04"))
            );
            $this->assertFileDoesNotExist($dest . 'minutes.zip');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Invalid extension for file minutes.zip.');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been thew attempting to edit/store a document file');

            $document = new \Galette\Entity\Document($this->zdb);
            $document->setDestDir($dest);
            $this->assertTrue($document->handleFiles($this->getUploadedFile('Minutes.ODT', 'content')));
            $this->assertFileExists($dest . 'Minutes.ODT');
            $this->expectNoLogEntry();
        } finally {
            unset($GLOBALS['preferences']);
            array_map(unlink(...), glob($dest . '*'));
            rmdir($dest);
        }
    }
}
