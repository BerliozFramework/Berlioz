<?php

namespace Berlioz\Form\Tests\Validator;

use Berlioz\Form\Element\ElementInterface;
use Berlioz\Form\Validator\FileFormatValidator;
use Berlioz\Http\Message\UploadedFile;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class FileFormatValidatorTest extends TestCase
{
    public function testValidFileByExtension(): void
    {
        // Mock of the stream
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);

        // Mock of the uploaded file
        // The file is a PNG with MIME type image/png
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMediaType')->willReturn('image/png');
        $file->method('getClientFilename')->willReturn('image.png');
        $file->method('getStream')->willReturn($stream);

        // Mock of the form element
        // The accept attribute allows .png and .jpg extensions
        $element = $this->createMock(ElementInterface::class);
        $element->method('getOption')
            ->with('attributes.accept', [])
            ->willReturn('.png,.jpg');

        // The element returns a single file
        $element->method('getValue')->willReturn($file);

        // Execute the validator
        $validator = new FileFormatValidator();
        $constraints = $validator->validate($element);

        // No invalid format → no constraint generated
        $this->assertEmpty($constraints);
    }

    public function testInvalidFileFormat(): void
    {
        // Mock of the stream
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);

        // Mock of the uploaded file
        // The file is a PDF, which is not allowed
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMediaType')->willReturn('application/pdf');
        $file->method('getClientFilename')->willReturn('document.pdf');
        $file->method('getStream')->willReturn($stream);

        // Mock of the form element
        // Only image extensions are allowed
        $element = $this->createMock(ElementInterface::class);
        $element->method('getOption')
            ->with('attributes.accept', [])
            ->willReturn('.png,.jpg');

        // The element returns the PDF file
        $element->method('getValue')->willReturn($file);

        // Execute the validator
        $validator = new FileFormatValidator();
        $constraints = $validator->validate($element);

        // An invalid file → one constraint is generated
        $this->assertCount(1, $constraints);

        // Assert the actual detected MIME type
        // without relying on the internal implementation details
        $this->assertSame('application/pdf', $constraints[0]->getContext()['actual']);
    }

    public function testValidFileByMime(): void
    {
        // Mock of the stream
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);

        // Mock of the uploaded file
        // MIME type matches image/*
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMediaType')->willReturn('image/jpeg');
        $file->method('getClientFilename')->willReturn('photo.jpeg');
        $file->method('getStream')->willReturn($stream);

        // Mock of the form element
        // The accept attribute allows all image MIME types
        $element = $this->createMock(ElementInterface::class);
        $element->method('getOption')
            ->willReturn('image/*');

        // The element returns a single file
        $element->method('getValue')->willReturn($file);

        // Execute the validator
        $validator = new FileFormatValidator();
        $constraints = $validator->validate($element);

        // MIME type matches → no constraint generated
        $this->assertEmpty($constraints);
    }

    public function testValidateRewindsStreamAfterRead(): void
    {
        // Mock of the stream — seekable, so detectMimeType reads 4096 bytes
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('read')->with(4096)->willReturn(str_repeat("\x00", 4096));
        $stream->expects($this->once())->method('rewind');

        // Mock of the uploaded file
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientFilename')->willReturn('image.png');
        $file->method('getStream')->willReturn($stream);

        // Mock of the form element — accept all images
        $element = $this->createMock(ElementInterface::class);
        $element->method('getOption')
            ->with('attributes.accept', [])
            ->willReturn('image/*');
        $element->method('getValue')->willReturn($file);

        // Execute the validator — PHPUnit verifies rewind() was called exactly once
        $validator = new FileFormatValidator();
        $validator->validate($element);
    }

    public function testNullFilesReturnsNoConstraints(): void
    {
        // Mock of the form element
        // getValue() returns null (no uploaded file)
        $element = $this->createMock(ElementInterface::class);

        $element->method('getOption')
            ->with('attributes.accept', [])
            ->willReturn('.png,.jpg');

        // No file uploaded
        $element->method('getValue')->willReturn(null);

        $validator = new FileFormatValidator();
        $constraints = $validator->validate($element);

        // No file means no validation error
        $this->assertEmpty($constraints);
    }
}
